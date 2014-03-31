<?php

class FP_Feed_Pull {

    private static $_instance;

	/**
	 * Setup the plugin
	 */
	public function __construct() {
		require_once( dirname( __FILE__ ) . '/class-fp-source-feed-cpt.php' );
		require_once( dirname( __FILE__ ) . '/class-fp-pull.php' );

		add_action( 'wp_loaded', array( $this, 'action_pull_check' ) );
    }

	public function action_pull_check() {
		if ( ! isset( $_GET['test_pull'] ) )
			return;

		new FP_Pull();
	}

    /**
     * Return an instance of the current class, create one if it doesn't exist
     *
     * @return object
     */
    public static function factory() {
        if ( ! self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
}

FP_Feed_Pull::factory();