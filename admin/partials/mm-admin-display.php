<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Media_Maestro
 * @subpackage Media_Maestro/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">

	<h2>Media Maestro Settings</h2>

	<form method="post" action="options.php">
		<?php
			settings_fields( 'media-maestro' );
			do_settings_sections( 'media-maestro' );
			submit_button();
		?>
	</form>

</div>
