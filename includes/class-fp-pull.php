<?php

class FP_Pull {

	private $_verbose_log = array();

	/**
	 * Instantiating this class does a pull
	 */
	public function __construct() {
		$this->do_pull();
	}

	private function log( $message, $type = 'status' ) {
		$this->_verbose_log[] = array(
			'message' => $message,
			'type' => $type,
		);
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


			$feed_url = get_post_meta( get_the_ID(), 'fp_feed_url', true );
			$posts_xpath = get_post_meta( get_the_ID(), 'fp_posts_xpath', true );
			//$namespace = get_post_meta( get_the_ID(), 'fp_namespace', true );
			$field_map = get_post_meta( get_the_ID(), 'fp_field_map', true );
			$new_post_status = get_post_meta( get_the_ID(), 'fp_post_status', true );
			$new_post_type = get_post_meta( get_the_ID(), 'fp_post_type', true );

			if ( empty( $posts_xpath ) ) {
				$this->log( 'No xpath to post items', 'error' );
				continue;
			}

			if ( empty( $feed_url ) ) {
				$this->log( 'No feed URL', 'error' );
				continue;
			}

			if ( empty( $field_map ) ) {
				$this->log( 'No field map', 'error' );
				continue;
			}

			$raw_feed_contents = $this->fetch_feed( $feed_url );

			if ( is_wp_error( $raw_feed_contents ) ) {
				$this->log( 'Could not fetch feed', 'error' );
				continue;
			}

			$feed = simplexml_load_string( $raw_feed_contents );

			$posts = $feed->xpath( $posts_xpath );

			if ( empty( $posts ) ) {
				$this->log( 'No items in feed', 'warning' );
				continue;
			}

			foreach ( $posts as $post ) {

				$new_post_args = array(
					'post_type' => $new_post_type,
					'post_status' => $new_post_status,
				);
				$meta_fields = array();

				foreach ( $field_map as $field ) {
					if ( 'post_meta' == $field['mapping_type'] ) {
						$meta_fields[] = $field;
					} else {
						$values = $post->xpath( $field['source_field'] );

						if ( empty( $values ) ) {
							$this->log( 'Xpath to source field returns nothing for ' . $field['source_field'], 'warning' );
						} elseif ( is_array( $values ) && count( $values ) === 1 ) {
							$new_post_args[$field['destination_field']] = apply_filters( 'fp_pre_post_insert_value', (string) $values[0], $field );
						} else {
							// Todo: is this possible?
						}
					}
				}

				$this->log( 'Attempting to create a new post with arguments ' . print_r( $new_post_args, true ), 'error' );

				$new_post_id = wp_insert_post( apply_filters( 'fp_new_post_args', $new_post_args ), true );

				if ( is_wp_error( $new_post_id ) ) {
					$this->log( 'Could not create new post: ' . $new_post_id->get_error_message(), 'error' );
				} else {
					$this->log( 'Created new post (' . $new_post_id . ') titled ' . get_the_title( $new_post_id ) , 'status' );

					foreach ( $meta_fields as $field ) {
						$values = $post->xpath( $field['source_field'] );

						if ( empty( $values ) ) {
							$this->log( 'Xpath to source field returns nothing for ' . $field['source_field'], 'warning' );
						} elseif ( is_array( $values ) && count( $values ) === 1 ) {
							$meta_value = apply_filters( 'fp_pre_post_insert_value', $values[0], $field );
						} else {
							$meta_value = apply_filters( 'fp_pre_post_insert_value', $values, $field );
						}

						// Todo: santization?
						update_post_meta( $new_post_id, $field['destination_field'], $meta_value );
					}
				}
			}
		}

		wp_reset_postdata();

		echo '<pre>'; var_dump( $this->_verbose_log ); echo '</pre>';
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