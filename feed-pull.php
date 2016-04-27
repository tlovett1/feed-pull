<?php
/**
 * Plugin Name: Feed Pull
 * Plugin URI: http://www.taylorlovett.com
 * Description: Automatically turn feed content into posts.
 * Author: Taylor Lovett, 10up
 * Version: 0.2.6
 * Author URI: http://www.taylorlovett.com
 */

/**
 * Define some plugin constants
 */
define( 'FP_OPTION_NAME', 'fp_feed_pull' );
define( 'FP_DELETED_OPTION_NAME', 'fp_deleted_syndicated' );

require_once( dirname( __FILE__ ) . '/includes/class-fp-feed-pull.php' );
