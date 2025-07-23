<?php
/**
 * Plugin Name: AI Bot for bbPress
 * Plugin URI:  https://wordpress.org/plugins/ai-bot-for-bbpress/
 * Description: Universal AI bot for bbPress forums with multi-provider support (OpenAI, Anthropic, Gemini, Grok, OpenRouter).
 * Version:     1.0.5
 * Author:      Chubes
 * Author URI:  https://chubes.net
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Requires Plugins: bbpress
 * Text Domain: ai-bot-for-bbpress
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define root path for convenience
define( 'AI_BOT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Include AI HTTP Client Library (official installation method)
require_once AI_BOT_PLUGIN_PATH . 'lib/ai-http-client/ai-http-client.php';

// Include Service Container
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-ai-bot-service-container.php';

// Include Namespaced Classes
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-generate-bot-response.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-system-prompt-builder.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-bot-trigger-service.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-database-agent.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-local-context-retriever.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-remote-context-retriever.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-forum-structure-provider.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-content-interaction-service.php';
// Include the main bot class
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-ai-bot.php';

// Include admin file
require_once AI_BOT_PLUGIN_PATH . 'inc/admin/admin-central.php';

// Use statements for namespaces
use AiBot\Core\AiBot_Service_Container;
use AiBot\Core\AiBot;
use AiBot\Admin\Admin_Settings;
use AiBot\Context\Database_Agent;
use AiBot\Context\Local_Context_Retriever;
use AiBot\Context\Remote_Context_Retriever;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Context\Forum_Structure_Provider;
use AiBot\Core\Generate_Bot_Response;
use AiBot\Core\System_Prompt_Builder;
use AiBot\Core\Bot_Trigger_Service;

// --- Service Container Setup ---

// Instantiate the service container class using its short name (due to the 'use' statement)
$container = new AiBot_Service_Container();

// Register Services

// Register AI HTTP Client (auto-reads provider configuration from WordPress options)
$container->register( 'api.ai_http_client', function( $c ) {
    return new AI_HTTP_Client();
} );

$container->register( 'context.database_agent', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new Database_Agent();
} );

// Register the local context retriever
$container->register( 'context.local_retriever', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new Local_Context_Retriever(
        $c->get( 'context.database_agent' )
    );
} );

// Register the remote context retriever
$container->register( 'context.remote_retriever', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new Remote_Context_Retriever();
} );

// Register the forum structure provider
$container->register( 'context.forum_structure_provider', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new Forum_Structure_Provider();
} );

// Register the system prompt builder
$container->register( 'core.system_prompt_builder', function( $c ) {
    return new System_Prompt_Builder(
        $c->get( 'context.forum_structure_provider' ),
        $c->get( 'api.ai_http_client' )
    );
} );

// Register the bot trigger service
$container->register( 'core.bot_trigger_service', function( $c ) {
    return new Bot_Trigger_Service();
} );

// Register the content interaction service
$container->register( 'context.interaction_service', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new Content_Interaction_Service(
        $c->get( 'context.database_agent' ),
        $c->get( 'context.local_retriever' ),
        $c->get( 'context.remote_retriever' ),
        $c->get( 'core.system_prompt_builder' )
    );
} );


// Register the response generation service
$container->register( 'core.generate_bot_response', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new Generate_Bot_Response(
        $c->get( 'api.ai_http_client' ), // Changed from api.chatgpt to ai_http_client
        $c->get( 'context.interaction_service' ),
        $c->get( 'core.system_prompt_builder' ),
        $c // Pass the container itself
    );
} );

// Register the main bot class
$container->register( 'bot.main', function( $c ) {
    // Use the short class name because of the 'use' statement above
    return new AiBot(
        $c->get( 'core.bot_trigger_service' ),
        $c->get( 'core.generate_bot_response' ),
        $c->get( 'context.interaction_service' ),
        $c->get( 'context.database_agent' )
    );
} );


// Instantiate and Initialize the Bot via the Container
$ai_bot_instance = $container->get( 'bot.main' );
// Check the init method exists on the main bot class
if (method_exists($ai_bot_instance, 'init')) {
    $ai_bot_instance->init();
} else {
    // error_log('AI Bot for bbPress Error: init() method not found on main bot class.');
}


// --- Elegant Backwards Compatibility ---

// Hook WordPress core option filters to provide seamless fallback to old plugin settings
// This ensures existing users' settings work without any migration or modification
add_filter('option_ai_http_client_providers', function($value) {
    // If library settings don't exist, map old plugin settings at runtime
    if (empty($value)) {
        $old_api_key = get_option('ai_bot_api_key');
        if (!empty($old_api_key)) {
            return array(
                'openai' => array(
                    'api_key' => $old_api_key,
                    'model' => 'gpt-4.1-mini',
                    'temperature' => get_option('ai_bot_temperature', 0.5)
                )
            );
        }
    }
    return $value; // Return original if library settings exist or no fallback available
});

// Also handle the selected provider option
add_filter('option_ai_http_client_selected_provider', function($value) {
    // If no library provider selected, but we have old API key, default to openai
    if (empty($value)) {
        $old_api_key = get_option('ai_bot_api_key');
        if (!empty($old_api_key)) {
            return 'openai';
        }
    }
    return $value;
});

// --- End Service Container Setup ---