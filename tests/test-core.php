<?php

class FPTestCore extends WP_UnitTestCase {

	/**
	 * Huge associative array for feed configurations
	 *
	 * @var array
	 */
	private $feeds = array(
		'WP.org' => array(
			'feed_url' => 'http://wordpress.org/news/feed',
			'feed_post_status' => 'publish',
			'posts_xpath' => 'channel/item',
			'feed_post_type' => 'post',
			'allow_updates' => true,
			'categories' => array(),
			'field_map' => array(
				array(
					'source_field' => 'title',
					'destination_field' => 'post_title',
					'mapping_type' => 'post_field',
				),
				array(
					'source_field' => 'guid',
					'destination_field' => 'guid',
					'mapping_type' => 'post_field',
				),
				array(
					'source_field' => 'content:encoded',
					'destination_field' => 'post_content',
					'mapping_type' => 'post_field',
				),
				array(
					'source_field' => 'description',
					'destination_field' => 'excerpt',
					'mapping_type' => 'post_field',
				),
				array(
					'source_field' => 'dc:creator',
					'destination_field' => 'creator',
					'mapping_type' => 'post_meta',
				)
			)
		),
		'broken.com' => array(
			'feed_url' => 'http://broken.com/broken/news/feed',
			'feed_post_status' => 'publish',
			'posts_xpath' => 'channel/item',
			'feed_post_type' => 'post',
			'allow_updates' => true,
			'categories' => array( 1 ),
			'field_map' => array(
				array(
					'source_field' => 'name',
					'destination_field' => 'post_title',
					'mapping_type' => 'post_field',
				),
			)
		),
	);

	/**
	 * Create a simple feed with no config
	 *
	 * @param $title
	 * @return int|WP_Error
	 */
	private function _createSourceFeed( $title ) {

		$args = array(
			'post_type' => 'fp_feed',
			'post_title' => $title,
			'post_status' => 'publish',
			'post_author' => 1,
		);

		$feed_id = wp_insert_post( $args );

		return $feed_id;

	}

	/**
	 * Setup an existing feed
	 *
	 * @param $feed_id
	 * @param $args
	 */
	private function _setupSourceFeed( $feed_id, $args ) {

		update_post_meta( $feed_id, 'fp_feed_url', esc_url_raw( $args['feed_url'] ) );
		update_post_meta( $feed_id, 'fp_post_status', sanitize_text_field( $args['feed_post_status'] ) );
		update_post_meta( $feed_id, 'fp_posts_xpath', sanitize_text_field( $args['posts_xpath'] ) );
		update_post_meta( $feed_id, 'fp_post_type', sanitize_text_field( $args['feed_post_type'] ) );
		update_post_meta( $feed_id, 'fp_allow_updates', absint( $args['allow_updates'] ) );
		update_post_meta( $feed_id, 'fp_new_post_categories', array_map( 'absint', $args['categories'] ) );

		// Dont bother sanitizing field map for this
		update_post_meta( $feed_id, 'fp_field_map', $args['field_map'] );

	}

	/**
	 * Test creating a feed. Pretty much a sanity check
	 */
	public function testCreateSourceFeeds() {

		foreach ( $this->feeds as $feed_title => $feed ) {
			$feed_id = $this->_createSourceFeed( $feed_title );

			$this->assertTrue( ! is_wp_error( $feed_id ) );
		}
	}

	/**
	 * Test pulling live feed from WP.org
	 */
	public function testLiveFeedPull() {
		global $wpdb;

		$feed_id = $this->_createSourceFeed( 'WP.org' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['WP.org'] );

		$first_pull = new FP_Pull();

		// Make sure our pull resulted in no errors or warnings
		$errors = $first_pull->get_log_messages_by_type( $feed_id, 'error' );
		$this->assertTrue( empty( $errors ) );
		$warnings = $first_pull->get_log_messages_by_type( $feed_id, 'warning' );
		$this->assertTrue( empty( $warnings ) );

		// Lets make sure we have posts from our pull
		$posts_num = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status='publish' AND post_type='%s'", $this->feeds['WP.org']['feed_post_type'] ) );

		$this->assertTrue( $posts_num > 0 );

		// Let's pull again, no new posts should be created and we should have no errors or warnings
		$second_pull = new FP_Pull();
		$errors = $second_pull->get_log_messages_by_type( $feed_id, 'error' );
		$this->assertTrue( empty( $errors ) );
		$warnings = $second_pull->get_log_messages_by_type( $feed_id, 'warning' );
		$this->assertTrue( empty( $warnings ) );

		$second_pull_posts_num = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_status='publish' AND post_type='%s'", $this->feeds['WP.org']['feed_post_type'] ) );
		$this->assertEquals( $posts_num, $second_pull_posts_num );

		// Make sure feed has a "Last Pull Time" and a pull log
		$last_pull_time = get_post_meta( $feed_id, 'fp_last_pull_time', true);
		$last_pull_log = get_post_meta( $feed_id, 'fp_last_pull_log', true );
		$this->assertTrue( ! empty( $last_pull_time ) );
		$this->assertTrue( ! empty( $last_pull_log ) );

	}

	/**
	 *  Test that nothing happens when pulling is off
	 */
	public function testDisabledPull() {
		$feed_id = $this->_createSourceFeed( 'WP.org' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['WP.org'] );

		$feed_id = $this->_createSourceFeed( 'broken.com' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['broken.com'] );

		// Disable feed pulls
		$option = fp_get_option();
		$option['enable_feed_pull'] = 0;
		update_option( FP_OPTION_NAME, $option );

		$pull = new FP_Pull();
		$pull_log = $pull->get_log();

		// Log should be completely empty
		$this->assertEmpty( $pull_log );
	}
}

