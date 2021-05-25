<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/16/2018
 * Time: 2:39 PM
 */

namespace AFP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Activate_Deactivate {

	public function __construct() {
	}

	public function activate() {

		$configuration = new configuration();

		$feedreader_defaults                    = $configuration->feedreader_defaults;
		$feedreader_defaults['afp_user']        = $this->add_afp_user();
		$feedreader_defaults['import_interval'] = $configuration->cron_schedule_import_interval;

		add_option( 'afp_rubriques_version', ( new afp_rubriques() )->version, '', false );
		add_option( 'afp_rubriques_feedreader', $feedreader_defaults, '', false );
		add_option( 'afp_rubriques_media', $configuration->media_defaults, '', false );

	}

	public function deactivate() {

		delete_option( 'afp_rubriques_version' );
		delete_option( 'afp_rubriques_feedreader' );
		delete_option( 'afp_rubriques_media' );

		wp_clear_scheduled_hook( 'afp_rubriques_import' );
		wp_clear_scheduled_hook( 'afp_rubriques_purge_media' );
		wp_clear_scheduled_hook( 'afp_rubriques_purge_logs' );

	}


	/**
	 * Create the user that imported posts will be attributed to
	 *
	 * @return int id of the user if successful, zero on failure
	 */
	private function add_afp_user(): int {

		$user = get_user_by( 'login', 'Agence France Presse' );

		if ( false !== $user ) {
			return $user->ID;
		}

		$user_data['user_name']    = 'Agence France Presse';
		$user_data['user_login']   = 'Agence France Presse';
		$user_data['user_email']   = 'afpfeedreader@citizen.co.za';
		$user_data['user_pass']    = wp_generate_password( 26, false );
		$user_data['display_name'] = 'Agence France Presse';
		$user_data['description']  = 'User created by AFP Feedreader. Imported stories are assigned to this user.';

		$user_id = wp_insert_user( $user_data );

		if ( ! is_wp_error( $user_id ) ) {
			return $user_id;
		} else {
			return 0;
		}
	}

}
