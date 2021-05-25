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

?>

    <input
            id="<?php echo esc_attr( $field['id'] ); ?>"
            type="<?php echo esc_attr( $field['type'] ); ?>"
            name="<?php echo esc_attr( $field['id'] ); ?>"
            value="<?php echo esc_attr( $field['data']['value'] ); ?>"
    style="<?php echo ( empty( $field['style'] ) ) ? '' : esc_attr( $field['style'] ); ?>"
<?php echo ( isset( $field['min'] ) ) ? ' min = "' . esc_attr( $field['min'] ) . '"' : ''; ?>
<?php echo ( isset( $field['max'] ) ) ? ' max = "' . esc_attr( $field['max'] ) . '"' : ''; ?>
<?php echo ( 'readonly' === $field['type'] ) ? 'readonly' : ''; ?>
    />


<?php
if ( isset( $field['helper'] ) ) {
	echo '<span class="helper">' . esc_html( $field['helper'] ) . '</span>';
}
if ( isset( $field['supplemental'] ) ) {
	echo '<p class="description">' . esc_html( $field['supplemental'] ) . '</p>';
}
