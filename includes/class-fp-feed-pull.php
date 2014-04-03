<?php

class FP_Feed_Pull {

    private static $_instance;

	public $option_defaults = array (
		'pull_interval' => array(
			'sanitizer' => 'absint',
			'default' => 3600,
		),
		'enable_feed_pull' => array(
			'sanitizer' => 'absint',
			'default' => 1,
		)
	);

	/**
	 * Setup the plugin
	 *
	 * @since 0.1.0
	 */
	public function __construct() {

		// Require plugin pieces
		require_once( dirname( __FILE__ ) . '/class-fp-source-feed-cpt.php' );
		require_once( dirname( __FILE__ ) . '/class-fp-pull.php' );
		require_once( dirname( __FILE__ ) . '/class-fp-cron.php' );
		require_once( dirname( __FILE__ ) . '/class-fp-ajax.php' );

		add_action( 'wp_loaded', array( $this, 'action_pull_check' ) );
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'plugins_loaded', array( $this, 'action_plugins_loaded' ) );
    }

	/**
	 * Add options page
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function action_admin_menu() {
		add_submenu_page( 'options-general.php', __( 'Feed Pull Settings', 'feed-pull' ), __( 'Feed Pull Settings', 'feed-pull' ), 'manage_options', 'feed-pull.php', array( $this, 'screen_options' ) );
	}

	/**
	 * Register setting and sanitization callback
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function action_admin_init() {
		register_setting( FP_OPTION_NAME, FP_OPTION_NAME, array( $this, 'sanitize_options' ) );
	}

	/**
	 * Sanitize options
	 *
	 * @param array $option
	 * @since 0.1.0
	 * @return array
	 */
	public function sanitize_options( $option ) {

		$new_option = array();

		foreach ( $this->option_defaults as $option_key => $option_settings ) {
			if ( isset( $option[$option_key] ) ) {
				$new_option[$option_key] = call_user_func( $option_settings['sanitizer'], $option[$option_key] );
			} else {
				$new_option[$option_key] = $option_settings['default'];
			}
		}

		return $new_option;
	}

	/**
	 * Localize plugin
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function action_plugins_loaded() {
		load_plugin_textdomain( 'feed-pull', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Output settings
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function screen_options() {
		$option = fp_get_option();

	?>
		<div class="wrap">
			<h2><?php _e( 'Feed Pull Settings', 'feed-pull' ); ?></h2>

			<form action="options.php" method="post">
				<?php settings_fields( FP_OPTION_NAME ); ?>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="fp_pull_interval"><?php _e( 'Pull feeds on this interval:', 'feed-pull' ); ?></label></th>
							<td>
								<input type="text" value="<?php if ( isset( $option['pull_interval'] ) ) echo absint( $option['pull_interval'] ); ?>" name="<?php echo FP_OPTION_NAME; ?>[pull_interval]" id="fp_pull_interval">
								<?php _e( '(seconds)', 'feed-pull' ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="fp_enable_feed_pull"><?php _e( 'Pull feeds:', 'feed-pull' ); ?></label></th>
							<td>
								<select name="<?php echo FP_OPTION_NAME; ?>[enable_feed_pull]" id="fp_enable_feed_pull">
									<option value="0"><?php _e( 'No', 'feed-pull' ); ?></option>
									<option <?php selected( $option['enable_feed_pull'], 1 ); ?> value="1"><?php _e( 'Yes', 'feed-pull' ); ?></option>
								</select>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
	<?php
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

global $fp_feed_pull;
$fp_feed_pull = FP_Feed_Pull::factory();

/**
 * Get plugin option with defaults
 *
 * @since 0.1.0
 * @return array|mixed|void
 */
function fp_get_option() {
	global $fp_feed_pull;

	$option = get_option( FP_OPTION_NAME, wp_list_pluck( $fp_feed_pull->option_defaults, 'default' ) );
	$option = wp_parse_args( $option, wp_list_pluck( $fp_feed_pull->option_defaults, 'default' ) );
	return $option;
}