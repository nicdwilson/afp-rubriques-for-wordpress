<?php


/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 4/7/2018
 * Time: 10:49
 */

namespace AFP;

// If this file is called directly, abort.
use SimpleXMLElement;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Feed_Reader extends Configuration {

	private $import_limit         = 1;
	private $images               = array();
	private $post_metadata        = array();
	private $current_afp_category = '';
	private $current_category_ids = array();

	private $delivery_url;
	private $delivery_path;
	private $lock_path;

	public function __construct() {
		parent::__construct();
		$this->set_afp_image_store();
		$this->lock_path = WP_CONTENT_DIR . '/uploads/logs/afp_imports/cron.lock';
	}

	/**
	 * Run the import.
	 */
	public function run_import() {

		$this->logger->write_log( 'Start import' );

		/*
		* Check settings are in place
		*/
		if ( false === $this->settings ) {
			$this->logger->write_log( 'AFP Feedreader configuration needs to be completed.' );
			exit();
		}

		/*
		 * Obtain a lock on the cron job
		 */
		// phpcs:ignore
		$lock = fopen( $this->lock_path, 'c' ); //WPCS WP_Filesystem offers no way of opening file handles.

		if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			$this->logger->write_log( 'Import could not obtain a lock.' );
			exit();
		}

		/*
		 * Set the limit to the number of files to import
		 */
		$this->set_import_limit();

		/*
		 * Get the AFP XML file names
		 */
		$files = $this->get_afp_file_names();

		/*
		 * Process the files
		 */
		$result = $this->import_files( $files );

		/*
		 * Log the result
		 */
		if ( false === $result ) {
			$this->logger->write_log( 'No files were imported.' );
		} else {
			$this->logger->write_log( 'End import' );
		}

		/*
		 * Close the lock
		 */
		// phpcs:ignore
		fclose( $lock );

		exit();

	}

	/**
	 * Set the limit to the number of files er category which may be imported at any one time
	 *
	 * @void
	 */
	private function set_import_limit() {

		if ( isset( $this->settings['import_limit'] ) && ! empty( $this->settings['import_limit'] ) ) {
			$this->import_limit = $this->settings['import_limit'];
		}
	}

	/**
	 * Get the status an imported story needs to be set to
	 *
	 * @return string
	 */
	private function get_publish_status(): string {

		if ( ! empty( $this->current_afp_category ) ) {
			if ( 'true' === $this->settings['category_status'][ $this->current_afp_category ] ) {
				return 'publish';
			}
		}

		if ( isset( $this->settings['imported_status'] ) && ! empty( $this->settings['imported_status'] ) ) {
			return (string) $this->settings['imported_status'];
		} else {
			return 'draft';
		}
	}

	/**
	 * Returns multidimensional array filenames and filemtime per afp category
	 * filemtime may be used at a future date to handle updates to existing posts.
	 *
	 * @return array
	 */
	private function get_afp_file_names(): array {

		$category_map = array();

		if ( isset( $this->settings['category_map'] ) ) {
			$category_map = $this->settings['category_map'];
		}

		$files = array();

		foreach ( $category_map as $category => $value ) {

			if ( empty( $value ) || absint( $value ) < 2 ) {
				continue;
			}

			$result = $this->set_delivery_path( $category );

			if ( false === $result ) {
				continue;
			}

			$xml = $this->get_xml( 'index.xml' );

			if ( empty( $xml ) ) {
				continue;
			}

			$i = 0;

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			foreach ( $xml->NewsItem->NewsComponent->NewsComponent as $child ) {

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$file_name = (string) $child->NewsItemRef->attributes()->NewsItem;

				if ( file_exists( $this->delivery_path . $file_name ) ) {
					$modified_time = filemtime( $this->delivery_path . $file_name );
				} else {
					continue;
				}

				$files[ $category ][ $i ]['file']     = $file_name;
				$files[ $category ][ $i ]['modified'] = $modified_time;

				$i ++;
			}
		}

		return $files;
	}

	/**
	 * Process files requiring update
	 *
	 * @param array $files Array containing file names.
	 *
	 * @return bool
	 */
	private function import_files( $files = array() ): bool {

		if ( empty( $files ) ) {
			return false;
		}

		$import_limit = ( isset( $this->settings['import_limit'] ) ) ? $this->settings['import_limit'] : 1;

		foreach ( $files as $category => $file_data ) {

			$this->set_delivery_path( $category );
			$this->set_category_data( $category );

			$i     = 0;
			$n     = 0;
			$count = count( $file_data );

			while ( $i < $count && $n < $import_limit ) {

				$post_exists = $this->does_post_exist( $file_data[ $i ]['file'] );

				$post_data = $this->prepare_post_data( $file_data[ $i ]['file'] );

				if ( 0 === $post_exists && ! empty( $post_data ) ) {

					/*
					* Allow plugin users to manipulate post data
					*/
					$post_data = apply_filters( 'afp_rubriques_pre_post_import', $post_data );

					$post_id = wp_insert_post( $post_data );

					if ( ! is_wp_error( $post_id ) ) {

						$this->insert_post_metadata( $post_id );
						$this->set_post_category( $post_id );
						$this->process_images( $post_id );

						/*
						* Allow plugin users to do something with the post ID
						*/
						do_action( 'afp_rubriques_post_imported', $post_id );

						$n ++;

					} else {
						$this->logger->write_log( $post_id->get_error_message() );
					}
				}

				$i ++;
			}

			$message = 'Category: ' . $category . '. Processed: ' . $i . ' articles. Imported: ' . $n . ' articles';
			$this->logger->write_log( $message );
		}

		return true;
	}

	/**
	 * Sets site category ids for this specific AFP category
	 *
	 * @param $category
	 */
	private function set_category_data( $category ) {

		$category_map = $this->settings['category_map'];

		if ( ! empty( $category_map[ $category ] ) ) {

			$this->current_afp_category = $category;
			$this->current_category_ids = $category_map[ $category ];
		} else {

			$this->current_afp_category = 0;
			$this->current_category_ids = 0;
		}

	}

	/**
	 * Sets the post category. Returns false on empty post id or failure
	 *
	 * @param $post_id
	 *
	 * @return array|bool Array of affected term ids | false on failure
	 */
	private function set_post_category( $post_id ) {

		if ( empty( $post_id ) ) {
			return false;
		}

		$result = wp_set_post_terms( $post_id, array( absint( $this->current_category_ids ) ), 'category', false );
		/*
		 * The following is required to prevent some weirdness on The Citizen which saw some posts being assigned
		 * tags at random on import. This may be due to incomplete upgrade routines run for 4.2 and term meta which
		 * caused corruption of certain large databases. Or it might be I missed something...
		 */
		wp_set_object_terms( $post_id, array( 0 ), 'post_tag', false );
		wp_set_object_terms( $post_id, array( $this->current_afp_category ), 'rubrique', false );

		return $result;

	}

	/**
	 * Inserts the previously readied post meta. Post meta is handled separately to avoid duplication
	 *
	 * @param $post_id
	 *
	 * @return bool
	 */
	private function insert_post_metadata( $post_id ): bool {

		if ( empty( $post_id ) ) {
			return false;
		}

		foreach ( $this->post_metadata as $meta_key => $meta_value ) {

			update_post_meta( $post_id, $meta_key, $meta_value );
		}

		return true;
	}


	/**
	 * Prepares the post_data array for insertion or update. Sets the meta_data readied for insertion or update
	 *
	 * @param string Filename of the xml file containing the data to be used to prepare the post_data array
	 *
	 * @return array Array of post data
	 */
	private function prepare_post_data( $file ): array {

		if ( empty( $file ) ) {
			return array();
		}

		$xml = $this->get_xml( $file );

		if ( ! is_object( $xml ) ) {
			return array();
		}

		$author_id   = $this->settings['afp_user'];
		$post_status = $this->get_publish_status();

		$xml_file_reader = new file_reader();

		$modified_date  = $xml_file_reader->get_the_modified_date( $xml );
		$published_date = $xml_file_reader->get_the_published_date( $xml );
		$title          = $xml_file_reader->get_the_title( $xml );
		$excerpt        = $xml_file_reader->get_the_excerpt( $xml );
		$byline         = $xml_file_reader->get_the_byline( $xml );

		$content      = $xml_file_reader->get_the_content( $xml, $this->image_store_url );
		$post_content = $content['content'];
		$this->images = $content['images'];

		$post_data = array();

		$post_data['post_author']   = $author_id;
		$post_data['post_date']     = $published_date;
		$post_data['post_modified'] = $modified_date;
		$post_data['post_content']  = $post_content;
		$post_data['post_title']    = $title;
		$post_data['post_excerpt']  = $excerpt;
		$post_data['post_status']   = $post_status;

		$this->post_metadata['afp_slug']     = $file;
		$this->post_metadata['afp_category'] = $this->current_afp_category;
		$this->post_metadata['is_afp']       = 1;

		if ( ! empty( $byline ) ) {
			$this->post_metadata['cxt_article_byline'] = $byline;
		}

		return $post_data;
	}

	/**
	 * Processes images. Sets first image to featured image with DB insert, loads the
	 * rest into the storage path as defined in the set_storage_path function
	 *
	 * @param int $post_id
	 * @param array $images
	 * @param bool $return_id ;
	 *
	 * @return bool
	 */
	public function process_images( $post_id = 0, $images = array(), $return_id = false ): bool {

		if ( empty( $images ) ) {
			$images = $this->images;
		}

		if ( empty( $images ) ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$i = 0;

		foreach ( $images as $image ) {

			$image_data['post_excerpt'] = $image['caption'];
			$url                        = $this->delivery_url . $image['file'];
			$file_info                  = wp_check_filetype( basename( $url ) );

			if ( 'jpg' !== $file_info['ext'] || 'image/jpeg' !== $file_info['type'] ) {
				continue;
			}

			/**
			 * If this is image zero, it's the featured image, so database it and set it to featured
			 */
			if ( 0 === $i && 'true' === $this->settings['database_image_zero'] ) {

				$image_id = $this->does_image_exist( $image['file'] );

				if ( 0 === $image_id ) {

					$image_id = media_sideload_image( $url, null, $image['caption'], 'id' );

					if ( is_wp_error( $image_id ) ) {

						$this->logger->write_log( $image_id->get_error_message() );
						$i ++;
						continue;
					}
				}

				if ( $post_id > 0 && 'true' === $this->settings['database_image_zero'] ) {
					set_post_thumbnail( $post_id, $image_id );
				}

				$caption_data = array(
					'ID'           => $image_id,
					'post_excerpt' => $image['caption'],
				);

				wp_update_post( $caption_data );
				update_post_meta( $image_id, 'is_afp', 1 );
				update_post_meta( $image_id, 'afp_file', $image['file'] );

			}

			$result = copy( $this->delivery_path . $image['file'], $this->image_store_path . $image['file'] );

			if ( false === $result ) {
				$this->logger->write_log( 'Image ' . $image['file'] . ' failed to copy to image store.' );
			}

			if ( true === $return_id && ! empty( $image_id ) ) {
				return $image_id;
			}

			if ( true === $return_id && empty( $image_id ) ) {
				return false;
			}

			$i ++;

		}

		$this->logger->write_log( 'Imported ' . $i . ' images.' );

		return true;
	}

	/**
	 * Sets directory path and URL where you will find the files arriving from AFP.
	 * Contents are managed by AFP and should not be used in situ, but should be moved out
	 * and stored elsewhere for use.
	 *
	 * @param string $category The AFP category - NOT the WordPress category we are dealing with
	 *
	 * @return bool
	 */
	private function set_delivery_path( string $category ): bool {

		/**
		 * The "english" portion of the path is dependent on the client language and on AFP delivery protocols
		 */
		$path          = trim( $this->settings['delivery_path'], '\/' );
		$path          = str_replace( '.', '', $path );
		$delivery_path = WP_CONTENT_DIR . '/' . $path . '/english/shared/' . $category . '/';
		$delivery_url  = WP_CONTENT_URL . '/' . $path . '/english/shared/' . $category . '/';

		if ( ! file_exists( $delivery_path ) ) {
			return false;
		}

		$this->delivery_path = $delivery_path;
		$this->delivery_url  = $delivery_url;

		return true;
	}

	/**
	 * Checks for need to update.
	 * Returns post id if post exists, false if post does not exist and on error
	 *
	 * @param string $afp_slug Contains the filename, which is unique ( $file_data['slug'] )
	 *
	 * @return int
	 */
	private function does_post_exist( string $afp_slug ): int {

		if ( empty( $afp_slug ) ) {
			return 0;
		}

		$args = array(
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'nopaging'       => true,
			'meta_query'     => array(
				array(
					'key'   => 'afp_slug',
					'value' => $afp_slug,
				),
			),
		);

		$post_ids = get_posts( $args );

		if ( empty( $post_ids ) ) {
			return 0;
		}

		if ( absint( $post_ids[0] ) > 1 ) {
			return $post_ids[0];
		}

		return 0;
	}

	/**
	 * Gets XML data from the delivery path
	 *
	 * @param string $file The filename we're getting XML from
	 *
	 * @return boolean|SimpleXMLElement Returns XML data object. False on error.
	 */
	private function get_xml( string $file ) {

		$file = $this->delivery_path . $file;

		if ( ! file_exists( $file ) ) {

			$this->logger->write_log( 'Missing input file ' . $file );

			return false;
		}

		// phpcs:ignore
		$file_handle = fopen( $file, 'r' ); // WP_Filesystem offers no way of opening file handles

		/* Check this file is not still open via FTP */
		if ( ! flock( $file_handle, LOCK_EX | LOCK_NB ) ) {

			$this->logger->write_log( 'Input file locked.' );
			// phpcs:ignore
			fclose( $file_handle );

			return false;
		}
		// phpcs:ignore
		fclose( $file_handle );

		clearstatcache( true, $file );

		if ( filesize( $file ) < 100 ) {

			$this->logger->write_log( 'Corrupt input file at ' . $file );

			return false;
		}

		$xml = simplexml_load_file( $file );

		if ( empty( $xml ) || false === $xml ) {
			return false;
		}

		return $xml;

	}


	/**
	 * @param string $image_filename
	 *
	 * @return int $post_id
	 */
	private function does_image_exist( $image_filename ): int {

		$args = array(
			'post_status'    => array( 'all' ),
			'post_type'      => array( 'attachment' ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'nopaging'       => true,
			'meta_query'     => array(
				array(
					'key'   => 'afp_file',
					'value' => $image_filename,
				),
			),
		);

		$post_ids = get_posts( $args );

		if ( ! empty( $post_ids[0] ) && $post_ids[0] > 0 ) {
			return $post_ids[0];
		}

		return 0;
	}

}
