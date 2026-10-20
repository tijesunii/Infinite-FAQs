=== Infinite FAQs ===
Contributors: thewpbro
Tags: faq, ai, knowledge-base, accordion, markdown
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered FAQ knowledge base using your own API key (BYOK) to generate accurate answers from your Markdown docs without hallucinations.

== Description ==

**Infinite FAQs** combines a clean, responsive 4-card FAQ accordion with an interactive AI search bar backed directly by your own Markdown (`.md`) documentation.

Instead of relying on third-party SaaS subscriptions or vector databases, Infinite FAQs chunks and indexes your `.md` documentation directly inside your WordPress database (`wp_infinite_faqs_docs`) using MySQL `FULLTEXT` search and your own AI provider API key (OpenAI, Anthropic Claude, Google Gemini, or DeepSeek).

= Key Features =

* **Bring Your Own Key (BYOK):** Supports OpenAI (`gpt-4o-mini`), Anthropic (`claude-3-haiku`), Google Gemini (`gemini-1.5-flash`), and DeepSeek (`deepseek-chat`) with AES-256-CBC key encryption at rest.
* **Zero-Hallucination Grounding:** Answers are strictly generated from your uploaded Markdown documentation chunks with automatic deep-link citations (`#anchor`).
* **Smart Fallback to Support:** If an answer is not found in your documentation, users are gracefully directed to your support URL without wasting tokens.
* **Predefined 4-Card Accordion + AI Auto-Generator:** Manually write your top 4 FAQs or generate them in one click from any indexed `.md` document using AI.
* **Light & Dark Mode Shortcodes:** Drop `[infinite_faqs_light]` or `[infinite_faqs_dark]` into Gutenberg or Elementor with zero jQuery dependency.

== External Services ==

This plugin connects to external third-party AI APIs **only** when a site administrator explicitly configures an API key in **Infinite FAQs → Settings** and selects one of the supported providers below. No data is transmitted to any external server until an API key is configured.

When configured, data is sent to the selected AI provider under three specific circumstances:
1. **Markdown Ingestion (Admin):** When an administrator uploads a `.md` file in **Infinite FAQs → Knowledge Base**, the text chunks of that document are sent to the configured AI provider to generate search keywords.
2. **Predefined FAQ Auto-Generation (Admin):** When an administrator clicks **Generate 4 FAQs with AI**, the selected indexed document chunks are sent to the configured AI provider to generate 4 question-and-answer pairs.
3. **Visitor Question Answering (Frontend):** When a visitor submits a question in the FAQ search bar and a matching documentation chunk is found in the local WordPress database, the visitor's sanitized question and the matched documentation chunk are sent to the configured AI provider to generate a grounded response.

= Supported Third-Party AI Providers =

* **OpenAI API** (`https://api.openai.com/v1/chat/completions`)
  * Service Website: [https://openai.com/api/](https://openai.com/api/)
  * Terms of Use: [https://openai.com/policies/terms-of-use/](https://openai.com/policies/terms-of-use/)
  * Privacy Policy: [https://openai.com/policies/privacy-policy/](https://openai.com/policies/privacy-policy/)
* **Anthropic Claude API** (`https://api.anthropic.com/v1/messages`)
  * Service Website: [https://www.anthropic.com/api](https://www.anthropic.com/api)
  * Terms of Service: [https://www.anthropic.com/legal/commercial-terms](https://www.anthropic.com/legal/commercial-terms)
  * Privacy Policy: [https://www.anthropic.com/legal/privacy](https://www.anthropic.com/legal/privacy)
* **Google Gemini API** (`https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent`)
  * Service Website: [https://ai.google.dev/](https://ai.google.dev/)
  * Terms of Service: [https://ai.google.dev/gemini-api/terms](https://ai.google.dev/gemini-api/terms)
  * Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)
* **DeepSeek API** (`https://api.deepseek.com/chat/completions`)
  * Service Website: [https://platform.deepseek.com/](https://platform.deepseek.com/)
  * Terms of Service: [https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html](https://cdn.deepseek.com/policies/en-US/deepseek-open-platform-terms-of-service.html)
  * Privacy Policy: [https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html](https://cdn.deepseek.com/policies/en-US/deepseek-privacy-policy.html)

== Third-Party Libraries & Source Code ==

* **Parsedown** (`v1.8.0` by Emanuil Rusev, bundled in `lib/Parsedown.php` and prefixed as `Infinite_FAQs_Parsedown`): Licensed under the MIT License (GPL-compatible). Source repository: [https://github.com/erusev/parsedown](https://github.com/erusev/parsedown)
* **Plugin Source Code:** All JavaScript (`assets/js/infinite-faqs.js`) and CSS (`assets/css/infinite-faqs.css`) are shipped in unminified, human-readable form. Public development repository: [https://github.com/tijesunii/Infinite-FAQs](https://github.com/tijesunii/Infinite-FAQs)

== Installation ==

1. Upload the `infinite-faqs` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Infinite FAQs → Settings** to select your AI Provider, enter your API Key, and configure your Support Page URL.
4. Navigate to **Infinite FAQs → Knowledge Base** to upload your `.md` documentation files, customize your shortcode heading/subtitle, or auto-generate your 4 predefined FAQ cards.
5. Copy `[infinite_faqs_light]` or `[infinite_faqs_dark]` and paste it into any page or post.

== Frequently Asked Questions ==

= Which AI providers are supported? =
Infinite FAQs supports OpenAI, Anthropic (Claude), Google Gemini, and DeepSeek.

= Does this plugin require jQuery? =
No. The frontend widget is built with 100% Vanilla JavaScript and scoped CSS variables for fast page loads and full compatibility with Gutenberg and Elementor.

= What happens if a visitor asks a question not covered by my Markdown files? =
The plugin checks your MySQL `FULLTEXT` index first and enforces a strict zero-hallucination system prompt. When a topic is not in your docs, it displays a fallback message with a direct link to your configured Support Page URL.

== Changelog ==

= 1.0.0 =
* Initial release of Infinite FAQs.
