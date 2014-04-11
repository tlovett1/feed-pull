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

			$formatted_log = array();
			$log = get_post_meta( $source_feed_id, 'fp_last_pull_log', true );

			foreach ( $log as $log_item ) {
				$formatted_log_item = array(
					'type' => esc_html( $log_item['type'] ),
					'pretty_type' => ucwords( esc_html( $log_item['type'] ) ),
					'message' => esc_html( $log_item['message'] ),
				);

				if ( ! empty( $log_item['post_id'] ) ) {
					$formatted_log_item['post_id'] = (int) $log_item['post_id'];
					$formatted_log_item['edit_post_link'] = get_edit_post_link( $log_item['post_id'] );
				}

				$formatted_log[] = $formatted_log_item;
			}

			$output['log'] = $formatted_log;
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
