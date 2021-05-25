<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/3/2018
 * Time: 2:48 PM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

$selected = ( $field['data']['value'] === 'true' ) ? 'checked' : '';
echo '<input type="checkbox" name="' . esc_attr( $field['id'] ) . '" value="true" ' . esc_attr( $selected ) . ' />';


if ( isset( $field['helper'] ) ) {
	echo '<span class="helper">' . esc_html( $field['helper'] ) . '</span>';
}
if ( isset( $field['supplemental'] ) ) {
	echo '<p class="description">' . esc_html( $field['supplemental'] ) . '</p>';
}
