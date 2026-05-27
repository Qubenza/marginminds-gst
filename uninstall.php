<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes all plugin data from the database.
 *
 * @package Marginminds
 */

// Exit if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'marginminds_gst_settings' );
