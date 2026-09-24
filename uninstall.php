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
$keep_data = get_option( 'infinite_faqs_keep_data_on_uninstall' );

if ( ! $keep_data || $keep_data === '0' ) {
    global $wpdb;

    // Drop the custom table
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';
    $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

    // Delete options
    delete_option( 'infinite_faqs_api_key' );
    delete_option( 'infinite_faqs_support_url' );
    delete_option( 'infinite_faqs_ai_provider' );
    delete_option( 'infinite_faqs_answer_length' );
    delete_option( 'infinite_faqs_keep_data_on_uninstall' );
}
