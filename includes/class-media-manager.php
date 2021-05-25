<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/8/2018
 * Time: 3:19 PM
 */

namespace AFP;

// If this file is called directly, abort.
use DateTime;
use Exception;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Media_Manager extends Configuration {

	private $purge_date;

	public function __construct() {
		parent::__construct();
	}

	public function run_purge() {

		$this->set_purge_date();

		$this->set_afp_image_store();

		if ( true !== $this->settings['purge'] ) {

			$this->logger->write_log( 'Media purge is not set. Aborting purge.' );
			exit;
		}

		$post_ids = $this->get_post_ids();

		if ( false === $post_ids ) {
			$this->logger->write_log( 'Media purge aborting on error fetching post ids.' );
			exit;
		}

		if ( ! is_array( $post_ids ) ) {
			$this->logger->write_log( 'Media purge aborting on empty result.' );
			exit;
		}

		$i = 0;
		foreach ( $post_ids as $post_id ) {

			$article_id         = wp_get_post_parent_id( $post_id );
			$old_file_full_path = get_attached_file( $post_id );

			$file_name          = basename( $old_file_full_path );
			$new_file_full_path = $this->image_store_path . $file_name;
			$new_file_url       = $this->image_store_url . $file_name;

			copy( $old_file_full_path, $new_file_full_path );

			if ( ! file_exists( $new_file_full_path ) ) {
				$this->logger->write_log( 'Image purge failed to move the featured image to the image store' );
				continue;
			}

			$post         = get_post( $article_id );
			$post_content = $post->post_content;

			if ( empty( $post_content ) ) {
				continue;
			}

			/*
			* Allow users to style their own images
			*/
			$image_classes = apply_filters( 'afp_rubrique_image_styling', array() );
			$image_classes = $this->check_image_styling( $image_classes );

			$the_missing_link  = '<div class="' . $image_classes['div'] . '">';
			$the_missing_link .= '<figure class="' . $image_classes['figure'] . '">';
			$the_missing_link .= '<img src="' . esc_html( $new_file_url ) . '" alt="' . esc_html( $post->post_excerpt ) . '" class="' . $image_classes['img'] . '" />';
			$the_missing_link .= '<figcaption class="' . $image_classes['figcaption'] . '">' . esc_html( $post->post_excerpt ) . '</figcaption>';
			$the_missing_link .= '</figure>';
			$the_missing_link .= '</div>';
			$post_content      = $the_missing_link . $post_content;

			$post_data = wp_update_post(
				array(
					'ID'           => $article_id,
					'post_content' => $post_content,
				)
			);

			if ( is_wp_error( $post_data ) || false === $post_data ) {
				$this->logger->write_log( 'Post update of missing link failed for ' . $article_id );
				continue;
			}

			$post_data = wp_delete_post( $post_id, true );

			if ( is_wp_error( $post_data ) || false === $post_data ) {
				$this->logger->write_log( 'Image delete failed for image post id ' . $post_id );
				continue;
			}

			$i ++;
		}

		$this->logger->write_log( 'Deleted ' . $i . ' AFP images from the database.' );
	}


	public function set_purge_date() {

		if ( empty( $this->settings['archive_period'] ) ) {

			$this->logger->write_log( 'Media archive period is invalid. Aborting purge.' );
			exit;
		}

		if ( $this->settings['archive_period'] < 3 ) {

			$this->logger->write_log( 'Media archive period is too short. Aborting purge. Minimum value is three days.' );
			exit;
		}

		if ( $this->settings['archive_period'] > 60 ) {

			$this->logger->write_log( 'Media archive period is too short. Aborting purge. Minimum value is sixty days.' );
			exit;
		}

		try {
			$date = new DateTime( 'now' );
		} catch ( Exception $ex ) {
			$this->logger->write_log( 'Invalid purge date. ' . $ex->getMessage() );
			exit;
		}

		$archive_period = sanitize_text_field( $this->settings['archive_period'] );

		try {
			$date->modify( $archive_period . ' days ago' );
		} catch ( Exception $ex ) {
			$this->logger->write_log( 'Cannot modify purge date. ' . $ex->getMessage() );
			exit;
		}

		$purge_date = array(
			'year'  => absint( $date->format( 'Y' ) ),
			'month' => absint( $date->format( 'm' ) ),
			'day'   => absint( $date->format( 'd' ) ),
		);

		$this->purge_date = $purge_date;

	}

	public function get_post_ids() {

		if ( empty( $this->purge_date ) ) {
			return false;
		}

		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 10,
			'fields'         => 'ids',
			'post_mime_type' => 'image / jpeg',
			'order'          => 'DESC',
			'orderby'        => 'post_date',
			'meta_query'     => array(
				array(
					'key'   => 'is_afp',
					'value' => 1,
				),
			),
			'date_query'     => array(
				'before' => array(
					'year'  => $this->purge_date['year'],
					'month' => $this->purge_date['month'],
					'day'   => $this->purge_date['day'],
				),
			),
		);

		$post_ids = get_posts( $args );

		if ( empty( $post_ids ) ) {

			$this->logger->write_log( 'No post IDs returned' );

			return false;
		}

		return $post_ids;
	}

}
