<?php
/**
 * This class pulls from all source feeds
 */

class FP_Pull {

	private $_feed_log = array();

	/**
	 * Instantiating this class does a pull
	 *
	 * @param int $source_feed_id
	 * @since 0.1.0
	 */
	public function __construct( $source_feed_id = null ) {
		$this->do_pull( $source_feed_id );
	}

	/**
	 * Log a message during a feed pull
	 *
	 * @param $message
	 * @param int $source_feed_id
	 * @param string $type
	 * @param int $post_id
	 * @since 0.1.0
	 */
	private function log( $message, $source_feed_id, $type = 'status', $post_id = null ) {
		if ( empty( $this->_feed_log[$source_feed_id] ) ) {
			$this->_feed_log[$source_feed_id] = array();
		}

		$this->_feed_log[$source_feed_id][] = array(
			'message' => sanitize_text_field( $message ),
			'type' => sanitize_text_field( $type ),
			'post_id' => (int) $post_id,
		);
	}

	/**
	 * Grab log messages by type
	 *
	 * @param int $source_feed_id
	 * @param string $type
	 * @since 0.1.5
	 * @return boolean
	 */
	public function get_log_messages_by_type( $source_feed_id, $type ) {
		$messages = array();

		foreach ( $this->_feed_log[$source_feed_id] as $log_entry ) {
			if ( $type == $log_entry['type'] ) {
				$messages[] = $log_entry;
			}
		}

		return $messages;
	}

	/**
	 * Get pull log for a source feed
	 *
	 * @param int $source_feed_id
	 * @since 0.1.0
	 * @return array|bool
	 */
	public function get_log( $source_feed_id = null ) {
		if ( empty( $source_feed_id ) ) {
			return $this->_feed_log;
		}

		if ( empty( $this->_feed_log[$source_feed_id] ) ) {
			return false;
		}

		return $this->_feed_log[$source_feed_id];
	}

	/**
	 * Lookup a post by guid
	 *
	 * @param string $guid
	 * @since 0.1.0
	 * @return bool|int
	 */
	private function lookup_post_by_guid( $guid ) {
		global $wpdb;

		$sanitized_guid = sanitize_text_field( $guid );

		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'fp_guid' AND meta_value = '%s' LIMIT 1", $sanitized_guid ) );

		if ( $post_id ) {
			return $post_id;
		}

		return false;
	}

	/**
	 * Handle feed logging
	 *
	 * @param $source_feed_id
	 * @return bool
	 */
	private function handle_feed_log( $source_feed_id ) {
		if ( apply_filters( 'fp_log_last_pull', true, $source_feed_id ) ) {
			// Todo: sanitiziation?
			update_post_meta( $source_feed_id, 'fp_last_pull_log', $this->get_log( $source_feed_id ) );
			update_post_meta( $source_feed_id, 'fp_last_pull_time', current_time( 'timestamp' ) );

			return true;
		}

		return false;
	}

	/**
	 * Pull from all our source feeds
	 *
	 * @param int $source_feed_id
	 * @since 0.1.0
	 */
	private function do_pull( $source_feed_id = null ) {

		// Do nothing if feed pulling is not turned on
		$option = fp_get_option();
		if ( empty( $option['enable_feed_pull'] ) ) {
			return;
		}

		$args = array(
			'post_type' => 'fp_feed',
			'post_status' => 'publish',
			'posts_per_page' => apply_filters( 'fp_max_source_feeds', 100 ),
			'no_found_rows' => false,
			'cache_results' => false,
		);

		if ( ! empty( $source_feed_id ) ) {
			$args['p'] = (int) $source_feed_id;
		}

		$source_feeds = new WP_Query( $args );

		if ( ! $source_feeds->have_posts() ) {
			return;
		}

		while ( $source_feeds->have_posts() ) {
			$source_feeds->the_post();

			$source_feed_id = get_the_ID();

			$this->log( __( 'Pulling feed', 'feed-pull' ), $source_feed_id, 'status' );

			$feed_url = get_post_meta( $source_feed_id, 'fp_feed_url', true );
			$posts_xpath = get_post_meta( $source_feed_id, 'fp_posts_xpath', true );
			$field_map = get_post_meta( $source_feed_id, 'fp_field_map', true );
			$new_post_status = get_post_meta( $source_feed_id, 'fp_post_status', true );
			$new_post_type = get_post_meta( $source_feed_id, 'fp_post_type', true );
			$allow_updates = get_post_meta( $source_feed_id, 'fp_allow_updates', true );
			$post_categories = get_post_meta( $source_feed_id, 'fp_new_post_categories', true );

			if ( empty( $posts_xpath ) ) {
				$this->log( __( 'No xpath to post items', 'feed-pull' ), $source_feed_id, 'error' );
				$this->handle_feed_log( $source_feed_id );
				continue;
			}

			if ( empty( $feed_url ) ) {
				$this->log( __( 'No feed URL', 'feed-pull' ), $source_feed_id, 'error' );
				$this->handle_feed_log( $source_feed_id );
				continue;
			}

			if ( empty( $field_map ) ) {
				$this->log( __( 'No field map', 'feed-pull' ), $source_feed_id, 'error' );
				$this->handle_feed_log( $source_feed_id );
				continue;
			}

			$raw_feed_contents = $this->fetch_feed( $feed_url );

			if ( is_wp_error( $raw_feed_contents ) ) {
				$this->log( __( 'Could not fetch feed', 'feed-pull' ), $source_feed_id, 'error' );
				$this->handle_feed_log( $source_feed_id );
				continue;
			}

			$feed = simplexml_load_string( $raw_feed_contents );

			$posts = $feed->xpath( $posts_xpath );

			if ( empty( $posts ) ) {
				$this->log( __( 'No items in feed', 'feed-pull' ), $source_feed_id, 'warning' );
				$this->handle_feed_log( $source_feed_id );
				continue;
			}

			do_action( 'fp_pre_feed_pull', $source_feed_id );

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
							$this->log( sprintf( __( 'Xpath to source field returns nothing for %s', 'feed-pull' ), sanitize_text_field( $field['source_field'] ) ), $source_feed_id, 'warning' );
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
					$this->log( __( 'Missing required fields to create/update post', 'feed-pull' ), $source_feed_id, 'error' );
					continue;
				}

				$update = false;

				// Check if post exists by guid
				$existing_post_id = $this->lookup_post_by_guid( $new_post_args['guid'] );

				if ( ! empty( $existing_post_id ) ) {
					if ( $allow_updates ) {
						$update = true;
						$this->log( sprintf( __( 'Attempting to update post with guid %s', 'feed-pull' ), sanitize_text_field( $new_post_args['guid'] ) ), $source_feed_id, 'status' );
						$new_post_args['ID'] = $existing_post_id;
						unset( $new_post_args['guid'] );
					} else {
						$this->log( __( 'Post already exists and updates are not allowed.', 'feed-pull' ), $source_feed_id, 'error' );
						continue;
					}
				} else {
					$this->log( sprintf( __( 'Attempting to create post with guid %s', 'feed-pull' ), sanitize_text_field( $new_post_args['guid'] ) ), $source_feed_id, 'status' );
				}

				$new_post_id = wp_insert_post( apply_filters( 'fp_post_args', $new_post_args, $post, $source_feed_id ), true );

				// Set categories if they exist
				// Todo: what if the post type does not support categories?
				if ( ! empty( $post_categories ) ) {
					$sanitized_post_categories = array_map( 'absint', $post_categories );

					wp_set_object_terms( $new_post_id, apply_filters( 'fp_post_categories', $sanitized_post_categories ), 'category', true );
				}


				if ( is_wp_error( $new_post_id ) ) {
					if ( $update ) {
						$this->log( sprintf( __( 'Could not update post: %s', 'feed-pull' ), $new_post_id->get_error_message() ), 'error' );
					} else {
						$this->log( sprintf( __( 'Could not create post: %s', 'feed-pull' ), $new_post_id->get_error_message() ), 'error' );
					}
				} else {
					if ( $update ) {
						do_action( 'fp_updated_post', $new_post_id, $source_feed_id );
						$this->log( __( 'Updated post', 'feed-pull' ), $source_feed_id, 'status', $new_post_id );
					} else {
						do_action( 'fp_created_post', $new_post_id, $source_feed_id );
						$this->log( __( 'Created new post', 'feed-pull' ), $source_feed_id, 'status', $new_post_id );
					}

					// Mark the post as syndicated
					update_post_meta( $new_post_id, 'fp_syndicated_post', true );

					// Save GUID for post in meta. We have to do this because of this core WP
					// bug: https://core.trac.wordpress.org/ticket/24248
					if ( ! $update ) {
						update_post_meta( $new_post_id, 'fp_guid', sanitize_text_field( $new_post_args['guid'] ) );
					}

					foreach ( $meta_fields as $field ) {
						$values = $post->xpath( $field['source_field'] );

						if ( empty( $values ) ) {
							$this->log( sprintf( __( 'Xpath to source field returns nothing for %s', 'feed-pull' ), sanitize_text_field( $field['source_field'] ) ), $source_feed_id, 'warning', $new_post_id );
						} else {
							$meta_value = apply_filters( 'fp_pre_post_meta_value', (string) $values[0], $field, $post, $source_feed_id );
						}

						// Todo: sanitization?
						update_post_meta( $new_post_id, $field['destination_field'], $meta_value );
					}
				}
			}

			// Save last pull into log for source feed
			$this->handle_feed_log( $source_feed_id );

			do_action( 'fp_post_feed_pull', $source_feed_id );
		}

		wp_reset_postdata();
	}

	/**
	 * Get contents of feed file
	 *
	 * @param $url
	 * @since 0.1.0
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