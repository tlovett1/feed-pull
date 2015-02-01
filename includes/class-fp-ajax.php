<?php

/**
 * Register AJAX actions
 *
 */
class FP_AJAX {
	private static $_instance;

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		add_action( 'wp_ajax_pull', array( $this, 'action_pull' ) );
		add_action( 'wp_ajax_pull_test', array( $this, 'action_pull_test' ) );
		add_action( 'wp_ajax_reset_deleted_posts', array( $this, 'action_reset_deleted_posts' ) );
		add_action( 'wp_ajax_get_namespaces', array( $this, 'action_get_namespaces' ) );
	}

	/**
	 * Get document level namespaces
	 *
	 * @since 0.1.5
	 * @return void
	 */
	public function action_get_namespaces() {
		$output = array();
		$output['success'] = false;

		if ( ! empty( $_POST['feed_url'] ) && check_ajax_referer( 'fp_get_namespaces_nonce', 'nonce', false ) ) {
			$raw_feed_contents = fp_fetch_feed( $_POST['feed_url'] );

			if ( ! is_wp_error( $raw_feed_contents ) ) {
				$feed = simplexml_load_string( $raw_feed_contents );

				$namespaces = $feed->getDocNamespaces();

				$output['namespaces'] = $namespaces;
				$output['success'] = true;
			}
		}

		wp_send_json( $output );
	}

	/**
	 * Do a feed pull
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function action_pull() {
		$output = array();
		$output['message'] = __( 'Invalid AJAX Request, try again.', '' );
		$output['success'] = false;

		if ( check_ajax_referer( 'fp_pull_nonce', 'nonce', false ) ) {
			$source_feed_id = null;

			if ( isset( $_POST['source_feed_id'] ) ) {
				$source_feed_id = (int) $_POST['source_feed_id'];
			}

			new FP_Pull( $source_feed_id );

			$feed_cpt = FP_Source_Feed_CPT::factory();

			$source_feed = get_post( $source_feed_id );

			ob_start();

			$feed_cpt->meta_box_log( $source_feed );

			$output['message'] = ob_get_clean();

			$output['success'] = true;
		}

		wp_send_json( $output );
	}

	/**
	 * Do a feed pull test
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function action_pull_test() {
		$output = array();
		$output['success'] = false;

		if ( check_ajax_referer( 'fp_pull_nonce', 'nonce', false ) ) {
			$source_feed_id = null;
			if ( isset( $_POST['source_feed_id'] ) ) {
				$source_feed_id = (int) $_POST['source_feed_id'];
			}

			$feedpull = new FP_Pull( false );

			$posts = $feedpull->get_feed_posts( $source_feed_id );

			// Error
			if ( false === $posts ) {
				$feed_cpt = FP_Source_Feed_CPT::factory();

				$source_feed = get_post( $source_feed_id );

				ob_start();

				$feed_cpt->meta_box_log( $source_feed );

				$output['message'] = ob_get_clean();

				$output['success'] = false;
			}
			else {
				// Get first post
				$content = current( $posts );

				// Convert SimpleXMLElement to object
				$content = get_object_vars( $content );

				$output['message'] = print_r( $content, true );

				$output['success'] = true;
			}
		}

		wp_send_json( $output );
	}

	/**
	 * Reset deleted posts
	 *
	 * @since 0.1.7
	 * @return void
	 */
	public function action_reset_deleted_posts() {
		$output = array();
		$output['success'] = false;

		if ( check_ajax_referer( 'fp_reset_deleted_posts_nonce', 'nonce', false ) ) {
			delete_option( FP_DELETED_OPTION_NAME );
			$output['success'] = true;
		}

		wp_send_json( $output );
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist
	 *
	 * @since 0.1.0
	 * @return object
	 */
	public static function factory() {
		if ( ! self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
}

FP_AJAX::factory();
