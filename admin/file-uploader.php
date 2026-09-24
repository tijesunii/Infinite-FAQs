<?php
/**
 * Infinite FAQs for WordPress - Markdown Ingestion Engine
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register the Ingestion Menu Page.
 */
function infinite_faqs_add_ingestion_menu() {
    add_submenu_page(
        'infinite-faqs',
        esc_html__( 'Manage Knowledge Base', 'infinite-faqs' ),
        esc_html__( 'Knowledge Base', 'infinite-faqs' ),
        'manage_options',
        'infinite-faqs-ingestion',
        'infinite_faqs_ingestion_page_html'
    );
}
add_action( 'admin_menu', 'infinite_faqs_add_ingestion_menu' );

/**
 * AI Keyword Generator Helper
 */
function infinite_faqs_generate_keywords( $chunk_text ) {
    $provider = get_option( 'infinite_faqs_ai_provider', 'openai' );
    $encrypted_key = get_option( 'infinite_faqs_api_key', '' );
    $api_key = infinite_faqs_decrypt_data( $encrypted_key );
    
    if ( empty( $api_key ) ) {
        return '';
    }

    $prompt = "Generate 5 to 10 highly relevant search keywords for the following documentation text. Return ONLY the keywords separated by commas. Do not include any other text, formatting, or explanation.\n\nText:\n" . $chunk_text;
    $keywords = '';

    if ( $provider === 'openai' ) {
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt )
                )
            )),
            'timeout' => 15,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $keywords = trim( $body['choices'][0]['message']['content'] );
            }
        }
    } elseif ( $provider === 'anthropic' ) {
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model'      => 'claude-3-haiku-20240307',
                'max_tokens' => 100,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $prompt )
                )
            )),
            'timeout' => 15,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['content'][0]['text'] ) ) {
                $keywords = trim( $body['content'][0]['text'] );
            }
        }
    } elseif ( $provider === 'gemini' ) {
        $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body' => wp_json_encode( array(
                'contents' => array(
                    array( 'parts' => array( array( 'text' => $prompt ) ) )
                )
            )),
            'timeout' => 15,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                $keywords = trim( $body['candidates'][0]['content']['parts'][0]['text'] );
            }
        }
    } elseif ( $provider === 'deepseek' ) {
        $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'model' => 'deepseek-chat',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt )
                )
            )),
            'timeout' => 15,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $keywords = trim( $body['choices'][0]['message']['content'] );
            }
        }
    }

    return sanitize_text_field( $keywords );
}

/**
 * Handle File Upload and Parsedown Chunking
 */
function infinite_faqs_handle_upload() {
    if ( ! isset( $_POST['infinite_faqs_upload_nonce'] ) || ! wp_verify_nonce( $_POST['infinite_faqs_upload_nonce'], 'infinite_faqs_upload_file' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( empty( $_FILES['infinite_faqs_md_file']['tmp_name'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please select a file.', 'infinite-faqs' ) . '</p></div>';
        return;
    }

    $file_ext = strtolower( pathinfo( $_FILES['infinite_faqs_md_file']['name'], PATHINFO_EXTENSION ) );
    if ( $file_ext !== 'md' ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Only .md files are allowed.', 'infinite-faqs' ) . '</p></div>';
        return;
    }

    $base_page_url = '';
    if ( ! empty( $_POST['infinite_faqs_base_page_url'] ) ) {
        $base_page_url = esc_url_raw( wp_unslash( $_POST['infinite_faqs_base_page_url'] ) );
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Base Page URL is required.', 'infinite-faqs' ) . '</p></div>';
        return;
    }

    // Load Parsedown
    if ( ! class_exists( 'Parsedown' ) ) {
        require_once INFINITE_FAQS_PLUGIN_DIR . 'lib/Parsedown.php';
    }

    $file_name = sanitize_file_name( $_FILES['infinite_faqs_md_file']['name'] );
    $file_content = file_get_contents( $_FILES['infinite_faqs_md_file']['tmp_name'] );
    
    $parsedown = new Parsedown();
    $html_content = $parsedown->text( $file_content );

    // Parse HTML to chunks by headers
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    $chunks = array();
    $current_anchor = sanitize_title_with_dashes( 'introduction' );
    $current_text = '';

    foreach ( $dom->childNodes as $node ) {
        if ( $node->nodeType === XML_ELEMENT_NODE && preg_match( '/^h[1-6]$/i', $node->nodeName ) ) {
            // Save the previous chunk if it has text
            if ( trim( $current_text ) !== '' ) {
                $chunks[] = array(
                    'anchor' => $current_anchor,
                    'text'   => trim( $current_text )
                );
            }
            $current_anchor = sanitize_title_with_dashes( $node->textContent );
            $current_text = '';
        } else {
            $current_text .= $node->textContent . "\n";
        }
    }
    // Don't forget the last chunk
    if ( trim( $current_text ) !== '' ) {
        $chunks[] = array(
            'anchor' => $current_anchor,
            'text'   => trim( $current_text )
        );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';

    // Delete existing rows for this file to prevent duplicates
    $wpdb->delete( $table_name, array( 'file_name' => $file_name ), array( '%s' ) );

    $upload_batch_id = uniqid( 'batch_', true );
    $inserted_count = 0;

    foreach ( $chunks as $chunk ) {
        // Generate AI keywords per chunk
        $keywords = infinite_faqs_generate_keywords( $chunk['text'] );

        $inserted = $wpdb->insert(
            $table_name,
            array(
                'upload_batch_id' => $upload_batch_id,
                'file_name'       => $file_name,
                'page_slug'       => $base_page_url,
                'anchor_id'       => $chunk['anchor'],
                'chunk_text'      => $chunk['text'],
                'keywords'        => $keywords,
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
        if ( $inserted ) {
            $inserted_count++;
        }
    }

    // Explicitly destroy the temporary file from the server immediately after processing
    if ( file_exists( $_FILES['infinite_faqs_md_file']['tmp_name'] ) ) {
        unlink( $_FILES['infinite_faqs_md_file']['tmp_name'] );
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Successfully processed "%s" into %d chunks and generated keywords.', 'infinite-faqs' ), esc_html( $file_name ), $inserted_count ) . '</p></div>';
}

/**
 * Render the Ingestion Engine Page HTML
 */
function infinite_faqs_ingestion_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['infinite_faqs_upload_submit'] ) ) {
        infinite_faqs_handle_upload();
    }
    $retained_url = '';
    if ( isset( $_POST['infinite_faqs_base_page_url'] ) ) {
        $retained_url = esc_url_raw( wp_unslash( $_POST['infinite_faqs_base_page_url'] ) );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Manage Knowledge Base', 'infinite-faqs' ); ?></h1>
        
        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
            <h2><?php esc_html_e( 'Upload Markdown Documentation', 'infinite-faqs' ); ?></h2>
            <p><?php esc_html_e( 'Upload your .md files and the system will automatically generate search keywords using your selected AI Provider.', 'infinite-faqs' ); ?></p>
            
            <form method="post" enctype="multipart/form-data" action="">
                <?php wp_nonce_field( 'infinite_faqs_upload_file', 'infinite_faqs_upload_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="infinite_faqs_base_page_url"><?php esc_html_e( 'Base Page URL', 'infinite-faqs' ); ?></label></th>
                        <td>
                            <input type="url" name="infinite_faqs_base_page_url" id="infinite_faqs_base_page_url" class="regular-text" placeholder="https://example.com/pricing" value="<?php echo esc_attr( $retained_url ); ?>" required />
                            <p class="description"><?php esc_html_e( 'The live URL where this document will live. Essential for generating "Read more" links.', 'infinite-faqs' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="infinite_faqs_md_file"><?php esc_html_e( 'Markdown File', 'infinite-faqs' ); ?></label></th>
                        <td>
                            <input type="file" name="infinite_faqs_md_file" id="infinite_faqs_md_file" accept=".md" required />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( esc_html__( 'Upload & Process', 'infinite-faqs' ), 'primary', 'infinite_faqs_upload_submit' ); ?>
            </form>
        </div>

        <div style="max-width: 600px; margin-top: 40px;">
            <h2><?php esc_html_e( 'Indexed Files', 'infinite-faqs' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'File Name', 'infinite-faqs' ); ?></th>
                        <th><?php esc_html_e( 'Base URL', 'infinite-faqs' ); ?></th>
                        <th><?php esc_html_e( 'Chunks', 'infinite-faqs' ); ?></th>
                        <th><?php esc_html_e( 'Last Updated', 'infinite-faqs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'infinite_faqs_docs';
                    $files = $wpdb->get_results( "SELECT file_name, page_slug, COUNT(id) as chunks, MAX(created_at) as last_updated FROM $table_name GROUP BY file_name, page_slug ORDER BY last_updated DESC" );

                    if ( ! empty( $files ) ) {
                        foreach ( $files as $file ) {
                            echo '<tr>';
                            echo '<td>' . esc_html( $file->file_name ) . '</td>';
                            echo '<td><a href="' . esc_url( $file->page_slug ) . '" target="_blank">' . esc_html( $file->page_slug ) . '</a></td>';
                            echo '<td>' . esc_html( $file->chunks ) . '</td>';
                            echo '<td>' . esc_html( $file->last_updated ) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4">' . esc_html__( 'No files indexed yet.', 'infinite-faqs' ) . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
