<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file handles dropping the custom database tables and removing
 * plugin options if the admin hasn't opted to keep the data.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Check if the admin opted to keep data on uninstall
$infinite_faqs_keep_data = get_option( 'infinite_faqs_keep_data_on_uninstall' );

if ( ! $infinite_faqs_keep_data || '0' === $infinite_faqs_keep_data ) {
	global $wpdb;

	// Drop the custom table
	$infinite_faqs_table_name = $wpdb->prefix . 'infinite_faqs_docs';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $infinite_faqs_table_name ) );

	// Delete options
	delete_option( 'infinite_faqs_api_key' );
	delete_option( 'infinite_faqs_support_url' );
	delete_option( 'infinite_faqs_ai_provider' );
	delete_option( 'infinite_faqs_answer_length' );
	delete_option( 'infinite_faqs_shortcode_heading' );
	delete_option( 'infinite_faqs_shortcode_subtitle' );
	delete_option( 'infinite_faqs_static_faqs' );
	delete_option( 'infinite_faqs_keep_data_on_uninstall' );
}
