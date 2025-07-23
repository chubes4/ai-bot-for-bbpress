=== AI Bot for bbPress ===
Contributors: chubes
Tags: bbpress, ai, bot, forum, chatgpt, anthropic, claude, gemini, grok, openrouter, multi-provider
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.0.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ai-bot-for-bbpress

Universal AI bot for bbPress forums with multi-provider support - choose from OpenAI, Anthropic, Gemini, Grok, or OpenRouter.

== Description ==

**The first and only bbPress AI bot with true multi-provider support!** Choose from 5 major AI providers and switch between them seamlessly without any code changes.

AI Bot for bbPress integrates seamlessly with your bbPress forums, allowing a configurable AI bot user to participate in discussions. The bot can be triggered by direct mentions (@YourBotUsername) or specific keywords within forum posts, leveraging context from the forum and optionally a remote WordPress site.

**🚀 Multi-Provider AI Support:**
* **OpenAI** - Access to all available OpenAI models
* **Anthropic** - Full Claude model family support
* **Google Gemini** - Complete Gemini model lineup
* **Grok (X.AI)** - All Grok models with advanced capabilities  
* **OpenRouter** - Access to 100+ models from multiple providers

**✨ Key Features:**
* **Professional Provider Management** - Intuitive admin interface for easy switching
* **Dynamic Model Selection** - Automatically fetches available models from any provider
* **Seamless Migration** - Existing OpenAI configurations work without changes
* **Cost Optimization** - Choose providers based on your budget and needs
* **Future-Proof** - Built on our advanced [AI HTTP Client library](https://github.com/chubes4/ai-http-client)

**Links:**
* [Plugin Homepage](https://chubes.net) - Visit the developer's website
* [GitHub Repository](https://github.com/chubes4/ai-bot-for-bbpress) - Source code, issues, and contributions
* [Support](https://github.com/chubes4/ai-bot-for-bbpress/issues) - Report bugs or request features

== Installation ==

1.  Upload the `ai-bot-for-bbpress` folder to the `/wp-content/plugins/` directory
2.  Activate the plugin through the 'Plugins' menu in WordPress
3.  Configure the plugin settings under the 'AI Bot for bbPress' menu in the WordPress admin panel (Settings > Forum AI Bot).

== Configuration ==

After activation, navigate to Settings > Forum AI Bot in your WordPress admin area to configure the following options:

*   **AI Provider Configuration:** Choose your preferred AI provider (OpenAI, Anthropic, Gemini, Grok, or OpenRouter) and enter the corresponding API key. The interface automatically adapts to show relevant settings for your selected provider.
*   **Bot User ID:** The WordPress user ID of the account the bot will use to post replies.
*   **System Prompt:** Instructions defining the bot's personality, role, and general behavior (e.g., "You are a helpful assistant for the Example Community forum.").
*   **Custom Prompt:** Additional instructions appended to every API request, useful for guiding specific response formats or context usage.
*   **Temperature:** Controls the creativity/randomness of the AI's responses (0 = deterministic, 1 = max creativity). Default is 0.5.
*   **Trigger Keywords:** A comma-separated list of keywords (in addition to mentions) that will trigger the bot to respond.
*   **Local Search Limit:** Maximum number of relevant posts/topics to retrieve from the local forum database for context. Default is 3.
*   **Remote REST Endpoint URL:** The full URL to the search endpoint provided by the BBP Bot Helper plugin installed on your remote site (e.g., `https://your-main-site.com/wp-json/bbp-bot-helper/v1/search`). Leave blank to disable remote context.
*   **Remote Search Limit:** Maximum number of relevant posts to retrieve from the remote endpoint for context. Default is 3.
*   **Forum Access Control:** Choose whether the bot responds in all forums or only selected ones. When set to "Selected Forums Only", you can choose specific forums where the bot will be active.

== Frequently Asked Questions ==

= What does this plugin do? =

This plugin allows you to integrate an AI bot into your bbPress forums. The bot can respond to mentions and keywords, providing automated assistance and engaging in discussions.

= How do I configure the bot? =

You can configure the bot's behavior and settings under the 'AI Bot for bbPress' menu in the WordPress admin panel.

= What is the "Remote REST Endpoint URL" setting? =

The remote context feature allows the bot to search for relevant information on a separate WordPress installation (like your main website). To enable this, you need to install the companion plugin, **BBP Bot Helper**, on that *other* WordPress site. This helper plugin creates a secure REST API endpoint. You then enter the URL of this endpoint into the "Remote REST Endpoint URL" setting in *this* plugin (AI Bot for bbPress).

= What is the BBP Bot Helper plugin? =

It provides a REST API endpoint for the AI Bot for bbPress plugin to securely query content for context.

= Where do I install the BBP Bot Helper plugin? =

Install it on the WordPress site whose content you want the AI Bot for bbPress to be able to search. This is often your main website, separate from where the bbPress forum itself is hosted.

= Does the BBP Bot Helper plugin do anything on its own? =

No. It only provides an API endpoint for use by the AI Bot for bbPress plugin.

= Is the BBP Bot Helper endpoint secure? =

The endpoint itself is public by default, typical of WordPress REST API endpoints. Security relies on the fact that only the AI Bot for bbPress knows to query it, and it only returns publicly available content (published posts/pages). Access control could be added in future versions if needed.

== External Services ==

This plugin connects to your selected AI provider's API to generate responses for the AI bot. This is essential for the plugin's core functionality of providing AI-driven replies in bbPress forums.

*   **Supported Services:** OpenAI API, Anthropic API, Google Gemini API, Grok (X.AI) API, or OpenRouter API - depending on your configuration.
*   **Purpose:** To generate intelligent and contextually relevant responses based on forum discussions and configured prompts.
*   **Data Sent:** When the bot is triggered (by a mention or keyword), the following types of data are sent to your selected AI provider's API:
    *   The content of the post that triggered the bot.
    *   Relevant conversation history from the current topic (including post content and author usernames/slugs).
    *   Contextual information retrieved from the local WordPress database (titles, snippets, and URLs of relevant posts/pages based on keyword matching).
    *   If configured, contextual information retrieved from a remote WordPress site via the BBP Bot Helper plugin (titles, snippets, and URLs of relevant posts/pages).
    *   The system prompt, custom prompt, and temperature settings configured in the plugin's admin page.
    *   The structure of your bbPress forums (forum names, topic names, and their hierarchy).
    *   The current date and time.
*   **When Data is Sent:** Data is sent only when the bot is triggered to generate a response. This occurs after a user posts a new reply or topic that meets the trigger conditions (mentioning the bot or containing a specified keyword).
*   **Privacy Policies:** Please review the privacy policy and terms of service for your selected AI provider to understand how they handle the data sent to their API.

It is important to have an active API key with sufficient credits/quota for your selected AI provider for the bot to function. Each provider has different pricing models and terms.

If the "Remote REST Endpoint URL" is configured, the plugin will also send search queries (derived from the conversation) to that endpoint to fetch additional context. This endpoint is typically on another WordPress site you control and that runs the companion "BBP Bot Helper" plugin. No user-specific data is sent to this remote endpoint beyond the search terms. The data received from this endpoint is then included in the information sent to your selected AI provider's API as described above.

== Screenshots ==

1.  Configuration screen showing the main settings.
2.  Example of a bot reply in a forum topic.
3.  (Add more descriptions as needed)

== Changelog ==

= 1.0.5 =
* 🚀 REVOLUTIONARY: Multi-Provider AI Support - Choose from 5 AI providers!
* NEW: Support for OpenAI, Anthropic (Claude), Google Gemini, Grok (X.AI), and OpenRouter
* NEW: Professional provider management interface with seamless switching
* NEW: Dynamic model selection - automatically fetch available models from any provider
* NEW: Unified admin interface for all providers with core components
* ENHANCEMENT: Backward compatibility - existing OpenAI configurations work seamlessly
* ENHANCEMENT: Provider-agnostic architecture - switch providers without code changes  
* ENHANCEMENT: Real-time model fetching with proper error handling and UX feedback
* ARCHITECTURE: Complete integration of AI HTTP Client library (7,400+ lines of new code)
* ARCHITECTURE: Unified normalizers for consistent cross-provider functionality
* UPGRADE: From single-provider to universal AI bot platform

= 1.0.4 =
* Fix: Bot replies now properly trigger WordPress hooks (bbp_new_reply) for better integration
* Enhancement: Bot replies now work correctly with notification plugins, points systems, and activity feeds
* Enhancement: Improved compatibility with bbPress ecosystem plugins that expect standard hooks

= 1.0.3 =
* NEW FEATURE: Forum Access Control - Bot can now be restricted to specific forums only
* NEW: Hierarchical forum selection with proper indentation showing parent/child relationships
* MAJOR: Improved conversation memory - Bot now uses proper OpenAI message structure for better context retention
* ARCHITECTURE: Complete code refactor with centralized System_Prompt_Builder for all AI instructions
* ARCHITECTURE: Eliminated redundant wrapper classes and directories for cleaner, more maintainable code
* ARCHITECTURE: New Bot_Trigger_Service centralizes all trigger logic (mentions, keywords, forum restrictions)
* Enhancement: Forum restriction enforcement - bot will only respond in selected forums when configured
* Enhancement: Bot now posts user-friendly error messages instead of staying silent when API errors occur
* Enhancement: Conversation history now uses proper user/assistant message alternation for OpenAI API
* Enhancement: All prompt construction logic now centralized in single service following single responsibility principle
* Fix: Bot now properly recognizes its own username when mentioned (no longer thinks mentions are about someone else)
* Fix: Added proper output escaping for admin form inputs to meet WordPress security standards
* Code cleanup: Removed redundant code and directories, improved separation of concerns

= 1.0.2 =
* Address WordPress.org review feedback:
    * Added 'Requires Plugins: bbpress' header to main plugin file.
    * Updated readme.txt with detailed 'External Services' disclosure for OpenAI API.
    * Removed unnecessary PHP closing tags (`?>`) from several files.
    * Commented out `error_log` and `print_r` development debugging statements.
    * Replaced `strip_tags()` with `wp_strip_all_tags()` in database agent.
    * Restored `unset()` for a class property in database agent after filter removal.
* Enhanced bot response context to more clearly identify the user being replied to.

= 1.0.0 =
*   Major Refactor: Renamed plugin to "AI Bot for bbPress" and updated internal naming conventions.
*   Feature: Added optional remote context retrieval via BBP Bot Helper companion plugin.
*   Feature: Added Local and Remote search limit settings.
*   Feature: Added Trigger Keywords setting.
*   Feature: Added date/timestamp information to remote context results.
*   Fix: Resolved issues with local and remote context not being correctly passed to the AI model.
*   Fix: Improved reliability of cron job scheduling and execution logging.
*   Update: Added configuration options for temperature and prompts.
*   Update: Tested compatibility up to WordPress 6.8.

== Upgrade Notice ==

= 1.0.0 =
This is the version submitted to the WordPress plugin repository. 

= 0.1.2 =
*   Updated plugin name and description.
*   Added helper plugin for remote context.

= 0.1.1 =
*   Bug fixes and minor improvements.

= 0.1.0 =
*   Initial release.