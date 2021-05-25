<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/3/2018
 * Time: 3:20 PM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<div class="wrap">

    <div id="icon-themes" class="icon32"></div>
    <h2>Agence France Presse</h2>

	<?php $active_tab = ( isset( $_GET['tab'] ) ) ? sanitize_text_field( $_GET['tab'] ) : 'import'; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=afp-options&tab=import"  class="nav-tab <?php echo 'import' === $active_tab ? 'nav-tab-active' : ''; ?>">
            Rubrique import options
        </a>
        <a href="?page=afp-options&tab=categories" class="nav-tab <?php echo 'categories' === $active_tab ? 'nav-tab-active' : ''; ?>">
            Rubrique categories
        </a>
        <a href="?page=afp-options&tab=purge" class="nav-tab <?php echo 'purge' === $active_tab ? 'nav-tab-active' : ''; ?>">
            Rubrique purge options
        </a>
        <a href="?page=afp-options&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
            Rubrique import logs
        </a>
    </h2>

	<?php if ( 'purge' === $active_tab || 'import' === $active_tab || 'categories' === $active_tab ) : ?>

        <form method="post" action="options.php">

            <?php if ( 'categories' === $active_tab ) : ?>

		        <?php settings_fields( 'afp_categories' ); ?>
		        <?php do_settings_sections( 'afp-options-categories' ); ?>

	        <?php endif; ?>

			<?php if ( 'purge' === $active_tab ) : ?>

				<?php settings_fields( 'afp_media_manager' ); ?>
				<?php do_settings_sections( 'afp-options-media-manager' ); ?>

			<?php endif; ?>

			<?php if ( 'import' === $active_tab ) : ?>

				<?php settings_fields( 'afp_feedreader_settings' ); ?>
				<?php do_settings_sections( 'afp-options-feed-reader' ); ?>

			<?php endif; ?>

			<?php submit_button(); ?>

    </form>

	<?php endif; ?>

	<?php if ( 'logs' === $active_tab ) : ?>

		<?php $this->render_import_logs(); ?>

	<?php endif; ?>


</div>
