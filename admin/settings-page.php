<?php
/**
 * Infinite FAQs - Admin Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function infinite_faqs_add_admin_menu() {
    add_menu_page(
        esc_html__( 'Infinite FAQs Settings', 'infinite-faqs' ),
        esc_html__( 'Infinite FAQs', 'infinite-faqs' ),
        'manage_options',
        'infinite-faqs',
        'infinite_faqs_settings_page_html',
        'dashicons-testimonial',
        80
    );
}
add_action( 'admin_menu', 'infinite_faqs_add_admin_menu' );

function infinite_faqs_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['infinite_faqs_submit'] ) ) {
        $settings_nonce = isset( $_POST['infinite_faqs_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['infinite_faqs_settings_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $settings_nonce, 'infinite_faqs_save_settings' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'infinite-faqs' ) );
        }

        if ( isset( $_POST['infinite_faqs_ai_provider'] ) ) {
            update_option( 'infinite_faqs_ai_provider', sanitize_text_field( wp_unslash( $_POST['infinite_faqs_ai_provider'] ) ) );
        }

        if ( ! empty( $_POST['infinite_faqs_api_key'] ) ) {
            $raw_api_key = sanitize_text_field( wp_unslash( $_POST['infinite_faqs_api_key'] ) );
            if ( false === strpos( $raw_api_key, '*' ) ) {
                $encrypted_key = infinite_faqs_encrypt_data( $raw_api_key );
                update_option( 'infinite_faqs_api_key', $encrypted_key );
            }
        }

        if ( isset( $_POST['infinite_faqs_support_url'] ) ) {
            update_option( 'infinite_faqs_support_url', sanitize_url( wp_unslash( $_POST['infinite_faqs_support_url'] ) ) );
        }

        if ( isset( $_POST['infinite_faqs_answer_length'] ) ) {
            update_option( 'infinite_faqs_answer_length', sanitize_text_field( wp_unslash( $_POST['infinite_faqs_answer_length'] ) ) );
        }

        $keep_data = isset( $_POST['infinite_faqs_keep_data_on_uninstall'] ) ? '1' : '0';
        update_option( 'infinite_faqs_keep_data_on_uninstall', $keep_data );

        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved securely.', 'infinite-faqs' ) . '</p></div>';
    }

    $current_provider = get_option( 'infinite_faqs_ai_provider', 'openai' );
    $answer_length    = get_option( 'infinite_faqs_answer_length', 'Brief' );
    $encrypted_key    = get_option( 'infinite_faqs_api_key', '' );
    $decrypted_key    = infinite_faqs_decrypt_data( $encrypted_key );
    $masked_key       = infinite_faqs_mask_api_key( $decrypted_key );
    $support_url      = get_option( 'infinite_faqs_support_url', '' );
    $keep_data        = get_option( 'infinite_faqs_keep_data_on_uninstall', '0' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Infinite FAQs Settings', 'infinite-faqs' ); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field( 'infinite_faqs_save_settings', 'infinite_faqs_settings_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="infinite_faqs_ai_provider"><?php esc_html_e( 'AI Provider', 'infinite-faqs' ); ?></label></th>
                    <td>
                        <select name="infinite_faqs_ai_provider" id="infinite_faqs_ai_provider">
                            <option value="openai" <?php selected( $current_provider, 'openai' ); ?>>OpenAI</option>
                            <option value="anthropic" <?php selected( $current_provider, 'anthropic' ); ?>>Anthropic</option>
                            <option value="gemini" <?php selected( $current_provider, 'gemini' ); ?>>Gemini</option>
                            <option value="deepseek" <?php selected( $current_provider, 'deepseek' ); ?>>DeepSeek</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the AI provider you wish to use for generating answers.', 'infinite-faqs' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="infinite_faqs_answer_length"><?php esc_html_e( 'Answer Length', 'infinite-faqs' ); ?></label></th>
                    <td>
                        <select name="infinite_faqs_answer_length" id="infinite_faqs_answer_length">
                            <option value="Brief" <?php selected( $answer_length, 'Brief' ); ?>>Brief (Concise)</option>
                            <option value="Standard" <?php selected( $answer_length, 'Standard' ); ?>>Standard (Detailed)</option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select your preferred verbosity for generated answers.', 'infinite-faqs' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="infinite_faqs_api_key"><?php esc_html_e( 'API Key', 'infinite-faqs' ); ?></label></th>
                    <td>
                        <input type="password" name="infinite_faqs_api_key" id="infinite_faqs_api_key" value="<?php echo esc_attr( $masked_key ); ?>" class="regular-text" autocomplete="off" />
                        <p class="description"><?php esc_html_e( 'Your API Key is stored securely. To update, simply enter a new key.', 'infinite-faqs' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="infinite_faqs_support_url"><?php esc_html_e( 'Contact Support URL', 'infinite-faqs' ); ?></label></th>
                    <td>
                        <input type="url" name="infinite_faqs_support_url" id="infinite_faqs_support_url" value="<?php echo esc_url( $support_url ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'The fallback URL users are directed to if the AI cannot find an answer in your documentation.', 'infinite-faqs' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Data Retention', 'infinite-faqs' ); ?></th>
                    <td>
                        <label for="infinite_faqs_keep_data_on_uninstall">
                            <input type="checkbox" name="infinite_faqs_keep_data_on_uninstall" id="infinite_faqs_keep_data_on_uninstall" value="1" <?php checked( $keep_data, '1' ); ?> />
                            <?php esc_html_e( 'Keep data on uninstall', 'infinite-faqs' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'If checked, your custom knowledge base tables and settings will NOT be deleted when you uninstall the plugin.', 'infinite-faqs' ); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button( esc_html__( 'Save Settings', 'infinite-faqs' ), 'primary', 'infinite_faqs_submit' ); ?>
        </form>
    </div>
    <?php
}
