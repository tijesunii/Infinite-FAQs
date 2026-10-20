<?php
/**
 * Infinite FAQs - Shortcode Renderer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function infinite_faqs_register_shortcodes() {
	add_shortcode( 'infinite_faqs', 'infinite_faqs_render_shortcode' );
	add_shortcode( 'infinite_faqs_light', 'infinite_faqs_render_light_shortcode' );
	add_shortcode( 'infinite_faqs_dark', 'infinite_faqs_render_dark_shortcode' );
}
add_action( 'init', 'infinite_faqs_register_shortcodes' );

function infinite_faqs_register_frontend_assets() {
	wp_register_style(
		'infinite-faqs-style',
		INFINITE_FAQS_PLUGIN_URL . 'assets/css/infinite-faqs.css',
		array(),
		INFINITE_FAQS_VERSION
	);

	wp_register_script(
		'infinite-faqs-script',
		INFINITE_FAQS_PLUGIN_URL . 'assets/js/infinite-faqs.js',
		array(),
		INFINITE_FAQS_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'infinite_faqs_register_frontend_assets' );
add_action( 'elementor/frontend/after_enqueue_styles', 'infinite_faqs_enqueue_frontend_assets' );
add_action( 'elementor/preview/enqueue_styles', 'infinite_faqs_enqueue_frontend_assets' );

function infinite_faqs_get_frontend_config() {
	return array(
		'restUrl'     => esc_url_raw( rest_url( 'infinite-faqs/v1/ask' ) ),
		'token'       => infinite_faqs_create_security_token(),
		'wpRestNonce' => wp_create_nonce( 'wp_rest' ),
		'supportUrl'  => esc_url_raw( get_option( 'infinite_faqs_support_url', '' ) ),
		'i18n'        => array(
			'emptyError'   => __( 'Please enter a question (at least 5 characters).', 'infinite-faqs' ),
			'genericError' => __( 'We are unable to process your question at this time.', 'infinite-faqs' ),
			'readMore'     => __( 'Read more →', 'infinite-faqs' ),
			'contactBtn'   => __( 'Contact Support →', 'infinite-faqs' ),
		),
	);
}

function infinite_faqs_enqueue_frontend_assets() {
	if ( ! wp_style_is( 'infinite-faqs-style', 'registered' ) ) {
		infinite_faqs_register_frontend_assets();
	}

	wp_enqueue_style( 'infinite-faqs-style' );

	if ( ! wp_script_is( 'infinite-faqs-script', 'enqueued' ) ) {
		wp_enqueue_script( 'infinite-faqs-script' );

		wp_localize_script(
			'infinite-faqs-script',
			'infiniteFaqsData',
			infinite_faqs_get_frontend_config()
		);
	}
}

function infinite_faqs_render_light_shortcode( $atts = array() ) {
	$atts          = is_array( $atts ) ? $atts : array();
	$atts['theme'] = 'light';
	return infinite_faqs_render_shortcode( $atts );
}

function infinite_faqs_render_dark_shortcode( $atts = array() ) {
	$atts          = is_array( $atts ) ? $atts : array();
	$atts['theme'] = 'dark';
	return infinite_faqs_render_shortcode( $atts );
}

function infinite_faqs_render_shortcode( $atts = array() ) {
	infinite_faqs_enqueue_frontend_assets();

	$saved_heading = get_option( 'infinite_faqs_shortcode_heading', 'Questions' );
	if ( empty( $saved_heading ) ) {
		$saved_heading = 'Questions';
	}

	$saved_subtitle = get_option( 'infinite_faqs_shortcode_subtitle', 'The short ones are here. For anything else, ask our AI below.' );
	if ( '' === trim( $saved_subtitle ) ) {
		$saved_subtitle = 'The short ones are here. For anything else, ask our AI below.';
	}

	$atts = shortcode_atts(
		array(
			'theme'    => 'light',
			'title'    => $saved_heading,
			'subtitle' => $saved_subtitle,
		),
		$atts,
		'infinite_faqs'
	);

	$heading_text = mb_substr( trim( $atts['title'] ), 0, 9 );
	if ( '' === $heading_text ) {
		$heading_text = 'Questions';
	}

	$theme_mode  = ( 'dark' === strtolower( $atts['theme'] ) ) ? 'dark' : 'light';
	$theme_class = 'infinite-faqs-theme-' . $theme_mode;
	$config_json = wp_json_encode( infinite_faqs_get_frontend_config() );

	$static_faqs = get_option( 'infinite_faqs_static_faqs', array() );
	if ( ! is_array( $static_faqs ) ) {
		$static_faqs = array();
	}

	ob_start();
	?>
	<div class="infinite-faqs-wrapper <?php echo esc_attr( $theme_class ); ?>" data-theme="<?php echo esc_attr( $theme_mode ); ?>" data-config="<?php echo esc_attr( $config_json ); ?>">
		<div class="infinite-faqs-container">
			<div class="infinite-faqs-left-col">
				<h2 class="infinite-faqs-title"><?php echo esc_html( $heading_text ); ?></h2>
				<p class="infinite-faqs-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
			</div>

			<div class="infinite-faqs-right-col">
				<div class="infinite-faqs-accordion-list">
					<?php
					foreach ( $static_faqs as $faq ) :
						$q_text = isset( $faq['question'] ) ? trim( $faq['question'] ) : '';
						$a_text = isset( $faq['answer'] ) ? trim( $faq['answer'] ) : '';
						if ( '' === $q_text && '' === $a_text ) {
							continue;
						}
						?>
						<div class="infinite-faqs-accordion-item">
							<button type="button" class="infinite-faqs-accordion-header" aria-expanded="false">
								<span class="infinite-faqs-question-text"><?php echo esc_html( $q_text ); ?></span>
								<span class="infinite-faqs-icon-container" aria-hidden="true">
									<svg class="infinite-faqs-icon infinite-faqs-icon-inactive" viewBox="0 0 24 24">
										<path d="M6 9l6 6 6-6" />
									</svg>
									<svg class="infinite-faqs-icon infinite-faqs-icon-active" viewBox="0 0 24 24">
										<path d="M5 12h14M12 5l7 7-7 7" />
									</svg>
								</span>
							</button>
							<div class="infinite-faqs-accordion-content">
								<p><?php echo esc_html( $a_text ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="infinite-faqs-ask-section">
					<form class="infinite-faqs-ask-form" novalidate>
						<div class="infinite-faqs-hp-wrap" aria-hidden="true">
							<label for="ifaq_email_check_<?php echo esc_attr( $theme_mode ); ?>">Do not fill this field</label>
							<input type="text" name="_ifaq_email_check" id="ifaq_email_check_<?php echo esc_attr( $theme_mode ); ?>" class="infinite-faqs-honeypot" value="" tabindex="-1" autocomplete="off" />
						</div>

						<div class="infinite-faqs-input-wrapper">
							<input
								type="text"
								name="question"
								class="infinite-faqs-input"
								placeholder="<?php echo esc_attr__( 'Ask a question about our product...', 'infinite-faqs' ); ?>"
								aria-label="<?php echo esc_attr__( 'Ask a question about our product', 'infinite-faqs' ); ?>"
								maxlength="250"
								required
							/>
							<button type="submit" class="infinite-faqs-submit-btn" aria-label="<?php echo esc_attr__( 'Submit question', 'infinite-faqs' ); ?>">
								<span class="infinite-faqs-icon-container">
									<svg class="infinite-faqs-icon" viewBox="0 0 24 24">
										<path d="M5 12h14M12 5l7 7-7 7" />
									</svg>
								</span>
							</button>
						</div>
					</form>

					<div class="infinite-faqs-loading" aria-live="polite">
						<span class="infinite-faqs-spinner"></span>
						<span class="infinite-faqs-loading-text"><?php esc_html_e( 'Searching docs...', 'infinite-faqs' ); ?></span>
					</div>

					<div class="infinite-faqs-response-container" aria-live="polite">
						<p class="infinite-faqs-ai-thought"></p>
						<div class="infinite-faqs-answer-text"></div>
						<div class="infinite-faqs-action-wrapper"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
