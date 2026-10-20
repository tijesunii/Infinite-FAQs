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
