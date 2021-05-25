<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/21/2018
 * Time: 4:20 PM
 */

namespace AFP;

class Feed_Taxonomy extends Configuration {

	/*
	 * Rubrique custom taxonomy labels
	 */
	private $feed_taxonomy_labels = array(
		'name'              => 'AFP rubriques',
		'singular_name'     => 'AFP rubrique',
		'search_items'      => 'Search AFP rubriques',
		'all_items'         => 'All AFP rubriques',
		'parent_item'       => 'Parent rubrique',
		'parent_item_colon' => 'Parent rubrique:',
		'edit_item'         => 'Edit AFP rubriques',
		'update_item'       => 'Update AFP rubriques',
		'add_new_item'      => 'Add AFP rubriques',
		'new_item_name'     => 'New AFP rubrique',
		'menu_name'         => 'AFP Rubriques',
	);


	private $feed_taxonomy_args;

	protected static $instance = null;

	public static function init(): ?Feed_Taxonomy {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();

		$this->set_taxonomy_arguments();

		register_taxonomy( 'rubrique', array( 'post' ), $this->feed_taxonomy_args );
		add_action( 'restrict_manage_posts', array( $this, 'add_rubrique_taxonomy_filter' ) );
	}

	/**
	 * Sets taxonomy arguments for the rubrique custom taxonomy
	 */
	public function set_taxonomy_arguments() {

		$this->feed_taxonomy_args = array(
			'public'             => true,
			'hierarchical'       => false,
			'labels'             => $this->feed_taxonomy_labels,
			'show_ui'            => false,
			'show_in_menu'       => true,
			'show_admin_column'  => true,
			'rewrite'            => array( 'slug' => 'rubrique' ),
			'show_in_rest'       => true,
			'show_in_quick_edit' => false,
			'show_in_nav_menus'  => false,
		);

	}

	/**
	 * Creates taxonomy terms for each AFP category, as read from the individual rubriques' delivery directories in
	 * the delivery path
	 */
	public function insert_rubrique_taxonomy_terms() {

		$rubriques = $this->get_afp_categories();

		foreach ( $rubriques as $rubrique ) {

			$term = get_term_by( 'slug', $rubrique, 'rubrique' );

			if ( false === $term ) {

				$args = array(
					'slug' => $rubrique,
				);

				wp_insert_term( $rubrique, 'rubrique', $args );
			}
		}
	}


	/**
	 *  Adds the dropdown filter to the posts list page
	 * todo escape output correctly
	 */
	public function add_rubrique_taxonomy_filter() {

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		global $typenow;

		if ( 'post' === $typenow ) {

			$terms = get_terms( 'rubrique', array( 'hide_empty' => false ) );

			if ( count( $terms ) > 0 ) {

				$html  = '<select name="rubrique" id="rubrique" class="postform">';
				$html .= '<option value="">Show all AFP files</option>';

				foreach ( $terms as $term ) {

					$current = ( isset( $_GET['rubrique'] ) ) ? sanitize_text_field( $_GET['rubrique'] ) : '';

					$selected = ( $current === $term->slug ) ? ' selected="selected"' : '';

					$html .= '<option value="' . esc_attr( $term->slug ) . '" ';
					$html .= esc_attr( $selected ) . '>' . esc_html( $term->name ) . '</option>';
				}
				$html .= '</select>';
				echo $html;
			}
		}
	}


}
