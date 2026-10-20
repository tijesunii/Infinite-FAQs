/**
 * Infinite FAQs for WordPress - Vanilla JS Frontend Controller
 *
 * Strictly Vanilla JavaScript (ES6+). Zero external dependencies.
 * Uses event delegation so shortcodes rendered dynamically via AJAX
 * (such as inside the Elementor live editor) work automatically.
 */

(function () {
	'use strict';

	/**
	 * Retrieve configuration from window.infiniteFaqsData or fallback to data-config attribute.
	 *
	 * @param {HTMLElement} wrapper The .infinite-faqs-wrapper element.
	 * @return {Object|null}
	 */
	function getConfig(wrapper) {
		if (typeof window.infiniteFaqsData !== 'undefined' && window.infiniteFaqsData.restUrl) {
			return window.infiniteFaqsData;
		}
		if (wrapper && wrapper.dataset && wrapper.dataset.config) {
			try {
				return JSON.parse(wrapper.dataset.config);
			} catch (e) {
				return null;
			}
		}
		return null;
	}

	/**
	 * 1. Event Delegation for Static FAQ Accordions
	 * Works immediately for static pages AND dynamically injected Elementor widgets.
	 */
	document.addEventListener('click', function (e) {
		const button = e.target.closest('.infinite-faqs-accordion-header');
		if (!button) {
			return;
		}

		const wrapper = button.closest('.infinite-faqs-wrapper');
		const item = button.closest('.infinite-faqs-accordion-item');
		if (!wrapper || !item) {
			return;
		}

		const content = item.querySelector('.infinite-faqs-accordion-content');
		const isActive = item.classList.contains('active');

		// Collapse all other accordion items within this instance
		wrapper.querySelectorAll('.infinite-faqs-accordion-item').forEach(function (otherItem) {
			otherItem.classList.remove('active');
			const otherBtn = otherItem.querySelector('.infinite-faqs-accordion-header');
			const otherContent = otherItem.querySelector('.infinite-faqs-accordion-content');
			if (otherBtn) {
				otherBtn.setAttribute('aria-expanded', 'false');
			}
			if (otherContent) {
				otherContent.style.maxHeight = null;
			}
		});

		// Expand clicked item if it wasn't already open
		if (!isActive && content) {
			item.classList.add('active');
			button.setAttribute('aria-expanded', 'true');
			content.style.maxHeight = content.scrollHeight + 'px';
		}
	});

	/**
	 * 2. Event Delegation for Open-Ended AI Query Form Submission
	 */
	document.addEventListener('submit', async function (e) {
		const form = e.target.closest('.infinite-faqs-ask-form');
		if (!form) {
			return;
		}

		e.preventDefault();

		const wrapper = form.closest('.infinite-faqs-wrapper');
		if (!wrapper) {
			return;
		}

		const config = getConfig(wrapper);
		if (!config || !config.restUrl) {
			return;
		}

		const input = form.querySelector('.infinite-faqs-input');
		const submitBtn = form.querySelector('.infinite-faqs-submit-btn');
		const honeypot = form.querySelector('.infinite-faqs-honeypot');
		const loadingEl = wrapper.querySelector('.infinite-faqs-loading');
		const responseContainer = wrapper.querySelector('.infinite-faqs-response-container');
		const thoughtEl = wrapper.querySelector('.infinite-faqs-ai-thought');
		const answerTextEl = wrapper.querySelector('.infinite-faqs-answer-text');
		const actionWrapperEl = wrapper.querySelector('.infinite-faqs-action-wrapper');

		const question = input ? input.value.trim() : '';
		const honeypotVal = honeypot ? honeypot.value : '';
		const i18n = config.i18n || {};

		// Validate non-empty / minimum length (PRD Section 9 & Layer 4 Security)
		if (question.length < 5) {
			renderResponse(
				{
					thoughtText: '',
					answer: i18n.emptyError || 'Please enter a question (at least 5 characters).',
					actionUrl: '',
					actionLabel: '',
					isError: true
				},
				{ responseContainer, thoughtEl, answerTextEl, actionWrapperEl }
			);
			return;
		}

		// Enter Loading State (AC-3.3)
		setLoadingState(true, { input, submitBtn, loadingEl, responseContainer });
		const startTime = performance.now();

		try {
			const headers = {
				'Content-Type': 'application/json'
			};

			// Pass standard WP REST nonce header so logged-in sessions authenticate cleanly
			if (config.wpRestNonce) {
				headers['X-WP-Nonce'] = config.wpRestNonce;
			}

			const response = await fetch(config.restUrl, {
				method: 'POST',
				headers: headers,
				body: JSON.stringify({
					question: question,
					_ifaq_token: config.token,
					_ifaq_email_check: honeypotVal
				}),
				credentials: 'same-origin'
			});

			const elapsedSeconds = Math.max(1, Math.round((performance.now() - startTime) / 1000));
			const data = await response.json();

			setLoadingState(false, { input, submitBtn, loadingEl, responseContainer });

			// Handle HTTP-level errors (400, 403, 429, 500)
			if (!response.ok) {
				const errorMessage =
					data && data.message
						? data.message
						: i18n.genericError || 'We are unable to process your question at this time.';

				renderResponse(
					{
						thoughtText: '',
						answer: errorMessage,
						actionUrl: config.supportUrl || '',
						actionLabel: i18n.contactBtn || 'Contact Support →',
						isFallback: true
					},
					{ responseContainer, thoughtEl, answerTextEl, actionWrapperEl }
				);
				return;
			}

			// Handle Successful Retrieval + AI Answer (AC-5)
			if (data && data.success) {
				renderResponse(
					{
						thoughtText: 'Thought for ' + elapsedSeconds + 's',
						answer: data.answer || '',
						actionUrl: data.citation || '',
						actionLabel: i18n.readMore || 'Read more →',
						isFallback: false
					},
					{ responseContainer, thoughtEl, answerTextEl, actionWrapperEl }
				);
			} else {
				// Handle Graceful Fallback / Anti-Hallucination Trigger (AC-6)
				const fallbackSupportUrl = (data && data.citation) || config.supportUrl || '';
				renderResponse(
					{
						thoughtText: 'Thought for ' + elapsedSeconds + 's',
						answer:
							(data && data.answer) ||
							"I couldn't find that information in the available documentation.",
						actionUrl: fallbackSupportUrl,
						actionLabel: i18n.contactBtn || 'Contact Support →',
						isFallback: true
					},
					{ responseContainer, thoughtEl, answerTextEl, actionWrapperEl }
				);
			}
		} catch (err) {
			// Network / Timeout Failure (PRD Section 9: API Timeout / Failure)
			setLoadingState(false, { input, submitBtn, loadingEl, responseContainer });

			renderResponse(
				{
					thoughtText: '',
					answer: i18n.genericError || 'We are unable to process your question at this time.',
					actionUrl: config.supportUrl || '',
					actionLabel: i18n.contactBtn || 'Contact Support →',
					isFallback: true
				},
				{ responseContainer, thoughtEl, answerTextEl, actionWrapperEl }
			);
		}
	});

	/**
	 * Toggle UI controls during async query processing.
	 *
	 * @param {boolean} isLoading Whether a query is currently in-flight.
	 * @param {Object} elements DOM references.
	 */
	function setLoadingState(isLoading, elements) {
		if (elements.input) {
			elements.input.disabled = isLoading;
		}
		if (elements.submitBtn) {
			elements.submitBtn.disabled = isLoading;
		}
		if (elements.loadingEl) {
			elements.loadingEl.style.display = isLoading ? 'flex' : 'none';
		}
		if (isLoading && elements.responseContainer) {
			elements.responseContainer.style.display = 'none';
		}
	}

	/**
	 * Safely populate and expand the response container without innerHTML XSS risks.
	 *
	 * @param {Object} payload Data to display.
	 * @param {Object} elements DOM references.
	 */
	function renderResponse(payload, elements) {
		if (!elements.responseContainer) {
			return;
		}

		// 1. "Thought for Xs" indicator (from Acme Hosting HTML design)
		if (elements.thoughtEl) {
			if (payload.thoughtText) {
				elements.thoughtEl.textContent = payload.thoughtText;
				elements.thoughtEl.style.display = 'block';
			} else {
				elements.thoughtEl.textContent = '';
				elements.thoughtEl.style.display = 'none';
			}
		}

		// 2. Answer text (strictly using textContent to prevent XSS)
		if (elements.answerTextEl) {
			elements.answerTextEl.textContent = payload.answer || '';
		}

		// 3. Action Link: Either "Read more →" citation or "Contact Support →" button
		if (elements.actionWrapperEl) {
			elements.actionWrapperEl.textContent = '';

			if (payload.actionUrl) {
				const link = document.createElement('a');
				link.href = payload.actionUrl;
				link.className = payload.isFallback
					? 'infinite-faqs-support-link'
					: 'infinite-faqs-citation-link';
				link.textContent = payload.actionLabel;
				elements.actionWrapperEl.appendChild(link);
			}
		}

		elements.responseContainer.classList.toggle('is-fallback', Boolean(payload.isFallback));
		elements.responseContainer.classList.toggle('is-error', Boolean(payload.isError));
		elements.responseContainer.style.display = 'block';
	}
})();
