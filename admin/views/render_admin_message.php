<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/3/2018
 * Time: 3:13 PM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<div class="notice notice-error">
    <p>
		<?php echo esc_html( $message ); ?>
    </p>
</div>
