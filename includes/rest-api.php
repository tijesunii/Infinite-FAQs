<?php
/**
 * Infinite FAQs for WordPress - REST API Endpoint & Security
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register the custom REST route.
 */
add_action( 'rest_api_init', 'infinite_faqs_register_rest_routes' );
function infinite_faqs_register_rest_routes() {
    register_rest_route( 'infinite-faqs/v1', '/ask', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'infinite_faqs_process_question',
        'permission_callback' => 'infinite_faqs_security_checks',
    ) );
}

/**
 * Generate a stateless HMAC security token tied to the current day and site salt.
 * Works identically for both logged-in admins (e.g. inside Elementor/Gutenberg preview)
 * and logged-out visitors without conflicting with WordPress core's _wpnonce cookie check.
 */
function infinite_faqs_create_security_token() {
    $time_tick = ceil( time() / ( 12 * HOUR_IN_SECONDS ) );
    return hash_hmac( 'sha256', 'infinite_faqs_ask_' . $time_tick, wp_salt( 'nonce' ) );
}

/**
 * Verify the security token (checks current 12h window and previous 12h window).
 */
function infinite_faqs_verify_security_token( $token ) {
    if ( empty( $token ) || ! is_string( $token ) ) {
        return false;
    }
    $time_tick = ceil( time() / ( 12 * HOUR_IN_SECONDS ) );
    $expected_current  = hash_hmac( 'sha256', 'infinite_faqs_ask_' . $time_tick, wp_salt( 'nonce' ) );
    $expected_previous = hash_hmac( 'sha256', 'infinite_faqs_ask_' . ( $time_tick - 1 ), wp_salt( 'nonce' ) );

    return hash_equals( $expected_current, $token ) || hash_equals( $expected_previous, $token );
}

/**
 * Layers 1 & 2: Header/Origin Validation & Signed Token Check
 */
function infinite_faqs_security_checks( WP_REST_Request $request ) {
    // Layer 1: Strict Header & Origin Validation (CORS equivalent)
    $referer  = $request->get_header( 'referer' );
    $origin   = $request->get_header( 'origin' );
    $site_url = site_url();
    $home_url = home_url();
    
    $valid_origin = false;
    if ( $referer && ( strpos( $referer, $site_url ) === 0 || strpos( $referer, $home_url ) === 0 ) ) {
        $valid_origin = true;
    } elseif ( $origin && ( strpos( $origin, $site_url ) === 0 || strpos( $origin, $home_url ) === 0 ) ) {
        $valid_origin = true;
    }

    if ( ! $valid_origin ) {
        return new WP_Error( 'rest_forbidden', 'Invalid origin. Requests must originate from the host site.', array( 'status' => 403 ) );
    }

    // Layer 2: Short-Lived Signed Tokens
    // Accept either our dedicated _ifaq_token or a valid WP nonce
    $token = $request->get_param( '_ifaq_token' );
    $wp_nonce = $request->get_param( '_wpnonce' );

    if ( ! infinite_faqs_verify_security_token( $token ) && ! wp_verify_nonce( $wp_nonce, 'infinite_faqs_ask' ) ) {
        return new WP_Error( 'rest_forbidden', 'Invalid security token. Please refresh the page.', array( 'status' => 403 ) );
    }

    return true;
}

/**
 * Primary Callback: Process the question after initial security clears.
 */
function infinite_faqs_process_question( WP_REST_Request $request ) {
    // Layer 3: The Honeypot Trap
    $honeypot = $request->get_param( '_ifaq_email_check' );
    if ( ! empty( $honeypot ) ) {
        // Dumb bot detected. Silently drop the request but return a fake 200 OK.
        return rest_ensure_response( array(
            'success'  => true,
            'answer'   => 'Thank you for your question.',
            'citation' => ''
        ) );
    }

    // Layer 4: Payload Validation & Anti-Spam
    $question = $request->get_param( 'question' );
    
    // Check Length (Prevent Context Stuffing)
    if ( empty( $question ) || mb_strlen( $question ) < 5 || mb_strlen( $question ) > 250 ) {
        return new WP_Error( 'rest_invalid_param', 'Your question must be between 5 and 250 characters.', array( 'status' => 400 ) );
    }
    
    // Check for HTML and Links (Prevent SEO Spam/XSS)
    if ( preg_match( '/https?:\/\//i', $question ) || preg_match( '/<[^>]*>/', $question ) ) {
        return new WP_Error( 'rest_invalid_param', 'Links and HTML markup are not permitted in questions.', array( 'status' => 400 ) );
    }
    
    // Final sanitization
    $sanitized_question = sanitize_text_field( $question );

    // Layer 5: Composite Fingerprint Rate Limiting
    // Support Cloudflare if present, otherwise fallback to standard REMOTE_ADDR
    $ip = '';
    if ( isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
    } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    }
    $ua = $request->get_header( 'user_agent' );
    
    // Create unique hash
    $fingerprint = md5( $ip . $ua );
    $transient_key = 'ifaq_rl_' . $fingerprint;
    
    $attempts = (int) get_transient( $transient_key );
    
    if ( $attempts >= 15 ) { // Generous failsafe: 15 questions per minute per fingerprint
        return new WP_Error( 'rest_too_many_requests', 'You are asking questions too quickly. Please wait a moment before asking another.', array( 'status' => 429 ) );
    }
    
    // Increment the counter and set expiration to 60 seconds
    set_transient( $transient_key, $attempts + 1, MINUTE_IN_SECONDS );

    // Milestone 2.2: Database Retrieval Engine
    global $wpdb;
    $table_name = $wpdb->prefix . 'infinite_faqs_docs';

    // 1. Verify the Knowledge Base isn't empty
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $kb_count    = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(id) FROM %i', $table_name ) );
    $support_url = get_option( 'infinite_faqs_support_url', '' );
    
    if ( ! $kb_count || $kb_count == 0 ) {
        // KB is empty. Trigger fallback immediately.
        return rest_ensure_response( array(
            'success'  => false,
            'answer'   => 'I couldn\'t find that information in the available documentation.',
            'citation' => $support_url
        ) );
    }

    // 2. Execute Full-Text Search (MATCH AGAINST)
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $best_chunk = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT *, MATCH(keywords) AGAINST(%s IN NATURAL LANGUAGE MODE) as relevance_score 
             FROM %i 
             WHERE MATCH(keywords) AGAINST(%s IN NATURAL LANGUAGE MODE) 
             ORDER BY relevance_score DESC 
             LIMIT 1',
            $sanitized_question,
            $table_name,
            $sanitized_question
        )
    );

    // 3. Handle Zero-Match Scenario
    if ( ! $best_chunk ) {
        // No relevant keywords matched the query. Save an API credit and fallback.
        return rest_ensure_response( array(
            'success'  => false,
            'answer'   => 'I couldn\'t find that information in the available documentation.',
            'citation' => $support_url
        ) );
    }

    // Milestone 2.3 & 2.4: AI Execution & Response Formatting
    $provider = get_option( 'infinite_faqs_ai_provider', 'openai' );
    $encrypted_key = get_option( 'infinite_faqs_api_key', '' );
    $api_key = infinite_faqs_decrypt_data( $encrypted_key );
    
    if ( empty( $api_key ) ) {
        return rest_ensure_response( array(
            'success'  => false,
            'answer'   => 'The AI Knowledge Base is currently unconfigured. Please contact support.',
            'citation' => $support_url
        ) );
    }

    $verbosity = get_option( 'infinite_faqs_answer_length', 'Brief' );
    $length_instruction = ( $verbosity === 'Brief' ) ? 'Keep your answer concise and strictly to the point.' : 'Provide a detailed, comprehensive answer based on the context.';

    // Construct the strict system prompt (Zero Hallucination Rule)
    $system_prompt = "You are a helpful support assistant. Answer the user's question using ONLY the provided documentation context below. Do NOT infer, guess, or use outside knowledge. $length_instruction If the provided context does not contain the exact answer, reply EXACTLY with the phrase: 'I couldn't find that information in the available documentation.'\n\nContext:\n" . $best_chunk->chunk_text;
    $user_prompt = $sanitized_question;

    $ai_response = '';

    // Execute API Call based on Provider
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
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $user_prompt )
                )
            )),
            'timeout' => 20,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $ai_response = trim( $body['choices'][0]['message']['content'] );
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
                'max_tokens' => 300,
                'system'     => $system_prompt,
                'messages'   => array(
                    array( 'role' => 'user', 'content' => $user_prompt )
                )
            )),
            'timeout' => 20,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['content'][0]['text'] ) ) {
                $ai_response = trim( $body['content'][0]['text'] );
            }
        }
    } elseif ( $provider === 'gemini' ) {
        // phpcs:ignore PluginCheck.CodeAnalysis.AIProvider.DirectIntegration
        $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $api_key, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body' => wp_json_encode( array(
                'systemInstruction' => array( 'parts' => array( array( 'text' => $system_prompt ) ) ),
                'contents' => array(
                    array( 'role' => 'user', 'parts' => array( array( 'text' => $user_prompt ) ) )
                )
            )),
            'timeout' => 20,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
                $ai_response = trim( $body['candidates'][0]['content']['parts'][0]['text'] );
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
                    array( 'role' => 'system', 'content' => $system_prompt ),
                    array( 'role' => 'user', 'content' => $user_prompt )
                )
            )),
            'timeout' => 20,
        ));
        if ( ! is_wp_error( $response ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['choices'][0]['message']['content'] ) ) {
                $ai_response = trim( $body['choices'][0]['message']['content'] );
            }
        }
    }

    if ( empty( $ai_response ) ) {
        return new WP_Error( 'rest_api_error', 'Failed to communicate with the AI Provider.', array( 'status' => 500 ) );
    }

    // Check for fallback string (Anti-Hallucination trigger)
    if ( stripos( $ai_response, "I couldn't find that information" ) !== false ) {
        return rest_ensure_response( array(
            'success'  => false,
            'answer'   => 'I couldn\'t find that information in the available documentation.',
            'citation' => $support_url
        ) );
    }

    // Success Response Format
    return rest_ensure_response( array(
        'success'  => true,
        'answer'   => sanitize_textarea_field( $ai_response ),
        'citation' => esc_url( $best_chunk->page_slug . '#' . $best_chunk->anchor_id )
    ) );
}
