<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}

$options = array(
	'akamai-version',
	\Akamai::get_plugin_name(),
);

foreach( $options as $option ) {
	delete_option($option);

	// for site options in Multisite
	delete_site_option($option);
}
