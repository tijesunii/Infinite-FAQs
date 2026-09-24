<?php
/**
 * Plugin Name: Infinite FAQs for WordPress
 * Plugin URI: https://allmytools.xyz/
 * Description: Infinite FAQs for WordPress using BYOK AI Provider to generate answers securely from your markdown documents without hallucinations.
 * Version: 1.0.0
 * Author: THEWPBRO
 * License: GPLv2 or later
 * Text Domain: infinite-faqs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Define plugin constants
define( 'INFINITE_FAQS_VERSION', '1.0.0' );
define( 'INFINITE_FAQS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INFINITE_FAQS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Activation hook to create the custom database table.
 */
function infinite_faqs_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';
    $charset_collate = $wpdb->get_charset_collate();

    // Data Schema for wp_infinite_faqs_docs
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        upload_batch_id varchar(100) NOT NULL,
        file_name varchar(255) NOT NULL,
        page_slug varchar(255) NOT NULL,
        anchor_id varchar(255) NOT NULL,
        chunk_text longtext NOT NULL,
        keywords longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        FULLTEXT KEY keywords (keywords)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    
    // Execute dbDelta to create or update the table safely
    dbDelta( $sql );

    // Register default options if they don't exist
    if ( false === get_option( 'infinite_faqs_support_url' ) ) {
        add_option( 'infinite_faqs_support_url', '' );
    }
    if ( false === get_option( 'infinite_faqs_api_key' ) ) {
        add_option( 'infinite_faqs_api_key', '' );
    }
    if ( false === get_option( 'infinite_faqs_keep_data_on_uninstall' ) ) {
        add_option( 'infinite_faqs_keep_data_on_uninstall', '0' );
    }
    if ( false === get_option( 'infinite_faqs_ai_provider' ) ) {
        add_option( 'infinite_faqs_ai_provider', 'openai' );
    }
    if ( false === get_option( 'infinite_faqs_answer_length' ) ) {
        add_option( 'infinite_faqs_answer_length', 'Brief' );
    }
}
register_activation_hook( __FILE__, 'infinite_faqs_activate' );

/**
 * Deactivation hook.
 */
function infinite_faqs_deactivate() {
    // We do not drop tables on deactivation to preserve data
    // Flushing rewrite rules if custom endpoints were added
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'infinite_faqs_deactivate' );

/**
 * Core Initialization
 */
function infinite_faqs_init() {
    // Load plugin text domain, etc.
}
add_action( 'plugins_loaded', 'infinite_faqs_init' );

// Include admin files
if ( is_admin() ) {
    require_once INFINITE_FAQS_PLUGIN_DIR . 'admin/settings-page.php';
    require_once INFINITE_FAQS_PLUGIN_DIR . 'admin/file-uploader.php';
}
