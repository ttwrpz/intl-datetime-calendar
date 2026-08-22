<?php
/**
 * Removes the plugin's stored data when it is deleted.
 *
 * Deactivating does not run this file, so settings survive an upgrade and go
 * only when the plugin is actually removed.
 *
 * @package Intl_DateTime_Calendar
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$intl_datetime_calendar_option = 'intl_datetime_calendar_settings';

if ( is_multisite() ) {
	$intl_datetime_calendar_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $intl_datetime_calendar_sites as $intl_datetime_calendar_site_id ) {
		switch_to_blog( $intl_datetime_calendar_site_id );
		delete_option( $intl_datetime_calendar_option );
		restore_current_blog();
	}
} else {
	delete_option( $intl_datetime_calendar_option );
}
