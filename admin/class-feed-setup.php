<?php /** @noinspection SpellCheckingInspection */

/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 10/16/2018
 * Time: 4:23 PM
 */


namespace AFP;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Feed_Setup extends configuration {


	protected static $instance = null;

	public static function init(): ?Feed_Setup {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/* Hook into the appropriate actions when the class is constructed. */
	public function __construct() {
		parent::__construct();

		$this->logger = new logger();
		$this->add_afp_setup_page();

		add_action( 'admin_notices', array( $this, 'check_delivery_path' ) );

	}

	/*
	 * Add the AFP Rubrique settings page to the settings menu
	 */
	public function add_afp_setup_page() {

		add_submenu_page(
			'options-general.php',
			'Agence France Presse',
			'AFP Rubriques',
			'manage_options',
			'afp-options',
			array(
				$this,
				'render_setup_page',
			)
		);

		add_settings_section( 'afp_media_manager', 'Media manager', null, 'afp-options-media-manager' );
		register_setting( 'afp_media_manager', 'afp_rubriques_media' );

		$this->media_fields();

		add_settings_section( 'afp_feedreader_settings', 'Feedreader configuration', null, 'afp-options-feed-reader' );
		register_setting( 'afp_feedreader_settings', 'afp_rubriques_feedreader' );

		$this->feedreader_fields();

		add_settings_section( 'afp_categories', 'AFP category handling', null, 'afp-options-categories' );
		register_setting( 'afp_categories', 'afp_rubriques_categories' );

		$this->category_fields();
	}

	/*
	 * Render the AFP Rubriques setup page
	 */
	public function render_setup_page() {

		ob_start();
		include 'views/render_setup_page.php';
		$html = ob_get_clean();
		echo $html;

	}


	/*
	 * Create the category manager settings section fields and register them
	 */
	public function category_fields() {

		$fields = array();
		$fields = $this->add_category_mapping_fields( $fields );

		$this->register_fields( $fields );

	}


	/*
	 * Create the media manager settings section fields and register them
	 */
	public function media_fields() {

		$fields = array();
		$fields = $this->add_media_fields( $fields );

		$this->register_fields( $fields );
	}

	/*
	 * Create the feedreader settings section fields and register them.
	 */
	public function feedreader_fields() {

		$fields = array();

		/**
		 * Delivery path text field. Sets the delivery directory AFP is sending to.
		 * Assumed to be in the wp-content folder
		 */
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[delivery_path]',
				'title'        => 'Delivery path',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'section'      => 'afp_feedreader_settings',
				'input'        => 'text',
				'type'         => 'text',
				'style'        => 'width:80%;',
				'supplemental' => 'The FTP delivery directory which AFP is delivering to. Must be in /wp-content',
				'data'         => array( 'value' => $this->settings['delivery_path'] ),
			)
		);

		/**
		 * Delivery language selector field. Sets the language used in the delivery directory AFP is sending to.
		 * Default is 'english'
		 */
		$fields = $this->add_delivery_language_field( $fields );
		/**
		 * Import limit text field, of type number. Sets the per category limit to the number of files imported during any one cron.
		 */
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[import_limit]',
				'title'        => 'Maximum imports',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'section'      => 'afp_feedreader_settings',
				'input'        => 'text',
				'type'         => 'number',
				'max'          => '5',
				'style'        => 'width:10%;',
				'supplemental' => 'The maximum number of stories to import per category per scheduled cron job. Keep this as low as possible. Maximum of five allowed.',
				'data'         => array( 'value' => $this->settings['import_limit'] ),
			)
		);

		/**
		 * Import interval text field, of type number. Sets the import interval in seconds.
		 */
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[import_interval]',
				'title'        => 'Import interval',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'section'      => 'afp_feedreader_settings',
				'input'        => 'text',
				'type'         => 'readonly',
				'style'        => 'width:10%;',
				'supplemental' => 'The interval between imports. Shown here in seconds for information purposes only.',
				'data'         => array( 'value' => $this->cron_schedule_import_interval ),
			)
		);

		/**
		 * User ID is not configurable, but exposes the ID of the user the importer will use when inserting posts.
		 */
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[afp_user]',
				'title'        => 'AFP user',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'section'      => 'afp_feedreader_settings',
				'input'        => 'text',
				'type'         => 'readonly',
				'style'        => 'width:20%;',
				'supplemental' => 'The ID of the Agence France Presses user imported stories will be assigned to.',
				'data'         => array( 'value' => $this->settings['afp_user'] ),
			)
		);

		/*
		* First paragraph offered as an option
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[first_paragraph]',
				'title'        => 'Remove first paragraph',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'input'        => 'tickbox',
				'section'      => 'afp_feedreader_settings',
				'supplemental' => 'The first paragraph is used as an excerpt (blurb)',
				'data'         => array( 'value' => $this->settings['first_paragraph'] ),
			)
		);

		/*
		* Use all images
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[use_all_images]',
				'title'        => 'Use all images',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'input'        => 'tickbox',
				'section'      => 'afp_feedreader_settings',
				'supplemental' => 'Use all images in the article as directed by the AFP editors.',
				'data'         => array( 'value' => $this->settings['use_all_images'] ),
			)
		);

		/*
		* Leave image zero in body
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[image_zero_in_body]',
				'title'        => 'Leave image zero in article as image block',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'input'        => 'tickbox',
				'section'      => 'afp_feedreader_settings',
				'supplemental' => 'Leave the first image in the body of the article.',
				'data'         => array( 'value' => $this->settings['image_zero_in_body'] ),
			)
		);

		/*
		* Leave image zero in body
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[database_image_zero]',
				'title'        => 'Database image zero as Featured Image',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'input'        => 'tickbox',
				'section'      => 'afp_feedreader_settings',
				'supplemental' => 'Database the first image and set as the post featured image.',
				'data'         => array( 'value' => $this->settings['database_image_zero'] ),
			)
		);


		/**
		 * Imported status dropdown selector to decide what status to leave the newly imported article in.
		 */
		$fields = $this->add_imported_status_field( $fields );

		/*
		 * Category mapping dropdowns
		 */
		//$fields = $this->add_category_mapping_fields( $fields );

		$this->register_fields( $fields );

	}

	/**
	 * Build the array of media manager fields. Returns the array to the media fields function
	 *
	 * @param array $fields
	 *
	 * @return array $fields
	 */
	public function add_media_fields( $fields = array() ): array {

		/*
		* Must we run the daily purge or not
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_media[purge]',
				'title'        => 'Run daily media purges',
				'callback'     => 'render_field',
				'page'         => 'afp-options-media-manager',
				'input'        => 'tickbox',
				'section'      => 'afp_media_manager',
				'supplemental' => 'Purge media library of older AFP feed images. This prevents your library from getting clogged',
				'data'         => array( 'value' => $this->settings['purge'] ),
			)
		);

		/*
		* Must we run the daily purge or not
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_media[archive_period]',
				'title'        => 'Days to keep imported AFP media files for',
				'callback'     => 'render_field',
				'page'         => 'afp-options-media-manager',
				'input'        => 'text',
				'type'         => 'number',
				'min'          => '3',
				'max'          => '60',
				'section'      => 'afp_media_manager',
				'supplemental' => 'The number of days to keep your automatically imported AFP rubrique images. Your AFP contract will state the period you are allowed to archive for. Minimum of 3 and a maxiumum of 60',
				'data'         => array( 'value' => $this->settings['archive_period'] ),
			)
		);

		/*
		* Must we run the daily purge of drafts or not
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_media[purge_drafts]',
				'title'        => 'Purge all AFP articles left in draft',
				'callback'     => 'render_field',
				'page'         => 'afp-options-media-manager',
				'input'        => 'tickbox',
				'section'      => 'afp_media_manager',
				'supplemental' => 'Delete all articles imported automatically but not published within the set time frame.',
				'data'         => array( 'value' => $this->settings['purge_drafts'] ),
			)
		);


		/*
		* How long should we keep unpublished drafts for, if we are purging them automatically?
		*/
		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_media[purge_drafts_period]',
				'title'        => 'Days to keep unpublished drafts for',
				'callback'     => 'render_field',
				'page'         => 'afp-options-media-manager',
				'input'        => 'text',
				'type'         => 'number',
				'min'          => '1',
				'max'          => '30',
				'section'      => 'afp_media_manager',
				'supplemental' => 'The number of days to keep your automatically imported AFP rubrique articles if they are not used and left in draft. Default is three days.',
				'data'         => array( 'value' => $this->settings['purge_drafts_period'] ),
			)
		);
		return $fields;

	}

	/**
	 * Adds a dropdown for delivery language. THis is used in creating the delivery path
	 * ./wp-content/ input for user /shared/ delivery language
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	public function add_delivery_language_field( $fields ) {

		$selector = '<select name = "afp_rubriques_feedreader[delivery_language]" >';

		foreach ( $this->valid_languages as $value => $text ) {

			$selector .= '<option value="' . esc_attr( $value ) . '" ';
			$selector .= ( strtolower( $value ) === $this->settings['delivery_language'] ) ? ' selected ' : ''; //checked or not
			$selector .= ' >';
			$selector .= esc_html( $text );
			$selector .= '</option>';
		}

		$selector .= '</select>';

		array_push(
			$fields,
			array(
				'id'           => 'afp_rubriques_feedreader[delivery_language]',
				'title'        => 'Delivery language',
				'callback'     => 'render_field',
				'page'         => 'afp-options-feed-reader',
				'section'      => 'afp_feedreader_settings',
				'input'        => 'select',
				'type'         => 'select',
				'style'        => '',
				'supplemental' => 'What language articles are being delivered in. This affects the validity of your delivery path.',
				'data'         => $selector,

			)
		);

		return $fields;

	}

	/**
	 * Adds the imported status dropdown to the feedreader options. Chooose the status an imported post is left in.
	 *
	 * @param array $fields
	 *
	 * @return array $fields
	 */
	public function add_imported_status_field( $fields = array() ): array {

		$statuses = get_post_statuses();

		if ( ! empty( $statuses ) ) {

			$selector = '<select name = "afp_rubriques_feedreader[imported_status]" >';

			foreach ( $statuses as $value => $text ) {

				$selector .= '<option value="' . esc_attr( $value ) . '" ';
				$selector .= ( strtolower( $value ) === strtolower( $this->settings['imported_status'] ) ) ? ' selected ' : ''; //checked or not
				$selector .= ' >';
				$selector .= esc_html( $text );
				$selector .= '</option>';
			}

			$selector .= '</select>';

			array_push(
				$fields,
				array(
					'id'           => 'afp_rubriques_feedreader[imported_status]',
					'title'        => 'Status of imported story',
					'callback'     => 'render_field',
					'page'         => 'afp-options-feed-reader',
					'section'      => 'afp_feedreader_settings',
					'input'        => 'select',
					'type'         => 'select',
					'style'        => '',
					'supplemental' => 'What status to leave the imported stories in.',
					'data'         => $selector,

				)
			);
		}

		return $fields;

	}

	/**
	 * Returns the dropdown selector needed for each afp category ( afp category -> site category ).
	 *
	 * @param $fields
	 *
	 * @return mixed
	 */
	public function add_category_mapping_fields( $fields ) {

		/*
		 * If there is no delivery path we won't get far, so bail now
		 */
		if ( ! isset( $this->settings['delivery_path'] ) || empty( $this->settings['delivery_path'] ) ) {
			return $fields;
		}

		/*
		 * If there is no category map we won't get far, so bail now
		 */
		if ( empty( $this->settings['category_map'] ) ) {
			return $fields;
		}

		ob_start();

		wp_dropdown_categories(
			array(
				'hide_empty'   => 0,
				'name'         => 'select_name',
				'id'           => 'select_name',
				'hierarchical' => true,
			)
		);

		$site_categories_html = ob_get_clean();

		foreach ( $this->settings['category_map'] as $key => $value ) {

			$selector = $site_categories_html;

			//replace select_name with  id, name
			$selector = str_replace( 'select_name', 'afp_rubriques_categories[category_map][' . $key . ']', $selector );
			//check selected value
			$selector = str_replace( 'value="' . $value . '"', 'value="' . $value . '" selected ', $selector );

			array_push(
				$fields,
				array(
					'id'           => 'afp_rubriques_categories[category_map][' . $key . ']',
					'title'        => $key,
					'callback'     => 'render_field',
					'page'         => 'afp-options-categories',
					'section'      => 'afp_categories',
					'input'        => 'select',
					'type'         => 'select',
					'style'        => 'width:80%;',
					'supplemental' => 'Set the category items from this rubric will be assigned to',
					'data'         => $selector,
				)
			);

			array_push(
				$fields,
				array(
					'id'           => 'afp_rubriques_categories[category_status][' . $key . ']',
					'title'        => 'Publish the ' . $key . ' rubrique immediately',
					'callback'     => 'render_field',
					'page'         => 'afp-options-categories',
					'section'      => 'afp_categories',
					'input'        => 'tickbox',
					'supplemental' => 'This overrides the overall status setting. All articles in the ' . $key . ' rubrique will publish immediately.',
					'data'         => array(
						'value' => ! empty( $this->settings['category_status'][ $key ] ) ? $this->settings['category_status'][ $key ] : 'false',
					),
				)
			);
		}

		return $fields;
	}

	/**
	 * Registers fields using array values in the $fields array
	 *
	 * @param $fields
	 *
	 * @return void
	 */
	public function register_fields( $fields ) {

		foreach ( $fields as $field ) {
			add_settings_field(
				$field['id'],
				$field['title'],
				array( $this, $field['callback'] ),
				$field['page'],
				$field['section'],
				$field
			);
		}

	}

	/**
	 * If the delivery path is still missing, activate the admin error message
	 */
	public function check_delivery_path() {

		if ( ! isset( $this->settings['delivery_path'] ) || empty( $this->settings['delivery_path'] ) ) {
			$this->render_admin_message( $this->delivery_path_error );

			return;
		}

		$basic_path = WP_CONTENT_DIR . '/' . $this->settings['delivery_path'] . '/';

		$delivery_path = $this->get_delivery_path();

		if ( file_exists( $basic_path ) && ! file_exists( $delivery_path ) ) {
			$this->render_admin_message( $this->delivery_path_afp_error );

			return;
		}

		if ( ! file_exists( $basic_path ) ) {
			$this->render_admin_message( $this->delivery_path_error );

			return;
		}

	}

	/**
	 * Echoes out the field views html based on the data in the $field array
	 *
	 * @param $field
	 */
	public function render_field( $field ) {

		$white_list = array( 'text', 'select', 'tickbox' );
		if ( ! in_array( $field['input'], $white_list, true ) ) {
			echo '';
		}

		ob_start();
		include 'views/render_' . $field['input'] . '_field.php';
		$html = ob_get_clean();
		echo $html;

	}

	/**
	 * Renders the admin message
	 *
	 * @param string $message
	 */
	public function render_admin_message( $message = '' ) {

		ob_start();
		include 'views/render_admin_message.php';
		$html = ob_get_clean();
		echo $html;

	}

	/**
	 * Renders the import logs section of the options setup page
	 * Also runs import log maintenance function
	 */
	public function render_import_logs() {

		$logs                 = $this->logger->get_logs();
		$current_log_contents = '';

		$message = 'There are ' . count( $logs ) . ' log files in total.';

		$current_log = end( $logs );

		if ( ! empty( $current_log ) && file_exists( $this->logger->log_path . $current_log['title'] ) ) {

			$current_log_contents = file_get_contents( $this->logger->log_path . $current_log['title'] );

			ob_start();
			include 'views/render_import_logs.php';
			$html = ob_get_clean();
			echo $html;

		} else {
			echo 'Log files for today are not available.';
		}

	}

}
