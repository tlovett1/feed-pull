<?php
/**
 * This class pulls from all source feeds
 */

class FP_Pull {

	private $_feed_log = array();

	/**
	 * Instantiating this class does a pull
	 *
	 * @param int|null|bool $source_feed_id
	 * @since 0.1.0
	 */
	public function __construct( $source_feed_id = null ) {

		if ( false !== $source_feed_id ) {
			$this->do_pull( $source_feed_id );
		}

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
	 * @return array
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
		/**
		 * @var $wpdb wpdb
		 */
		global $wpdb;

		$sanitized_guid = sanitize_text_field( $guid );

		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'fp_guid' AND meta_value = %s LIMIT 1", $sanitized_guid ) );

		if ( $post_id ) {
			return $post_id;
		}

		return false;
	}

	/**
	 * Setup namespaces for SimpleXMLElement for next XPath query
	 *
	 * @param SimpleXMLElement $feed
	 * @param array $custom_namespaces
	 * @return SimpleXMLElement
	 */
	public function setupCustomNamespaces( &$feed, $custom_namespaces ) {
		if ( ! empty( $custom_namespaces ) && is_array( $custom_namespaces ) ) {
			foreach ( $custom_namespaces as $namespace ) {
				$feed->registerXPathNamespace( esc_attr( $namespace['namespace_prefix'] ), esc_url_raw( $namespace['namespace_url'] ) );
			}
		}

		return $feed;
	}

	/**
	 * Handle feed logging
	 *
	 * @param $source_feed_id
	 * @return bool
	 */
	private function handle_feed_log( $source_feed_id ) {
		if ( apply_filters( 'fp_log_last_pull', true, $source_feed_id ) ) {
			update_post_meta( $source_feed_id, 'fp_last_pull_log', $this->get_log( $source_feed_id ) );
			update_post_meta( $source_feed_id, 'fp_last_pull_time', current_time( 'timestamp' ) );

			return true;
		}

		return false;
	}

	/**
	 * Get source feed posts
	 *
	 * @param int $source_feed_id
	 * @since 1.0.0
	 *
	 * @return array|boolean Feed posts array of mapped fields or false on error
	 */
	public function get_feed_posts( $source_feed_id ) {

		if ( get_the_ID() != $source_feed_id ) {
			$feed = get_post( $source_feed_id );

			setup_postdata( $feed );
		}

		$new_post_status = get_post_meta( $source_feed_id, 'fp_post_status', true );
		$new_post_type = get_post_meta( $source_feed_id, 'fp_post_type', true );

		$feed_url = get_post_meta( $source_feed_id, 'fp_feed_url', true );
		$item_xpath = get_post_meta( $source_feed_id, 'fp_posts_xpath', true );
		$field_map = get_post_meta( $source_feed_id, 'fp_field_map', true );
		$custom_namespaces = get_post_meta( $source_feed_id, 'fp_custom_namespaces', true );

		// Provide some extra control for custom namespaces
		$custom_namespaces = apply_filters( 'fp_custom_namespaces', $custom_namespaces, $source_feed_id );

		if ( empty( $item_xpath ) ) {
			$this->log( __( 'No xpath to post items', 'feed-pull' ), $source_feed_id, 'error' );

			$this->handle_feed_log( $source_feed_id );

			return false;
		}

		if ( empty( $feed_url ) ) {
			$this->log( __( 'No feed URL', 'feed-pull' ), $source_feed_id, 'error' );

			$this->handle_feed_log( $source_feed_id );

			return false;
		}

		if ( empty( $field_map ) ) {
			$this->log( __( 'No field map', 'feed-pull' ), $source_feed_id, 'error' );

			$this->handle_feed_log( $source_feed_id );

			return false;
		}

		$raw_feed_contents = fp_fetch_feed( $feed_url );

		if ( is_wp_error( $raw_feed_contents ) ) {
			$this->log( __( 'Could not fetch feed', 'feed-pull' ), $source_feed_id, 'error' );

			$this->handle_feed_log( $source_feed_id );

			return false;
		}

		// Suppress all warnings/errors for this
		$feed = @simplexml_load_string( $raw_feed_contents );

		if ( ! $feed ) {
			$this->log( __( 'Feed could not be parsed', 'feed-pull' ), $source_feed_id, 'error' );

			$this->handle_feed_log( $source_feed_id );

			return false;
		}

		$this->setupCustomNamespaces( $feed, $custom_namespaces );

		$items = $feed->xpath( $item_xpath );

		if ( empty( $items ) ) {
			$this->log( __( 'No items in feed', 'feed-pull' ), $source_feed_id, 'warning' );

			$this->handle_feed_log( $source_feed_id );

			return false;
		}

		$posts = array();

		foreach ( $items as $item ) {
			$post = array(
				'post_fields' => array(
					'post_type' => $new_post_type,
					'post_status' => $new_post_status,
					'post_excerpt' => '',
				),
				'meta_fields' => array(),
				'taxonomy_fields' => array(),
				'item' => $item
			);

			/**
			 * Handle post field mapping
			 */
			foreach ( $field_map as $field ) {
				if ( 'post_meta' == $field[ 'mapping_type' ] ) {
					$this->setupCustomNamespaces( $item, $custom_namespaces );

					$values = $item->xpath( $field[ 'source_field' ] );

					if ( empty( $values ) ) {
						$this->log( sprintf( __( 'Xpath to source field returns nothing for %s', 'feed-pull' ), sanitize_text_field( $field[ 'source_field' ] ) ), $source_feed_id, 'warning' );
					} else {
						if ( count( $values ) > 1 ) {
							$pre_filter_meta_value = array();

							foreach ( $values as $value ) {
								$pre_filter_meta_value[] = (string) $value;
							}
						} else {
							$pre_filter_meta_value = (string) $values[ 0 ];
						}

						$post[ 'meta_fields' ][] = apply_filters( 'fp_pre_post_meta_value', $pre_filter_meta_value, $field, $item, $source_feed_id );
					}
				} elseif ( 'taxonomy' == $field[ 'mapping_type' ] ) {
					$this->setupCustomNamespaces( $item, $custom_namespaces );

					$values = $item->xpath( $field[ 'source_field' ] );

					if ( empty( $values ) ) {
						$this->log( sprintf( __( 'Xpath to source field returns nothing for %s', 'feed-pull' ), sanitize_text_field( $field[ 'source_field' ] ) ), $source_feed_id, 'warning' );
					} else {
						$pre_filter_terms = array();

						foreach ( $values as $value ) {
							$pre_filter_terms[] = (string) $value;
						}

						$post[ 'taxonomy_fields' ][] = apply_filters( 'fp_pre_terms_set', $pre_filter_terms, $field, $item, $source_feed_id );
					}
				} else {
					$this->setupCustomNamespaces( $item, $custom_namespaces );

					$values = $item->xpath( $field[ 'source_field' ] );

					if ( empty( $values ) ) {
						$this->log( sprintf( __( 'Xpath to source field returns nothing for %s', 'feed-pull' ), sanitize_text_field( $field[ 'source_field' ] ) ), $source_feed_id, 'warning' );
					} else {
						if ( count( $values ) > 1 ) {
							$pre_filter_post_value = array();

							foreach ( $values as $value ) {
								$pre_filter_post_value[] = (string) $value;
							}
						} else {
							$pre_filter_post_value = (string) $values[ 0 ];
						}

						$post[ 'post_fields' ][ $field[ 'destination_field' ] ] = apply_filters( 'fp_pre_post_insert_value', $pre_filter_post_value, $field, $item, $source_feed_id );
					}
				}
			}

			// Make sure we have all the required fields
			$required_fields = FP_Source_Feed_CPT::get_required_fields();

			foreach ( $post[ 'post_fields' ] as $arg_key => $arg_value ) {
				if ( ! empty( $arg_value ) && in_array( $arg_key, $required_fields ) ) {
					unset( $required_fields[ array_search( $arg_key, $required_fields ) ] );
				}
			}

			foreach ( $post[ 'meta_fields' ] as $arg_key => $arg_value ) {
				if ( ! empty( $arg_value ) && in_array( $arg_key, $required_fields ) ) {
					unset( $required_fields[ array_search( $arg_key, $required_fields ) ] );
				}
			}

			foreach ( $post[ 'taxonomy_fields' ] as $arg_key => $arg_value ) {
				if ( ! empty( $arg_value ) && in_array( $arg_key, $required_fields ) ) {
					unset( $required_fields[ array_search( $arg_key, $required_fields ) ] );
				}
			}

			if ( ! empty( $required_fields ) ) {
				$this->log( __( 'Missing required fields to create/update post', 'feed-pull' ), $source_feed_id, 'error' );

				continue;
			}

			$posts[] = $post;
		}

		return $posts;

	}

	/**
	 * Pull from all our source feeds
	 *
	 * @param int|null $source_feed_id Source Feed ID to pull one feed or null to pull all
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

			$posts = $this->get_feed_posts( $source_feed_id );

			if ( empty( $posts ) ) {
				continue;
			}

			$allow_updates = get_post_meta( $source_feed_id, 'fp_allow_updates', true );
			$post_categories = get_post_meta( $source_feed_id, 'fp_new_post_categories', true );

			do_action( 'fp_pre_feed_pull', $source_feed_id );

			foreach ( $posts as $post ) {
				$new_post_args = $post[ 'post_fields' ];

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
						$this->log( __( 'Post already exists and updates are not allowed.', 'feed-pull' ), $source_feed_id, 'warning' );
						continue;
					}
				} else {
					// Since we know an existing post doesn't exist. Let's make sure the post
					// hasn't been deleted in the past
					$deleted_posts = get_option( FP_DELETED_OPTION_NAME );

					if ( ! empty( $deleted_posts ) && in_array( esc_url_raw( $new_post_args['guid'] ), $deleted_posts ) ) {
						$this->log( __( 'A post with this GUID has already been syndicated and deleted.', 'feed-pull' ), $source_feed_id, 'warning' );
						continue;
					}

					$this->log( sprintf( __( 'Attempting to create post with guid %s', 'feed-pull' ), sanitize_text_field( $new_post_args['guid'] ) ), $source_feed_id, 'status' );
				}

				// Some post fields need special attention
				if ( apply_filters( 'fp_format_post_dates', true, $new_post_args, $post[ 'item' ], $source_feed_id ) ) {
					if ( ! empty( $new_post_args['post_date'] ) ) {
						$new_post_args['post_date'] = date( 'Y-m-d H:i:s', strtotime( $new_post_args['post_date'] ) );
					}

					if ( ! empty( $new_post_args['post_date_gmt'] ) ) {
						$new_post_args['post_date_gmt'] = date( 'Y-m-d H:i:s', strtotime( $new_post_args['post_date_gmt'] ) );
					}
				}

				// Handle author mapping
				if ( ! empty( $new_post_args['post_author'] ) ) {
					if ( apply_filters( 'fp_post_author_id_lookup', true, $new_post_args['post_author'], $post[ 'item' ], $source_feed_id ) && ! is_numeric( $new_post_args['post_author'] ) ) {
						$user = get_user_by( 'login', $new_post_args['post_author'] );

						if ( $user ) {
							$new_post_args['post_author'] = $user->ID;
						}
					}
				}

				$post_args = apply_filters( 'fp_post_args', $new_post_args, $post[ 'item' ], $source_feed_id );

				if ( $update ) {
					$new_post_id = wp_update_post( $post_args, true );
				} else {
					$new_post_id = wp_insert_post( $post_args, true );
				}

				if ( is_wp_error( $new_post_id ) ) {
					if ( $update ) {
						$this->log( sprintf( __( 'Could not update post: %s', 'feed-pull' ), $new_post_id->get_error_message() ), $source_feed_id, 'error' );
					} else {
						$this->log( sprintf( __( 'Could not create post: %s', 'feed-pull' ), $new_post_id->get_error_message() ), $source_feed_id, 'error' );
					}
				} else {
					if ( $update ) {
						do_action( 'fp_updated_post', $new_post_id, $source_feed_id );
						$this->log( __( 'Updated post', 'feed-pull' ), $source_feed_id, 'status', $new_post_id );
					} else {
						do_action( 'fp_created_post', $new_post_id, $source_feed_id );
						$this->log( __( 'Created new post', 'feed-pull' ), $source_feed_id, 'status', $new_post_id );
					}

					// Set categories if they exist
					if ( ! empty( $post_categories ) && is_object_in_taxonomy( $post_args[ 'post_type' ], 'category' ) ) {
						$sanitized_post_categories = array_map( 'absint', $post_categories );

						wp_set_object_terms( $new_post_id, apply_filters( 'fp_post_categories', $sanitized_post_categories ), 'category', $update );
					}

					// Mark the post as syndicated
					update_post_meta( $new_post_id, 'fp_syndicated_post', 1 );
					update_post_meta( $new_post_id, 'fp_source_feed_id', (int) $source_feed_id );

					// Save GUID for post in meta. We have to do this because of this core WP
					// bug: https://core.trac.wordpress.org/ticket/24248
					if ( ! $update ) {
						update_post_meta( $new_post_id, 'fp_guid', sanitize_text_field( $new_post_args['guid'] ) );
					}

					/**
					 * Handle post meta field mappings
					 */
					foreach ( $post[ 'meta_fields' ] as $field => $meta_value ) {
						update_post_meta( $new_post_id, $field[ 'destination_field' ], $meta_value );
					}

					/**
					 * Handle taxonomy post mappings
					 */
					foreach ( $post[ 'taxonomy_fields' ] as $field => $terms ) {
						$append = apply_filters( 'fp_tax_mapping_append', false, $field, $post[ 'item' ], $source_feed_id );

						$set_terms_result = wp_set_object_terms( $new_post_id, array_map( 'sanitize_text_field', $terms ), $field['destination_field'], $append );

						if ( is_wp_error( $set_terms_result ) ) {
							$this->log( sprintf( __( 'Could not set terms: %s', 'feed-pull' ), $set_terms_result->get_error_message() ), $source_feed_id, 'warning', $new_post_id );
						}
					}

					do_action( 'fp_handled_post', $new_post_id, $source_feed_id );
				}
			}

			// Save last pull into log for source feed
			$this->handle_feed_log( $source_feed_id );

			do_action( 'fp_post_feed_pull', $source_feed_id );
		}

		wp_reset_postdata();
	}
}

/**
 * Get contents of feed file
 *
 * @param $url_or_path
 * @since 0.1.5
 * @return array|string|WP_Error
 */
function fp_fetch_feed( $url_or_path ) {
	if ( ! preg_match( '#^https?://#i', $url_or_path ) ) {
		// if we have an absolute path, we can just use fopen. This is really only for unit testing

		$file_handle = @fopen( $url_or_path, 'r' );

		if ( ! $file_handle ) {
			return new WP_Error( 'fp_bad_feed_path', __( 'Could not read contents of feed path', 'feed-pull' ) );
		}

		$file_contents = '';

		while ( ! feof( $file_handle ) ) {
			$file_contents .= fgets( $file_handle );
		}

		fclose( $file_handle );

		return $file_contents;

	} else {
		$request = wp_remote_get( $url_or_path );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		return wp_remote_retrieve_body( $request );
	}
}