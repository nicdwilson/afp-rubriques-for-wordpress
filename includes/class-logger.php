<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/10/2018
 * Time: 11:32 AM
 */

namespace AFP;

/*
 * If this file is called directly, abort.
 */
if ( ! defined( 'WPINC' ) ) {
	die;
}

use DateTime;
use DirectoryIterator;
use Exception;

class Logger {

	public $log_path;
	private $log_file;
	private $message_prefix;

	public function __construct() {

		$this->log_path       = WP_CONTENT_DIR . '/uploads/logs/afp_imports/';
		$this->log_file       = gmdate( 'Y_m_d', strtotime( 'now' ) );
		$this->message_prefix = "\n" . gmdate( 'Y-m-d h:i:s' ) . ' :: ';
	}

	/**
	 * Write new string to log file
	 *
	 * @param string $message
	 */
	public function write_log( $message = '' ) {

		$message = stripslashes( $message );
		$message = esc_html( $message );

		if ( ! file_exists( $this->log_path ) ) {
			wp_mkdir_p( $this->log_path );
		}

		if ( is_array( $message ) ) {
			$message = wp_json_encode( $message );
		}

		error_log( $this->message_prefix . $message, 3, $this->log_path . $this->log_file . '.log' );
	}


	/**
	 * Import logs maintenance - if we don't clean up after ourselves nobody will,
	 * so we keep 21 days only.
	 *
	 * @return bool
	 */
	public function run_logfile_purge(): bool {

		$files = glob( WP_CONTENT_DIR . '/uploads/logs/afp_imports/', '*.log' );
		$i     = 0;

		/*
		 * We keep 21 logs, so we can bail if there are fewer files
		 */
		if ( count( $files ) < 21 ) {
			return false;
		}

		$now      = gmdate( 'U', strtotime( 'now' ) );
		$date_now = new DateTime();
		$date_now->setTimestamp( $now );

		try {

			$now = time();

			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					if ( $now - filemtime( $file ) >= 60 * 60 * 24 * 22 ) {
						unlink( $file );
						$this->write_log( 'Log file deleted at ' . $file );
					}
				}
			}
		} catch ( Exception $ex ) {
			$this->write_log( $ex->getMessage() );
		}

		$this->write_log( $i . ' older logfiles deleted' );

		return true;
	}

	/**
	 * Returns an array containing data for all import log files.
	 * 'title' => file name,
	 * 'link'  => file url,
	 * 'mtime' => last modified date,
	 * 'path'  => path to log files
	 *
	 * @return array
	 */
	public function get_logs(): array {

		$logs = array();

		if ( ! file_exists( $this->log_path ) ) {
			wp_mkdir_p( $this->log_path );
		}

		if ( file_exists( $this->log_path ) ) {

			$files = new DirectoryIterator( $this->log_path );

			foreach ( $files as $file ) {

				if ( ! $file->isDir() && ! $file->isDot() && $file->getExtension() === 'log' ) {

					$date = str_replace( '_', '-', $file->getFilename() );
					$date = str_replace( '.log', '', $date );

					array_push(
						$logs,
						array(
							'title' => $file->getFilename(),
							'link'  => WP_CONTENT_URL . '/uploads/logs/afp_imports/' . $file->getFilename(),
							'mtime' => $date,
							'path'  => $this->log_path,
						)
					);
				}
			}
		}

		return $logs;
	}
}
