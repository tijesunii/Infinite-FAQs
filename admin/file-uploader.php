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
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
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
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
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
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
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
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
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
    $upload_nonce = isset( $_POST['infinite_faqs_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_upload_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $upload_nonce, 'infinite_faqs_upload_file' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( ! isset( $_FILES['infinite_faqs_md_file']['tmp_name'], $_FILES['infinite_faqs_md_file']['name'] ) || empty( $_FILES['infinite_faqs_md_file']['tmp_name'] ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please select a file.', 'infinite-faqs' ) . '</p></div>';
        return;
    }

    $uploaded_file_name = sanitize_file_name( wp_unslash( $_FILES['infinite_faqs_md_file']['name'] ) );
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Temporary server file path verified via is_uploaded_file().
    $uploaded_tmp_name  = $_FILES['infinite_faqs_md_file']['tmp_name'];

    if ( ! is_uploaded_file( $uploaded_tmp_name ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid file upload.', 'infinite-faqs' ) . '</p></div>';
        return;
    }

    $file_ext = strtolower( pathinfo( $uploaded_file_name, PATHINFO_EXTENSION ) );
    if ( 'md' !== $file_ext ) {
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
    if ( ! class_exists( 'Infinite_FAQs_Parsedown' ) ) {
        require_once INFINITE_FAQS_PLUGIN_DIR . 'lib/Parsedown.php';
    }

    $file_name    = $uploaded_file_name;
    $file_content = file_get_contents( $uploaded_tmp_name );
    
    $parsedown    = new Infinite_FAQs_Parsedown();
    $html_content = $parsedown->text( $file_content );

    // Parse HTML to chunks by headers
    $dom = new DOMDocument();
    libxml_use_internal_errors( true );
    @$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    $chunks         = array();
    $current_anchor = sanitize_title_with_dashes( 'introduction' );
    $current_text   = '';

    foreach ( $dom->childNodes as $node ) {
        if ( XML_ELEMENT_NODE === $node->nodeType && preg_match( '/^h[1-6]$/i', $node->nodeName ) ) {
            // Save the previous chunk if it has text
            if ( '' !== trim( $current_text ) ) {
                $chunks[] = array(
                    'anchor' => $current_anchor,
                    'text'   => trim( $current_text ),
                );
            }
            $current_anchor = sanitize_title_with_dashes( $node->textContent );
            $current_text   = '';
        } else {
            $current_text .= $node->textContent . "\n";
        }
    }
    // Don't forget the last chunk
    if ( '' !== trim( $current_text ) ) {
        $chunks[] = array(
            'anchor' => $current_anchor,
            'text'   => trim( $current_text ),
        );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';

    // Delete existing rows for this file to prevent duplicates
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->delete( $table_name, array( 'file_name' => $file_name ), array( '%s' ) );

    $upload_batch_id = uniqid( 'batch_', true );
    $inserted_count  = 0;

    foreach ( $chunks as $chunk ) {
        // Generate AI keywords per chunk
        $keywords = infinite_faqs_generate_keywords( $chunk['text'] );
        
        // Smart Fallback: If AI keyword generation fails (missing API key, LocalWP SSL error, etc.), 
        // fallback to using the raw text itself as the searchable keywords.
        if ( empty( $keywords ) ) {
            $keywords = wp_strip_all_tags( $chunk['text'] );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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
    if ( file_exists( $uploaded_tmp_name ) ) {
        wp_delete_file( $uploaded_tmp_name );
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
        /* translators: 1: Uploaded Markdown file name, 2: Number of chunks created. */
        esc_html__( 'Successfully processed "%1$s" into %2$d chunks and generated keywords.', 'infinite-faqs' ),
        esc_html( $file_name ),
        absint( $inserted_count )
    ) . '</p></div>';
}

/**
 * AI Helper: Generate 4 Predefined FAQs from a selected Knowledge Base document (or all documents).
 *
 * @param string $selected_file Filename in wp_infinite_faqs_docs or '__all__'.
 * @return array|WP_Error Array of 4 ['question' => ..., 'answer' => ...] items or WP_Error.
 */
function infinite_faqs_generate_faqs_from_doc( $selected_file ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';

    if ( '__all__' === $selected_file || empty( $selected_file ) ) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $chunks = $wpdb->get_col( $wpdb->prepare( 'SELECT chunk_text FROM %i ORDER BY id ASC LIMIT 20', $table_name ) );
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $chunks = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT chunk_text FROM %i WHERE file_name = %s ORDER BY id ASC',
                $table_name,
                $selected_file
            )
        );
    }

    if ( empty( $chunks ) ) {
        return new WP_Error( 'empty_doc', __( 'No documentation chunks found for the selected document.', 'infinite-faqs' ) );
    }

    $provider      = get_option( 'infinite_faqs_ai_provider', 'openai' );
    $encrypted_key = get_option( 'infinite_faqs_api_key', '' );
    $api_key       = infinite_faqs_decrypt_data( $encrypted_key );

    if ( empty( $api_key ) ) {
        return new WP_Error( 'missing_key', __( 'Please configure a valid AI Provider API Key in Infinite FAQs Settings first.', 'infinite-faqs' ) );
    }

    $combined_context = implode( "\n\n---\n\n", $chunks );
    $prompt = "You are a technical documentation assistant. Based ONLY on the documentation text provided below, generate exactly 4 common Frequently Asked Questions and their concise, accurate answers.\n\nReturn ONLY a valid JSON array containing 4 objects, where each object has 'question' and 'answer' string keys. Do NOT wrap the JSON in markdown code fences or include any extra commentary.\n\nExample format:\n[{\"question\":\"Question 1?\",\"answer\":\"Answer 1.\"},{\"question\":\"Question 2?\",\"answer\":\"Answer 2.\"},{\"question\":\"Question 3?\",\"answer\":\"Answer 3.\"},{\"question\":\"Question 4?\",\"answer\":\"Answer 4.\"}]\n\nDocumentation Text:\n" . $combined_context;

    $raw_output = '';

    if ( 'openai' === $provider ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'model'    => 'gpt-4o-mini',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            ) ),
            'timeout' => 25,
        ) );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $raw_output = trim( $body['choices'][0]['message']['content'] );
            }
        } else {
            return $response;
        }
    } elseif ( 'anthropic' === $provider ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'model'      => 'claude-3-haiku-20240307',
                'max_tokens' => 800,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            ) ),
            'timeout' => 25,
        ) );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['content'][0]['text'] ) ) {
                $raw_output = trim( $body['content'][0]['text'] );
            }
        } else {
            return $response;
        }
    } elseif ( 'gemini' === $provider ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'contents' => array(
                    array(
                        'role'  => 'user',
                        'parts' => array( array( 'text' => $prompt ) ),
                    ),
                ),
            ) ),
            'timeout' => 25,
        ) );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                $raw_output = trim( $body['candidates'][0]['content']['parts'][0]['text'] );
            }
        } else {
            return $response;
        }
    } elseif ( 'deepseek' === $provider ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $response = wp_remote_post( 'https://api.deepseek.com/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array(
                'model'    => 'deepseek-chat',
                'messages' => array(
                    array( 'role' => 'user', 'content' => $prompt ),
                ),
            ) ),
            'timeout' => 25,
        ) );
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $raw_output = trim( $body['choices'][0]['message']['content'] );
            }
        } else {
            return $response;
        }
    }

    if ( empty( $raw_output ) ) {
        return new WP_Error( 'ai_empty', __( 'The AI Provider did not return a valid response. Check your API Key and try again.', 'infinite-faqs' ) );
    }

    // Strip any accidental markdown code fences (```json ... ```)
    $cleaned_json = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $raw_output ) );
    $decoded      = json_decode( $cleaned_json, true );

    // Fallback extraction if AI included surrounding prose around the JSON array
    if ( ! is_array( $decoded ) && preg_match( '/\[.*\]/s', $raw_output, $matches ) ) {
        $decoded = json_decode( $matches[0], true );
    }

    if ( ! is_array( $decoded ) || empty( $decoded ) ) {
        return new WP_Error( 'json_parse_error', __( 'Could not parse the AI response into FAQ cards. Please try again.', 'infinite-faqs' ) );
    }

    $generated_faqs = array();
    for ( $i = 0; $i < 4; $i++ ) {
        $q = isset( $decoded[ $i ]['question'] ) ? sanitize_text_field( $decoded[ $i ]['question'] ) : '';
        $a = isset( $decoded[ $i ]['answer'] ) ? sanitize_textarea_field( $decoded[ $i ]['answer'] ) : '';
        $generated_faqs[] = array(
            'question' => $q,
            'answer'   => $a,
        );
    }

    return $generated_faqs;
}

/**
 * Handle Deleting an Indexed File from wp_infinite_faqs_docs.
 */
function infinite_faqs_handle_delete_file() {
    $delete_nonce = isset( $_POST['infinite_faqs_delete_file_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_delete_file_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $delete_nonce, 'infinite_faqs_delete_indexed_file' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $file_to_delete = isset( $_POST['infinite_faqs_delete_file_name'] ) ? sanitize_file_name( wp_unslash( $_POST['infinite_faqs_delete_file_name'] ) ) : '';
    if ( empty( $file_to_delete ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $deleted    = $wpdb->delete( $table_name, array( 'file_name' => $file_to_delete ), array( '%s' ) );

    if ( false !== $deleted ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
            /* translators: 1: Deleted Markdown file name, 2: Number of chunks removed. */
            esc_html__( 'Deleted "%1$s" (%2$d chunks removed) from the knowledge base.', 'infinite-faqs' ),
            esc_html( $file_to_delete ),
            absint( $deleted )
        ) . '</p></div>';
    }
}

/**
 * Handle Saving, Deleting, or AI-Generating the Shortcode Heading, Subtitle & 4 Static FAQ Cards.
 */
function infinite_faqs_handle_static_faqs_actions() {
    $static_nonce = isset( $_POST['infinite_faqs_static_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_static_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $static_nonce, 'infinite_faqs_save_static_faqs' ) ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Always save the Heading if provided (enforcing max 9 characters, e.g., "Questions" or "FAQs")
    if ( isset( $_POST['infinite_faqs_shortcode_heading'] ) ) {
        $raw_heading   = sanitize_text_field( wp_unslash( $_POST['infinite_faqs_shortcode_heading'] ) );
        $clean_heading = mb_substr( trim( $raw_heading ), 0, 9 );
        if ( '' === $clean_heading ) {
            $clean_heading = 'Questions';
        }
        update_option( 'infinite_faqs_shortcode_heading', $clean_heading );
    }

    // Save the Subtitle if provided
    if ( isset( $_POST['infinite_faqs_shortcode_subtitle'] ) ) {
        $raw_subtitle   = sanitize_text_field( wp_unslash( $_POST['infinite_faqs_shortcode_subtitle'] ) );
        $clean_subtitle = trim( $raw_subtitle );
        if ( '' === $clean_subtitle ) {
            $clean_subtitle = 'The short ones are here. For anything else, ask our AI below.';
        }
        update_option( 'infinite_faqs_shortcode_subtitle', $clean_subtitle );
    }

    // Action A: Auto-Generate 4 FAQs with AI from Selected Document
    if ( isset( $_POST['infinite_faqs_ai_generate_faqs_submit'] ) ) {
        $selected_doc = isset( $_POST['infinite_faqs_source_doc'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_source_doc'] ) ) : '__all__';
        $result       = infinite_faqs_generate_faqs_from_doc( $selected_doc );

        if ( is_wp_error( $result ) ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
        } else {
            update_option( 'infinite_faqs_static_faqs', $result );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Successfully generated and saved 4 FAQs using AI! You can review or edit them in the cards below.', 'infinite-faqs' ) . '</p></div>';
        }
        return;
    }

    // Action B: Delete ALL 4 Predefined FAQ Cards
    if ( isset( $_POST['infinite_faqs_delete_all_cards_submit'] ) ) {
        $empty_cards = array(
            array( 'question' => '', 'answer' => '' ),
            array( 'question' => '', 'answer' => '' ),
            array( 'question' => '', 'answer' => '' ),
            array( 'question' => '', 'answer' => '' ),
        );
        update_option( 'infinite_faqs_static_faqs', $empty_cards );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All 4 predefined FAQ cards have been cleared.', 'infinite-faqs' ) . '</p></div>';
        return;
    }

    // Sanitize card arrays upfront for Action C & Action D
    $questions = isset( $_POST['infinite_faqs_card_q'] ) && is_array( $_POST['infinite_faqs_card_q'] )
        ? array_map( 'sanitize_text_field', wp_unslash( $_POST['infinite_faqs_card_q'] ) )
        : array();
    $answers   = isset( $_POST['infinite_faqs_card_a'] ) && is_array( $_POST['infinite_faqs_card_a'] )
        ? array_map( 'sanitize_textarea_field', wp_unslash( $_POST['infinite_faqs_card_a'] ) )
        : array();

    // Action C: Delete a Single Predefined FAQ Card Slot
    if ( isset( $_POST['infinite_faqs_delete_single_card'] ) && '' !== $_POST['infinite_faqs_delete_single_card'] ) {
        $card_idx = intval( $_POST['infinite_faqs_delete_single_card'] );

        $updated_cards = array();
        for ( $i = 0; $i < 4; $i++ ) {
            if ( $i === $card_idx ) {
                $updated_cards[] = array( 'question' => '', 'answer' => '' );
            } else {
                $updated_cards[] = array(
                    'question' => isset( $questions[ $i ] ) ? $questions[ $i ] : '',
                    'answer'   => isset( $answers[ $i ] ) ? $answers[ $i ] : '',
                );
            }
        }

        update_option( 'infinite_faqs_static_faqs', $updated_cards );
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
            /* translators: %d: FAQ card number (1-4). */
            esc_html__( 'FAQ Card #%d cleared and saved.', 'infinite-faqs' ),
            absint( $card_idx + 1 )
        ) . '</p></div>';
        return;
    }

    // Action D: Save Manual Edits to Heading, Subtitle & 4 FAQ Cards
    if ( isset( $_POST['infinite_faqs_save_static_submit'] ) ) {
        $saved_cards = array();
        for ( $i = 0; $i < 4; $i++ ) {
            $saved_cards[] = array(
                'question' => isset( $questions[ $i ] ) ? $questions[ $i ] : '',
                'answer'   => isset( $answers[ $i ] ) ? $answers[ $i ] : '',
            );
        }

        update_option( 'infinite_faqs_static_faqs', $saved_cards );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shortcode heading, subtitle, and 4 FAQ cards saved.', 'infinite-faqs' ) . '</p></div>';
    }
}

/**
 * Render the Ingestion Engine Page HTML
 */
function infinite_faqs_ingestion_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Dispatch form handlers (each handler verifies its own dedicated nonce)
    infinite_faqs_handle_upload();
    infinite_faqs_handle_delete_file();
    infinite_faqs_handle_static_faqs_actions();

    $retained_url = '';
    $upload_nonce = isset( $_POST['infinite_faqs_upload_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_upload_nonce'] ) ) : '';
    if ( wp_verify_nonce( $upload_nonce, 'infinite_faqs_upload_file' ) && isset( $_POST['infinite_faqs_base_page_url'] ) ) {
        $retained_url = esc_url_raw( wp_unslash( $_POST['infinite_faqs_base_page_url'] ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $files      = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT file_name, page_slug, COUNT(id) as chunks, MAX(created_at) as last_updated FROM %i GROUP BY file_name, page_slug ORDER BY last_updated DESC',
            $table_name
        )
    );

    // Retrieve current Shortcode Heading, Subtitle & 4 FAQ Cards
    $shortcode_heading  = get_option( 'infinite_faqs_shortcode_heading', 'Questions' );
    $shortcode_subtitle = get_option( 'infinite_faqs_shortcode_subtitle', 'The short ones are here. For anything else, ask our AI below.' );
    $static_faqs        = get_option( 'infinite_faqs_static_faqs', array() );
    if ( ! is_array( $static_faqs ) ) {
        $static_faqs = array();
    }

    // Ensure 4 card slots always exist for the UI (defaulting to empty strings so placeholders show)
    for ( $i = 0; $i < 4; $i++ ) {
        if ( ! isset( $static_faqs[ $i ] ) || ! is_array( $static_faqs[ $i ] ) ) {
            $static_faqs[ $i ] = array(
                'question' => '',
                'answer'   => '',
            );
        }
    }

    $selected_source_doc = '__all__';
    $static_nonce        = isset( $_POST['infinite_faqs_static_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_static_nonce'] ) ) : '';
    if ( wp_verify_nonce( $static_nonce, 'infinite_faqs_save_static_faqs' ) && isset( $_POST['infinite_faqs_source_doc'] ) ) {
        $selected_source_doc = sanitize_text_field( wp_unslash( $_POST['infinite_faqs_source_doc'] ) );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Manage Knowledge Base', 'infinite-faqs' ); ?></h1>

        <!-- Click-to-Copy Shortcodes Bar -->
        <div class="card" style="max-width: 1100px; margin: 20px 0 0 0; padding: 18px 20px;">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
                <div>
                    <h2 style="margin: 0 0 4px 0; font-size: 1.1rem;"><?php esc_html_e( 'Available Shortcodes', 'infinite-faqs' ); ?></h2>
                    <p class="description" style="margin: 0;"><?php esc_html_e( 'Click any shortcode below to copy it to your clipboard, then paste it into Gutenberg or Elementor.', 'infinite-faqs' ); ?></p>
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                    <button type="button" class="button ifaq-copy-shortcode-btn" data-shortcode="[infinite_faqs_light]" style="display: inline-flex; align-items: center; gap: 8px; padding: 4px 12px;">
                        <span><?php esc_html_e( 'Light Mode:', 'infinite-faqs' ); ?></span>
                        <code style="background: transparent; padding: 0; font-weight: 600;">[infinite_faqs_light]</code>
                        <span class="ifaq-copy-status" style="color: #2271b1; font-size: 12px;"><?php esc_html_e( 'Copy', 'infinite-faqs' ); ?></span>
                    </button>

                    <button type="button" class="button ifaq-copy-shortcode-btn" data-shortcode="[infinite_faqs_dark]" style="display: inline-flex; align-items: center; gap: 8px; padding: 4px 12px;">
                        <span><?php esc_html_e( 'Dark Mode:', 'infinite-faqs' ); ?></span>
                        <code style="background: transparent; padding: 0; font-weight: 600;">[infinite_faqs_dark]</code>
                        <span class="ifaq-copy-status" style="color: #2271b1; font-size: 12px;"><?php esc_html_e( 'Copy', 'infinite-faqs' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Top Row: Two-Column Layout for Upload Markdown & Indexed Files -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; max-width: 1100px; margin-top: 20px; align-items: start;">
            
            <!-- Left Column: Upload Markdown Documentation -->
            <div class="card" style="max-width: 100%; margin: 0; padding: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e( 'Upload Markdown Documentation', 'infinite-faqs' ); ?></h2>
                <p><?php esc_html_e( 'Upload your .md files and the system will automatically generate search keywords using your selected AI Provider.', 'infinite-faqs' ); ?></p>
                
                <form method="post" enctype="multipart/form-data" action="">
                    <?php wp_nonce_field( 'infinite_faqs_upload_file', 'infinite_faqs_upload_nonce' ); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 130px;"><label for="infinite_faqs_base_page_url"><?php esc_html_e( 'Base Page URL', 'infinite-faqs' ); ?></label></th>
                            <td>
                                <input type="url" name="infinite_faqs_base_page_url" id="infinite_faqs_base_page_url" class="large-text" placeholder="https://example.com/pricing" value="<?php echo esc_attr( $retained_url ); ?>" required />
                                <p class="description"><?php esc_html_e( 'The live URL where this document will live. Essential for generating "Read more" links.', 'infinite-faqs' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 130px;"><label for="infinite_faqs_md_file"><?php esc_html_e( 'Markdown File', 'infinite-faqs' ); ?></label></th>
                            <td>
                                <input type="file" name="infinite_faqs_md_file" id="infinite_faqs_md_file" accept=".md" required />
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button( esc_html__( 'Upload & Process', 'infinite-faqs' ), 'primary', 'infinite_faqs_upload_submit', false ); ?>
                </form>
            </div>

            <!-- Right Column: Indexed Files -->
            <div class="card" style="max-width: 100%; margin: 0; padding: 20px;">
                <h2 style="margin-top: 0;"><?php esc_html_e( 'Indexed Files', 'infinite-faqs' ); ?></h2>
                <p><?php esc_html_e( 'Documents currently chunked and indexed in your WordPress database.', 'infinite-faqs' ); ?></p>
                <table class="wp-list-table widefat fixed striped" style="margin-top: 12px;">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'File Name', 'infinite-faqs' ); ?></th>
                            <th><?php esc_html_e( 'Base URL', 'infinite-faqs' ); ?></th>
                            <th style="width: 65px;"><?php esc_html_e( 'Chunks', 'infinite-faqs' ); ?></th>
                            <th><?php esc_html_e( 'Last Updated', 'infinite-faqs' ); ?></th>
                            <th style="width: 75px; text-align: right;"><?php esc_html_e( 'Action', 'infinite-faqs' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ( ! empty( $files ) ) {
                            foreach ( $files as $file ) {
                                /* translators: %s: Markdown file name to delete. */
                                $delete_confirm_msg = sprintf( __( 'Delete "%s" from your indexed knowledge base?', 'infinite-faqs' ), $file->file_name );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $file->file_name ); ?></td>
                                    <td><a href="<?php echo esc_url( $file->page_slug ); ?>" target="_blank"><?php echo esc_html( $file->page_slug ); ?></a></td>
                                    <td><?php echo esc_html( $file->chunks ); ?></td>
                                    <td><?php echo esc_html( $file->last_updated ); ?></td>
                                    <td style="text-align: right;">
                                        <form method="post" action="" style="display: inline;" onsubmit="return confirm('<?php echo esc_js( $delete_confirm_msg ); ?>');">
                                            <?php wp_nonce_field( 'infinite_faqs_delete_indexed_file', 'infinite_faqs_delete_file_nonce' ); ?>
                                            <input type="hidden" name="infinite_faqs_delete_file_name" value="<?php echo esc_attr( $file->file_name ); ?>" />
                                            <button type="submit" name="infinite_faqs_delete_file_submit" value="1" class="button button-small button-link-delete" style="color: #d63638;">
                                                <?php esc_html_e( 'Delete', 'infinite-faqs' ); ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="5">' . esc_html__( 'No files indexed yet.', 'infinite-faqs' ) . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Shortcode Display & Predefined FAQs Configuration -->
        <div style="max-width: 1100px; margin-top: 40px; padding-top: 25px; border-top: 1px solid #dcdcde;">
            <h2><?php esc_html_e( 'Shortcode Display & Predefined FAQs', 'infinite-faqs' ); ?></h2>
            <p><?php esc_html_e( 'Customize the left-column heading, subtitle, and the 4 predefined FAQ accordion cards displayed by the [infinite_faqs_light] and [infinite_faqs_dark] shortcodes.', 'infinite-faqs' ); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field( 'infinite_faqs_save_static_faqs', 'infinite_faqs_static_nonce' ); ?>

                <!-- Two-Column Row for Section 1 (Heading & Subtitle) and Section 2 (AI Auto-Generator) -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 15px; align-items: stretch;">
                    
                    <!-- 1. Shortcode Heading & Subtitle -->
                    <div class="card" style="max-width: 100%; margin: 0; padding: 20px;">
                        <h3 style="margin-top: 0;"><?php esc_html_e( '1. Shortcode Heading & Subtitle', 'infinite-faqs' ); ?></h3>
                        <p style="margin-bottom: 12px;">
                            <label for="infinite_faqs_shortcode_heading" style="display: block; font-weight: 600; margin-bottom: 4px;">
                                <?php esc_html_e( 'Section Heading (Max 9 chars)', 'infinite-faqs' ); ?>
                            </label>
                            <input 
                                type="text" 
                                name="infinite_faqs_shortcode_heading" 
                                id="infinite_faqs_shortcode_heading" 
                                value="<?php echo esc_attr( $shortcode_heading ); ?>" 
                                maxlength="9" 
                                class="regular-text" 
                                style="width: 100%; max-width: 240px;" 
                                placeholder="Questions" 
                                required 
                            />
                            <span class="description" style="display: block; margin-top: 4px;"><?php esc_html_e( 'e.g., "Questions" or "FAQs".', 'infinite-faqs' ); ?></span>
                        </p>
                        <p style="margin-bottom: 0;">
                            <label for="infinite_faqs_shortcode_subtitle" style="display: block; font-weight: 600; margin-bottom: 4px;">
                                <?php esc_html_e( 'Section Subtitle', 'infinite-faqs' ); ?>
                            </label>
                            <input 
                                type="text" 
                                name="infinite_faqs_shortcode_subtitle" 
                                id="infinite_faqs_shortcode_subtitle" 
                                value="<?php echo esc_attr( $shortcode_subtitle ); ?>" 
                                class="large-text" 
                                placeholder="The short ones are here. For anything else, ask our AI below." 
                                required 
                            />
                        </p>
                    </div>

                    <!-- 2. AI Auto-Generator Bar (No blue border) -->
                    <div class="card" style="max-width: 100%; margin: 0; padding: 20px;">
                        <h3 style="margin-top: 0;"><?php esc_html_e( '2. Auto-Generate 4 FAQs with AI', 'infinite-faqs' ); ?></h3>
                        <p style="margin-bottom: 16px;"><?php esc_html_e( 'Select an uploaded document below and let your configured AI Provider automatically generate 4 questions and answers from its content.', 'infinite-faqs' ); ?></p>
                        
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <label for="infinite_faqs_source_doc" style="display: block; font-weight: 600; margin-bottom: 4px;"><?php esc_html_e( 'Source Document', 'infinite-faqs' ); ?></label>
                                <select name="infinite_faqs_source_doc" id="infinite_faqs_source_doc" style="width: 100%; max-width: 100%;">
                                    <option value="__all__" <?php selected( $selected_source_doc, '__all__' ); ?>><?php esc_html_e( 'All Indexed Documents', 'infinite-faqs' ); ?></option>
                                    <?php if ( ! empty( $files ) ) : ?>
                                        <?php foreach ( $files as $file ) : ?>
                                            <option value="<?php echo esc_attr( $file->file_name ); ?>" <?php selected( $selected_source_doc, $file->file_name ); ?>>
                                                <?php echo esc_html( $file->file_name . ' (' . $file->chunks . ' chunks)' ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div>
                                <button type="submit" name="infinite_faqs_ai_generate_faqs_submit" value="1" class="button button-secondary" <?php disabled( empty( $files ) ); ?>>
                                    <?php esc_html_e( '✨ Generate 4 FAQs with AI', 'infinite-faqs' ); ?>
                                </button>
                            </div>
                        </div>
                        <?php if ( empty( $files ) ) : ?>
                            <p class="description" style="margin-top: 8px; color: #d63638;"><?php esc_html_e( 'Upload at least one .md document above to enable AI FAQ generation.', 'infinite-faqs' ); ?></p>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- 3. 4 Question & Answer Cards Grid -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 28px; margin-bottom: 12px;">
                    <h3 style="margin: 0;"><?php esc_html_e( '3. Predefined Question & Answer Cards (4 Slots)', 'infinite-faqs' ); ?></h3>
                    <button type="submit" name="infinite_faqs_delete_all_cards_submit" value="1" class="button button-link-delete" style="color: #d63638;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to clear all 4 predefined FAQ cards?', 'infinite-faqs' ) ); ?>');">
                        <?php esc_html_e( 'Delete All Predefined FAQs', 'infinite-faqs' ); ?>
                    </button>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px;">
                    <?php for ( $i = 0; $i < 4; $i++ ) : ?>
                        <div class="card" style="max-width: 100%; margin: 0; padding: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid #f0f0f1; padding-bottom: 8px;">
                                <h4 style="margin: 0;">
                                    <?php
                                    echo sprintf(
                                        /* translators: %d: FAQ card number (1-4). */
                                        esc_html__( 'FAQ Card #%d', 'infinite-faqs' ),
                                        absint( $i + 1 )
                                    );
                                    ?>
                                </h4>
                                <button type="submit" name="infinite_faqs_delete_single_card" value="<?php echo esc_attr( $i ); ?>" class="button-link button-link-delete" style="color: #d63638; text-decoration: none; font-size: 12px;">
                                    <?php esc_html_e( 'Delete Card', 'infinite-faqs' ); ?>
                                </button>
                            </div>
                            <p style="margin-bottom: 12px;">
                                <label for="infinite_faqs_card_q_<?php echo esc_attr( $i ); ?>" style="display: block; font-weight: 600; margin-bottom: 4px;">
                                    <?php esc_html_e( 'Question', 'infinite-faqs' ); ?>
                                </label>
                                <input 
                                    type="text" 
                                    name="infinite_faqs_card_q[<?php echo esc_attr( $i ); ?>]" 
                                    id="infinite_faqs_card_q_<?php echo esc_attr( $i ); ?>" 
                                    value="<?php echo esc_attr( $static_faqs[ $i ]['question'] ); ?>" 
                                    class="large-text" 
                                    placeholder="<?php echo esc_attr__( 'e.g., What is your refund policy?', 'infinite-faqs' ); ?>" 
                                />
                            </p>
                            <p style="margin-bottom: 0;">
                                <label for="infinite_faqs_card_a_<?php echo esc_attr( $i ); ?>" style="display: block; font-weight: 600; margin-bottom: 4px;">
                                    <?php esc_html_e( 'Answer', 'infinite-faqs' ); ?>
                                </label>
                                <textarea 
                                    name="infinite_faqs_card_a[<?php echo esc_attr( $i ); ?>]" 
                                    id="infinite_faqs_card_a_<?php echo esc_attr( $i ); ?>" 
                                    rows="4" 
                                    class="large-text" 
                                    placeholder="<?php echo esc_attr__( 'Enter the concise answer displayed when this FAQ is expanded...', 'infinite-faqs' ); ?>"
                                ><?php echo esc_textarea( $static_faqs[ $i ]['answer'] ); ?></textarea>
                            </p>
                        </div>
                    <?php endfor; ?>
                </div>

                <p class="submit" style="margin-top: 20px;">
                    <button type="submit" name="infinite_faqs_save_static_submit" value="1" class="button button-primary button-large">
                        <?php esc_html_e( 'Save Heading & FAQ Cards', 'infinite-faqs' ); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <script>
    (function() {
        document.querySelectorAll('.ifaq-copy-shortcode-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var code = btn.getAttribute('data-shortcode');
                var status = btn.querySelector('.ifaq-copy-status');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(code).then(function() {
                        if (status) {
                            status.textContent = 'Copied!';
                            status.style.color = '#00a32a';
                            setTimeout(function() {
                                status.textContent = 'Copy';
                                status.style.color = '#2271b1';
                            }, 2000);
                        }
                    });
                }
            });
        });
    })();
    </script>
    <?php
}

