<?php
/*
Plugin name: AFP Rubriques
Plugin URI: https://solutiondesign.co.za/afpfeedreader/plugin-readme/
Description: Imports Agence France Presse rubriques. Go to plugin site for instructions.
Author: Nic Wilson
Version: 1.1
Author URI: https://www.linkedin.com/in/nic-wilson1/
*/

/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/3/2018
 * Time: 4:07 PM
 */

namespace AFP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . '/includes/class-configuration.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-feed-taxonomy.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-feed-reader.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-file-reader.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-media-manager.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-logger.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/class-cron-jobs.php';

if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . '/admin/class-feed-setup.php';
	require_once plugin_dir_path( __FILE__ ) . '/includes/class-activate-deactivate.php';
}


class AFP_Rubriques {

	/*
	 * Version is stored in options table as afp_version and checked on load
	 * There is an update function stub for use should you need it
	 *
	 */
	public $version = '1.1';

	protected static $instance = null;

	/**
	 * @return AFP_Rubriques|null
	 */
	public static function init(): ?AFP_Rubriques {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		$version = get_option( 'afp_rubriques_version', false );

		if ( $version !== $this->version ) {

			self::afp_updated();
		}

		add_action( 'wp_loaded', array( 'AFP\feed_taxonomy', 'init' ) );
		add_action( 'wp_loaded', array( 'AFP\cron_jobs', 'init' ) );
		add_action( 'admin_menu', array( 'AFP\feed_setup', 'init' ) );

	}

	/**
	 * On plugin activation, the options are added and the AFP user is created (if it is missing)
	 */
	public static function afp_activated() {

		$activate = new Activate_Deactivate();
		$activate->activate();

	}

	/**
	 * On plugin deactivation we remove all the options and the cron job
	 * We leave the user behind.
	 */
	public static function afp_deactivated() {

		$deactivate = new Activate_Deactivate();
		$deactivate->deactivate();

	}


	/**
	 * Stub method for updating the plugin
	 */
	public static function afp_updated() {

		/*
		 * For updates
		 */

	}

}

add_action( 'init', array( 'AFP\afp_rubriques', 'init' ) );
register_activation_hook( __FILE__, array( 'AFP\afp_rubriques', 'afp_activated' ) );
register_deactivation_hook( __FILE__, array( 'AFP\afp_rubriques', 'afp_deactivated' ) );
