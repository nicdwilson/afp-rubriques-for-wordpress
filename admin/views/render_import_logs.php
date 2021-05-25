<?php
/**
 * Created by PhpStorm.
 * User: nicdw
 * Date: 11/6/2018
 * Time: 1:16 PM
 */

?>

<h2>
	Download import log files
</h2>
<br>
<span class="description">Log files older than 21 days are automatically deleted.</span>
<br>
<br>

    <table class="wp-list-table widefat fixed striped posts">

	<?php foreach ( $logs as $log ) : ?>

    <tr>
        <td>
            <strong>
		        <?php echo date( 'D, j M Y', strtotime( $log['mtime'] ) ); ?>
            </strong>
        </td>
        <td>
            <a href="<?php echo esc_url( $log['link'] ); ?>" title="Download logfile">
                Download
            </a>
        </td>
    </tr>

	<?php endforeach; ?>

</table>

<br>
<br>
    <h2>
        Current log file
    </h2>
<br>

<div class="wide-fat" style="height:500px;background-color:#fff; padding: 1em;overflow-y: scroll;overflow-x: hidden">

    <pre>
        <?php echo esc_html( $current_log_contents ); ?>
    </pre>

</div>