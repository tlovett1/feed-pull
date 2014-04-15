<?php

class FPTestCore extends WP_UnitTestCase {

	/**
	 * Huge associative array for feed configurations
	 *
	 * @var array
	 * @since 0.1.5
	 */
	private $feeds = array(
		'WP.org' => array(
			'feed_url' => 'http://wordpress.org/news/feed',
			'feed_post_status' => 'publish',
			'posts_xpath' => 'channel/item',
			'feed_post_type' => 'post',
			'smart_author_mapping' => 0,
			'allow_updates' => 1,
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
					'source_field' => 'pubDate',
					'destination_field' => 'post_date',
					'mapping_type' => 'post_field',
				)
			)
		),
		'broken.com' => array(
			'feed_url' => 'http://broken.com/broken/news/feed',
			'feed_post_status' => 'publish',
			'posts_xpath' => 'channel/item',
			'feed_post_type' => 'post',
			'smart_author_mapping' => 0,
			'allow_updates' => 1,
			'categories' => array( 1 ),
			'field_map' => array(
				array(
					'source_field' => 'name',
					'destination_field' => 'post_title',
					'mapping_type' => 'post_field',
				),
			)
		),
		'qz.xml' => array(
			'feed_url' => 'tests/xml/qz.xml',
			'feed_post_status' => 'publish',
			'posts_xpath' => 'channel/item',
			'feed_post_type' => 'post',
			'allow_updates' => 1,
			'smart_author_mapping' => 0,
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
					'source_field' => 'pubDate',
					'destination_field' => 'post_date',
					'mapping_type' => 'post_field',
				),
				array(
					'source_field' => 'dc:creator',
					'destination_field' => 'post_author',
					'mapping_type' => 'post_field',
				),
				array(
					'source_field' => 'link',
					'destination_field' => 'old_link',
					'mapping_type' => 'post_meta',
				)
			)
		),
	);

	/**
	 * Create a simple feed with no config
	 *
	 * @param $title
	 * @since 0.1.5
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
	 * @param int $feed_id
	 * @param array $args
	 * @param array $overwrites
	 * @since 0.1.5
	 */
	private function _setupSourceFeed( $feed_id, $args, $overwrites = array() ) {

		$args = wp_parse_args( $overwrites, $args );

		update_post_meta( $feed_id, 'fp_feed_url', sanitize_text_field( $args['feed_url'] ) );
		update_post_meta( $feed_id, 'fp_post_status', sanitize_text_field( $args['feed_post_status'] ) );
		update_post_meta( $feed_id, 'fp_posts_xpath', sanitize_text_field( $args['posts_xpath'] ) );
		update_post_meta( $feed_id, 'fp_post_type', sanitize_text_field( $args['feed_post_type'] ) );
		update_post_meta( $feed_id, 'fp_allow_updates', absint( $args['allow_updates'] ) );
		update_post_meta( $feed_id, 'fp_smart_author_mapping', absint( $args['smart_author_mapping'] ) );
		update_post_meta( $feed_id, 'fp_new_post_categories', array_map( 'absint', $args['categories'] ) );

		// Dont bother sanitizing field map for this
		update_post_meta( $feed_id, 'fp_field_map', $args['field_map'] );

	}

	/**
	 * Test creating a feed. Pretty much a sanity check
	 *
	 * @since 0.1.5
	 */
	public function testCreateSourceFeeds() {

		foreach ( $this->feeds as $feed_title => $feed ) {
			$feed_id = $this->_createSourceFeed( $feed_title );

			$this->assertTrue( ! is_wp_error( $feed_id ) );
		}
	}

	/**
	 * Test mapping something to post meta
	 *
	 * @since 0.1.5
	 */
	public function testPostMeta() {
		$feed_id = $this->_createSourceFeed( 'qz.xml' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['qz.xml'] );

		$first_pull = new FP_Pull();

		// Make sure our pull resulted in no errors or warnings
		$errors = $first_pull->get_log_messages_by_type( $feed_id, 'error' );
		$this->assertTrue( empty( $errors ) );
		$warnings = $first_pull->get_log_messages_by_type( $feed_id, 'warning' );
		$this->assertTrue( empty( $warnings ) );

		// Grab some posts
		$args = array(
			'post_type' => 'post',
			'posts_per_page' => 50,
			'no_found_rows' => true,
			'cache_results' => false,
			'meta_key' => 'fp_syndicated_post',
			'meta_value' => 1,
		);

		$query = new WP_Query( $args );

		$this->assertTrue( $query->have_posts() );

		$meta_found = 0;
		foreach ( $query->posts as $post ) {
			$old_link = get_post_meta( $post->ID, 'old_link', true );

			if ( ! empty( $old_link ) ) {
				$meta_found++;
			}
		}

		$this->assertEquals( $meta_found, 12 );
	}

	/**
	 * Make sure authors map correctly without smart mapping
	 *
	 * @since 0.1.5
	 */
	public function testAuthorMappingNotSmart() {
		$feed_id = $this->_createSourceFeed( 'qz.xml' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['qz.xml'] );

		$first_pull = new FP_Pull();

		// Make sure our pull resulted in no errors or warnings
		$errors = $first_pull->get_log_messages_by_type( $feed_id, 'error' );
		$this->assertTrue( empty( $errors ) );
		$warnings = $first_pull->get_log_messages_by_type( $feed_id, 'warning' );
		$this->assertTrue( empty( $warnings ) );

		// There is one post with author ID 2, but since that user does not exist,
		// it should map to author 1
		$args = array(
			'post_type' => 'post',
			'posts_per_page' => 50,
			'no_found_rows' => true,
			'cache_results' => false,
			'meta_key' => 'fp_syndicated_post',
			'meta_value' => 1,
			'author' => 1,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( count( $query->posts ), 4 );
	}

	/**
	 * Make sure authors map correctly with smart mapping
	 *
	 * @since 0.1.5
	 */
	public function testAuthorMappingSmart() {
		wp_create_user( 'testuser', 'sdf$erdf13', 'testuser@testuser.com' );


		$feed_id = $this->_createSourceFeed( 'qz.xml' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['qz.xml'], array( 'smart_author_mapping' => 1 ) );

		$first_pull = new FP_Pull();

		// Make sure our pull resulted in no errors or warnings
		$errors = $first_pull->get_log_messages_by_type( $feed_id, 'error' );
		$this->assertTrue( empty( $errors ) );
		$warnings = $first_pull->get_log_messages_by_type( $feed_id, 'warning' );
		$this->assertTrue( empty( $warnings ) );

		$args = array(
			'post_type' => 'post',
			'posts_per_page' => 50,
			'no_found_rows' => true,
			'cache_results' => false,
			'meta_key' => 'fp_syndicated_post',
			'meta_value' => 1,
			'author' => 1,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( count( $query->posts ), 4 );

		$args = array(
			'post_type' => 'post',
			'posts_per_page' => 50,
			'no_found_rows' => true,
			'cache_results' => false,
			'meta_key' => 'fp_syndicated_post',
			'meta_value' => 1,
			'author' => 2,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( count( $query->posts ), 2 );
	}

	/**
	 * Test a broken feed
	 *
	 * @since 0.1.5
	 */
	public function testBrokenFeedPull() {
		$feed_id = $this->_createSourceFeed( 'broken.com' );
		$this->_setupSourceFeed( $feed_id, $this->feeds['broken.com'] );

		$first_pull = new FP_Pull();

		// Make sure our pull resulted in no errors or warnings
		$errors = $first_pull->get_log_messages_by_type( $feed_id, 'error' );
		$this->assertTrue( ! empty( $errors ) );
	}

	/**
	 * Test pulling live feed from WP.org
	 *
	 * @since 0.1.5
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
	 *
	 * @since 0.1.5
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

