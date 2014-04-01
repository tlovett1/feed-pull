<?php
/**
 * This class pulls from all source feeds
 */

class FP_Pull {

	private $_verbose_log = array();

	/**
	 * Instantiating this class does a pull
	 */
	public function __construct() {
		$this->do_pull();
	}

	/**
	 * Log a message during a feed pull
	 *
	 * @param $message
	 * @param $source_feed_id
	 * @param string $type
	 */
	private function log( $message, $source_feed_id, $type = 'status' ) {
		if ( empty( $this->_verbose_log[$source_feed_id] ) ) {
			$this->_verbose_log[$source_feed_id] = array();
		}

		$this->_verbose_log[$source_feed_id][] = array(
			'message' => $message,
			'type' => $type,
		);
	}

	/**
	 * Get pull log for a source feed
	 *
	 * @param int $source_feed_id
	 * @return array|bool
	 */
	public function get_log( $source_feed_id = 0 ) {
		if ( empty( $source_feed_id ) ) {
			return $this->_verbose_log;
		}

		if ( empty( $this->_verbose_log[$source_feed_id] ) ) {
			return false;
		}

		return $this->_verbose_log[$source_feed_id];
	}

	private function truncate_string( $string ) {
		if ( strlen( $string ) > 50 ) {
			$string = substr( $string, 0, 50 ) . '...';
		}

		return $string;
	}

	/**
	 * Lookup a post by guid
	 *
	 * @param $guid
	 * @return bool
	 */
	private function lookup_post_by_guid( $guid ) {
		global $wpdb;

		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->posts WHERE guid = '%s' LIMIT 1", $guid ) );

		if ( $post_id )
			return $post_id;

		return false;
	}

	/**
	 * Pull from all our source feeds
	 */
	private function do_pull() {
		$args = array(
			'post_type' => 'fp_feed',
			'post_status' => 'publish',
			'posts_per_page' => apply_filters( 'fp_max_source_feeds', 100 ),
			'no_found_rows' => false,
			'cache_results' => false,
		);

		$source_feeds = new WP_Query( $args );

		if ( ! $source_feeds->have_posts() ) {
			$this->log( 'No source feeds found', 'error' );
			return;
		}

		while ( $source_feeds->have_posts() ) {
			$source_feeds->the_post();

			$this->log( 'Pulling source feed: ' . get_the_title(), 'status' );

			$source_feed_id = get_the_ID();


			$feed_url = get_post_meta( $source_feed_id, 'fp_feed_url', true );
			$posts_xpath = get_post_meta( $source_feed_id, 'fp_posts_xpath', true );
			//$namespace = get_post_meta( $source_feed_id, 'fp_namespace', true );
			$field_map = get_post_meta( $source_feed_id, 'fp_field_map', true );
			$new_post_status = get_post_meta( $source_feed_id, 'fp_post_status', true );
			$new_post_type = get_post_meta( $source_feed_id, 'fp_post_type', true );
			$allow_updates = get_post_meta( $source_feed_id, 'fp_allow_updates', true );

			if ( empty( $posts_xpath ) ) {
				$this->log( 'No xpath to post items', $source_feed_id, 'error' );
				continue;
			}

			if ( empty( $feed_url ) ) {
				$this->log( 'No feed URL', $source_feed_id, 'error' );
				continue;
			}

			if ( empty( $field_map ) ) {
				$this->log( 'No field map', $source_feed_id, 'error' );
				continue;
			}

			$raw_feed_contents = $this->fetch_feed( $feed_url );

			if ( is_wp_error( $raw_feed_contents ) ) {
				$this->log( 'Could not fetch feed', $source_feed_id, 'error' );
				continue;
			}

			$feed = simplexml_load_string( $raw_feed_contents );

			$posts = $feed->xpath( $posts_xpath );

			if ( empty( $posts ) ) {
				$this->log( 'No items in feed', $source_feed_id, 'warning' );
				continue;
			}

			do_action( 'fp_pre_source_feed_pull', $source_feed_id );

			foreach ( $posts as $post ) {

				$new_post_args = array(
					'post_type' => $new_post_type,
					'post_status' => $new_post_status,
					'post_excerpt' => '',
				);
				$meta_fields = array();

				foreach ( $field_map as $field ) {
					if ( 'post_meta' == $field['mapping_type'] ) {
						$meta_fields[] = $field;
					} else {
						$values = $post->xpath( $field['source_field'] );

						if ( empty( $values ) ) {
							$this->log( 'Xpath to source field returns nothing for ' . $field['source_field'], $source_feed_id, 'warning' );
						} elseif ( is_array( $values ) && count( $values ) === 1 ) {
							$new_post_args[$field['destination_field']] = apply_filters( 'fp_pre_post_insert_value', (string) $values[0], $field, $post, $source_feed_id );
						} else {
							// Todo: is this possible?
						}
					}
				}

				// Make sure we have all the required fields
				$required_fields = FP_Source_Feed_CPT::get_required_fields();
				foreach ( $new_post_args as $arg_key => $arg_value ) {
					if ( ! empty( $arg_value ) && in_array( $arg_key, $required_fields ) ) {
						unset( $required_fields[array_search( $arg_key, $required_fields )]);
					}
				}

				if ( ! empty( $required_fields ) ) {
					$this->log( 'Missing required fields to create/update post', $source_feed_id, 'error' );
					continue;
				}

				// Check if post exists by guid
				$existing_post_id = $this->lookup_post_by_guid( $new_post_args['guid'] );
				if ( ! empty( $existing_post_id ) ) {
					if ( $allow_updates ) {
						$new_post_args['ID'] = $existing_post_id;
					} else {
						$this->log( 'Post already exists and updates are not allowed.', $source_feed_id, 'error' );
					}
				}

				$this->log( 'Attempting to create a new post with arguments ' . print_r( array_map( array( $this, 'truncate_string' ), $new_post_args ), true ), $source_feed_id, 'status' );

				$new_post_id = wp_insert_post( apply_filters( 'fp_new_post_args', $new_post_args, $post, $source_feed_id ), true );

				if ( is_wp_error( $new_post_id ) ) {
					$this->log( 'Could not create new post: ' . $new_post_id->get_error_message(), 'error' );
				} else {
					$this->log( 'Created new post (' . $new_post_id . ') with title: ' . get_the_title( $new_post_id ) , $source_feed_id, 'status' );

					// Mark the post as syndicated
					update_post_meta( $new_post_id, 'fp_syndicated_post', true );

					foreach ( $meta_fields as $field ) {
						$values = $post->xpath( $field['source_field'] );

						if ( empty( $values ) ) {
							$this->log( 'Xpath to source field returns nothing for ' . $field['source_field'], $post, $source_feed_id, 'warning' );
						} elseif ( is_array( $values ) && count( $values ) === 1 ) {
							$meta_value = apply_filters( 'fp_pre_post_insert_value', $values[0], $field, $post, $source_feed_id );
						} else {
							$meta_value = apply_filters( 'fp_pre_post_insert_value', $values, $field, $post, $source_feed_id );
						}

						// Todo: sanitization?
						update_post_meta( $new_post_id, $field['destination_field'], $meta_value );
					}
				}
			}

			// Save last pull into log for source feed
			if ( apply_filters( 'fp_log_last_pull', true, $source_feed_id ) ) {
				// Todo: sanitiziation?
				update_post_meta( $source_feed_id, 'fp_last_pull_log', $this->get_log( $source_feed_id ) );
				update_post_meta( $source_feed_id, 'fp_last_pull_time', time() );
			}

			do_action( 'fp_post_source_feed_pull', $source_feed_id );
		}

		wp_reset_postdata();
	}

	/**
	 * Get contents of feed file
	 *
	 * @param $url
	 * @return array|string|WP_Error
	 */
	private function fetch_feed( $url ) {
		$request = wp_remote_get( $url );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		return wp_remote_retrieve_body( $request );
	}
}