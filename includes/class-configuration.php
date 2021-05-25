<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/15/2018
 * Time: 8:52 PM
 */

namespace AFP;

use DirectoryIterator;

class Configuration {

	/*
	 * Default entries for the feedreader options stored as afp_rubriques_feedreader
	 */
	public $feedreader_defaults = array(
		'delivery_path'       => '',
		'import_limit'        => '1',
		'imported_status'     => 'draft',
		'delivery_language'   => 'english',
		'first_paragraph'     => 'false',
		'use_all_images'      => 'false',
		'image_zero_in_body'  => 'false',
		'database_image_zero' => 'false',
	);

	/*
	 * Default entries for the media manager options stored as afp_rubriques_media
	 */
	public $media_defaults = array(
		'purge'               => 'false',
		'archive_period'      => '14',
		'purge_drafts'        => 'false',
		'purge_drafts_period' => '3',
	);

	/**
	 * Default styling classes are used when generating post_content from an xml feed file
	 * Default WP Alignment Classes per WP 5.6
	 *
	 * <block> className : size-large aligncenter
	 * <figure> .wp-block-image .size-large .aligncenter
	 * <img>
	 * <figcaption> Caption text </figcaption>
	 * </figure>
	 * </div>
	 *
	 * @var array
	 *
	 */
	private $default_image_class_list = array(
		'block'      => 'size-large aligncenter',
		'div'        => 'wp-block-image aligncenter',
		'alignment'  => 'aligncenter',
		'figure'     => 'wp-block-image size-large aligncenter',
		'img'        => '',
		'figcaption' => '',
	);

	/*
	 * Delivery languages valid for the current version
	 */
	public $valid_languages = array(
		'english' => 'English',
		'french'  => 'French',
	);

	/*
	* The interval used for the import cron job. It is exposed in the settings page only
	* for information purposes. It's too dangerous to make it fully configurable. WordPress
	* does not implement CRON_LOCK by default, even though it should
	*/
	public $cron_schedule_import_interval = 120;

	/*
	 * Holds plugin options
	 */
	public $settings;

	/*
	 * Holds path and url for static AFP image store
	 */
	public $image_store_path;
	public $image_store_url;

	/*
	 * log writer
	 */
	public $logger;

	/*
	 *
	 */
	public $delivery_path_error = 'The path set for AFP delivery is empty or invalid.
		The feedreader requires a valid delivery path.
		This should be the directory AFP FTP delivery is jailed to,
		relative to the WP_CONTENT directory.';

	/*
	 *
	 */
	public $delivery_path_afp_error = 'The path set for AFP delivery is exists but does not contain a valid directory tree.
				The directory tree is created by AFP.';

	public function __construct() {

		$this->logger = new Logger();
		$this->get_settings();

	}

	/**
	 *
	 */
	public function get_settings() {

		$feed_settings     = get_option( 'afp_rubriques_feedreader', array() );
		$media_settings    = get_option( 'afp_rubriques_media', array() );
		$category_settings = get_option( 'afp_rubriques_categories', array() );

		if ( empty( $feed_settings ) ) {
			$feed_settings = $this->feedreader_defaults;
		}

		if ( empty( $media_settings ) ) {
			$media_settings = $this->media_defaults;
		}

		/*
		 * Set missing defaults
		 */
		foreach ( $this->feedreader_defaults as $key => $value ) {

			if ( ! isset( $feed_settings[ $key ] ) ) {
				$feed_settings[ $key ] = $value;
			}
		}

		foreach ( $this->media_defaults as $key => $value ) {

			if ( ! isset( $media_settings[ $key ] ) ) {
				$feed_settings[ $key ] = $value;
			}
		}

		$settings = array_merge( $feed_settings, $media_settings );

		if ( is_array( $category_settings ) ) {
			$settings = array_merge( $settings, $category_settings );
		}

		/*
		 * Make sure no new AFP categories have to be added to the category map since last time
		 * If there are new categories, add them
		 */
		if ( isset( $settings['category_map'] ) || empty( $settings['category_map'] ) ) {

			$afp_categories = $this->get_afp_categories();

			foreach ( $afp_categories as $afp_category ) {

				if ( ! isset( $settings['category_map'][ $afp_category ] ) ) {
					$settings['category_map'][ $afp_category ] = '';
				}
			}
		}

		$this->settings = $settings;
	}

	/**
	 * Returns an array of afp rubrique category slugs. The slugs are obtained by reading the directory names out of the
	 * delivery directory ( delivery_path/english/shared ).
	 *
	 * @return array
	 */
	protected function get_afp_categories(): array {

		$afp_categories = array();

		$delivery_path = $this->get_delivery_path();

		if ( empty( $delivery_path ) ) {
			return $afp_categories;
		}

		if ( file_exists( $delivery_path ) ) {

			$directories = new DirectoryIterator( $delivery_path );

			foreach ( $directories as $directory ) {
				if ( $directory->isDir() && ! $directory->isDot() ) {
					array_push( $afp_categories, $directory->getFilename() );
				}
			}
		}

		return $afp_categories;
	}


	/**
	 * Builds and sets the full delivery path
	 *
	 * @return string $delivery_path
	 */

	protected function get_delivery_path(): string {

		if ( ! isset( $this->settings['delivery_path'] ) || empty( $this->settings['delivery_path'] ) ) {
			return '';
		}

		//todo this delivery path thing is getting very messy
		/*
		 * Remove slashes before and after because we don't trust anyone to get it right
		 */
		$delivery_path = trim( $this->settings['delivery_path'], '\/' );
		/*
		 * Remove . entries from the path because we don't trust anyone anyway
		 */
		$delivery_path = str_replace( '.', '', $delivery_path );
		/*
		 * Build the last two directories ourselves because we still don't trust anyone
		 */
		$delivery_path = WP_CONTENT_DIR . '/' . $delivery_path . '/' . $this->settings['delivery_language'] . '/shared/';

		return $delivery_path;

	}

	/**
	 * Sets the location of the AFP image store, both the path and the URL.
	 * Should it go into options? Probably not - somebody would break it.
	 */
	public function set_afp_image_store() {

		$now              = gmdate( 'Y/m/d', strtotime( 'now' ) );
		$image_store_path = WP_CONTENT_DIR . '/uploads/afp/' . $now . '/';
		$image_store_url  = WP_CONTENT_URL . '/uploads/afp/' . $now . '/';

		if ( ! file_exists( $image_store_path ) ) {
			wp_mkdir_p( $image_store_path );
		}

		$this->image_store_path = $image_store_path;
		$this->image_store_url  = $image_store_url;

	}

	/**
	 * Applies default class names for image styling to elements left out of
	 * a user's array filter
	 *
	 * @param array $class_list Array of classnames
	 * $key = element name
	 * $value = class name/s as string
	 * div => string,
	 * img => string
	 * figure => string
	 * figcaption => string
	 *
	 * @return array $class_list
	 */
	public function check_image_styling( $class_list = array() ): array {

		if ( is_array( $class_list ) && ! empty( $class_list ) ) {

			foreach ( $this->default_image_class_list as $key => $value ) {

				if ( isset( $class_list[ $key ] ) && ! empty( $class_list[ $key ] ) ) {

					$this->default_image_class_list[ $key ] = $class_list[ $key ];
				}
			}
		}

		return $this->default_image_class_list;

	}

}
