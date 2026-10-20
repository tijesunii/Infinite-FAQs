<?php
/**
 * Infinite FAQs - Uninstall Handler
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

$infinite_faqs_keep_data = get_option( 'infinite_faqs_keep_data_on_uninstall' );

if ( ! $infinite_faqs_keep_data || '0' === $infinite_faqs_keep_data ) {
    global $wpdb;

    $infinite_faqs_table_name = $wpdb->prefix . 'infinite_faqs_docs';
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $infinite_faqs_table_name ) );

    delete_option( 'infinite_faqs_api_key' );
    delete_option( 'infinite_faqs_support_url' );
    delete_option( 'infinite_faqs_ai_provider' );
    delete_option( 'infinite_faqs_answer_length' );
    delete_option( 'infinite_faqs_shortcode_heading' );
    delete_option( 'infinite_faqs_shortcode_subtitle' );
    delete_option( 'infinite_faqs_static_faqs' );
    delete_option( 'infinite_faqs_keep_data_on_uninstall' );
}
