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
	}

	/**
	 * Do a feed pull
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function action_pull() {
		$output = array();
		$output['success'] = false;

		if ( check_ajax_referer( 'fp_pull_nonce', 'nonce', false ) ) {
			$source_feed_id = null;
			if ( isset( $_POST['source_feed_id'] ) ) {
				$source_feed_id = (int) $_POST['source_feed_id'];
			}

			new FP_Pull( $source_feed_id );
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
