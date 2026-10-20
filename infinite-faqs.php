<?php
/**
 * Plugin Name: Infinite FAQs
 * Plugin URI: https://allmytools.xyz/
 * Description: AI-powered FAQ knowledge base using your own API key (BYOK) to generate accurate answers from your Markdown docs without hallucinations.
 * Version: 1.0.0
 * Author: THEWPBRO
 * License: GPLv2 or later
 * Text Domain: infinite-faqs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'INFINITE_FAQS_VERSION', '1.0.0' );
define( 'INFINITE_FAQS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INFINITE_FAQS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

function infinite_faqs_activate() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'infinite_faqs_docs';
    $charset_collate = $wpdb->get_charset_collate();

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
    dbDelta( $sql );

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
    if ( false === get_option( 'infinite_faqs_shortcode_heading' ) ) {
        add_option( 'infinite_faqs_shortcode_heading', 'Questions' );
    }
    if ( false === get_option( 'infinite_faqs_shortcode_subtitle' ) ) {
        add_option( 'infinite_faqs_shortcode_subtitle', 'The short ones are here. For anything else, ask our AI below.' );
    }
    if ( false === get_option( 'infinite_faqs_static_faqs' ) ) {
        add_option( 'infinite_faqs_static_faqs', infinite_faqs_get_default_static_faqs() );
    }
}
register_activation_hook( __FILE__, 'infinite_faqs_activate' );

function infinite_faqs_get_default_static_faqs() {
    return array(
        array( 'question' => '', 'answer' => '' ),
        array( 'question' => '', 'answer' => '' ),
        array( 'question' => '', 'answer' => '' ),
        array( 'question' => '', 'answer' => '' ),
    );
}

function infinite_faqs_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'infinite_faqs_deactivate' );

function infinite_faqs_encrypt_data( $data ) {
    if ( empty( $data ) ) {
        return $data;
    }
    $encryption_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'fallback-ifaq-key-789';
    $iv_length      = openssl_cipher_iv_length( 'aes-256-cbc' );
    $iv             = openssl_random_pseudo_bytes( $iv_length );
    $encrypted      = openssl_encrypt( $data, 'aes-256-cbc', $encryption_key, 0, $iv );
    return base64_encode( $encrypted . '::' . $iv );
}

function infinite_faqs_decrypt_data( $data ) {
    if ( empty( $data ) ) {
        return $data;
    }
    $encryption_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'fallback-ifaq-key-789';
    $decoded        = base64_decode( $data );
    if ( false === strpos( $decoded, '::' ) ) {
        return $data;
    }
    list( $encrypted_data, $iv ) = explode( '::', $decoded, 2 );
    return openssl_decrypt( $encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv );
}

function infinite_faqs_mask_api_key( $api_key ) {
    if ( empty( $api_key ) ) {
        return '';
    }
    $length = strlen( $api_key );
    if ( $length <= 8 ) {
        return str_repeat( '*', 8 );
    }
    return substr( $api_key, 0, 4 ) . str_repeat( '*', 8 ) . substr( $api_key, -4 );
}

require_once INFINITE_FAQS_PLUGIN_DIR . 'includes/rest-api.php';
require_once INFINITE_FAQS_PLUGIN_DIR . 'includes/shortcode.php';

if ( is_admin() ) {
    require_once INFINITE_FAQS_PLUGIN_DIR . 'admin/settings-page.php';
    require_once INFINITE_FAQS_PLUGIN_DIR . 'admin/file-uploader.php';
}
