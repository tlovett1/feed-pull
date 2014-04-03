<?php

/**
 * Setup cron jobs for plugin
 */

class FP_Cron {

	private static $_instance;

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.0
	 * @uses add_action, add_filter
	 */
	private function __construct() {
		add_action( 'fp_feed_pull', array( $this, 'pull' ) );
		add_action( 'init', array( $this, 'schedule_events' ) );
		add_filter( 'cron_schedules', array( $this, 'filter_cron_schedules' ) );
	}

	/**
	 * Add custom cron schedule
	 *
	 * @param array $schedules
	 * @since 0.1.0
	 * @return array
	 */
	public function filter_cron_schedules( $schedules ) {
		$option = fp_get_option();

		$schedules['feed_pull'] = array(
			'interval' => (int) $option['pull_interval'],
			'display' => __( 'Custom Feed Pull Interval', 'feed-pull' ),
		);
		return $schedules;
	}

	/**
	 * Setup cron jobs
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function schedule_events() {
		$timestamp = wp_next_scheduled( 'fp_feed_pull' );

		if ( ! $timestamp ) {
			wp_schedule_event( time(), 'feed_pull', 'fp_feed_pull' );
		}
	}

	/**
	 * Initiate a feed pull
	 *
	 * @since 0.1.0
	 */
	public function pull() {
		new FP_Pull();
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

FP_Cron::factory();
