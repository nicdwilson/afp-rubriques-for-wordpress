<?php

/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/9/2018
 * Time: 1:35 PM
 */

namespace AFP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Cron_Jobs extends Configuration {

	protected static $instance = null;

	public static function init(): ?Cron_Jobs {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();
		$this->add_cron_jobs();
	}

	public function add_cron_jobs() {

		add_filter( 'cron_schedules', array( $this, 'add_import_schedules' ) );

		if ( ! wp_next_scheduled( 'afp_rubriques_import' ) ) {
			wp_schedule_event( strtotime( 'now' ), 'afp_rubriques_import_schedule', 'afp_rubriques_import' );
		}

		$feed_reader = new feed_reader();
		add_action( 'afp_rubriques_import', array( $feed_reader, 'run_import' ) );

		if ( ! wp_next_scheduled( 'afp_rubriques_purge_media' ) ) {
			wp_schedule_event( strtotime( 'now' ), 'hourly', 'afp_rubriques_purge_media' );
		}

		$media_manager = new media_manager();
		add_action( 'afp_rubriques_purge_media', array( $media_manager, 'run_purge' ) );

		if ( ! wp_next_scheduled( 'afp_rubriques_purge_logs' ) ) {
			wp_schedule_event( strtotime( 'now' ), 'daily', 'afp_rubriques_purge_logs' );
		}

		/**
		 * If selected, set up the draft purge
		 */
		if ( 'true' === $this->settings['purge_drafts'] ) {

			add_action( 'afp_rubriques_purge_drafts', array( $this, 'purge_drafts' ) );

			if ( ! wp_next_scheduled( 'afp_rubriques_purge_drafts' ) ) {
				wp_schedule_event( strtotime( 'now' ), 'hourly', 'afp_rubriques_purge_drafts' );
			}
		}

		add_action( 'afp_rubriques_purge_logs', array( $this->logger, 'run_logfile_purge' ) );

	}


	/**
	 * Adds the import schedule to cron schedules via cron schedule filter
	 * Interval defined by the $cron_schedule_import_interval variable.
	 *
	 * @return mixed
	 */
	public function add_import_schedules(): array {

		$schedules['afp_rubriques_import_schedule'] = array(
			'interval' => $this->cron_schedule_import_interval,
			'display'  => 'Every two minutes',
		);

		return $schedules;
	}


	/**
	 *
	 * Cron action to remove unpublished draft posts older than the required setting
	 *
	 * @return void
	 */
	public function purge_drafts() {

		$purge_date = new \DateTime();
		$purge_date->modify( '-' . $this->settings['purge_drafts_period'] . ' day' );

		$this->logger->write_log( 'Deleting AFP articles left in draft for longer than ' . $this->settings['purge_drafts_period'] . ' days.' );

		$args = array(
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'is_afp',
					'value' => 1
				),
			),
			'date_query'     => array(
				array(
					'before'    => array(
						'year'  => $purge_date->format( 'Y' ),
						'month' => $purge_date->format( 'm' ),
						'day'   => $purge_date->format( 'd' ),
						'hour'  => $purge_date->format( 'h' ),
					),
					'inclusive' => true,
				),
			),
			'posts_per_page' => 20,
		);

		$post_ids = get_posts( $args );

		if ( ! empty( $post_ids ) ) {
			$i = 0;
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id, false );
				$i ++;
			}
			$this->logger->write_log( 'Moved ' . $i . ' AFP articles from draft into bin.' );
		} else {
			$this->logger->write_log( 'No AFP draft articles found to delete.' );
		}

	}

}
