<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Auth0 Settings', WPA0_LANG); ?></h2>
    <?php if( count(get_settings_errors()) == 0 && isset($_GET['settings-updated']) ) { ?>
        <div id="message" class="updated">
            <p><strong><?php _e('Settings saved.') ?></strong></p>
        </div>
    <?php } ?>
    <?php settings_errors(); ?>
	<form action="options.php" method="post" onsubmit="return presubmit();">
		<?php settings_fields( WP_Auth0_Options::Instance()->get_options_name() ); ?>
		<?php do_settings_sections( WP_Auth0_Options::Instance()->get_options_name() ); ?>
		<?php submit_button(); ?>
	</form>
</div>

<script type="text/javascript">
	function presubmit() {
		if (typeof(a0metricsLib) !== 'undefined') {
			a0metricsLib.track('submit:settings', {});
		}
		return true;
	}
</script>
