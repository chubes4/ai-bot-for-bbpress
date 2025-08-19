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

define( 'AI_BOT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

require_once AI_BOT_PLUGIN_PATH . 'lib/ai-http-client/ai-http-client.php';

require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-ai-bot-service-container.php';

require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-generate-bot-response.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-system-prompt-builder.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-bot-trigger-service.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-database-agent.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-forum-structure-provider.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/context/class-content-interaction-service.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/tools/class-local-search-tool.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/tools/class-remote-search-tool.php';
require_once AI_BOT_PLUGIN_PATH . 'inc/core/class-ai-bot.php';

require_once AI_BOT_PLUGIN_PATH . 'inc/admin/admin-central.php';

use AiBot\Core\AiBot_Service_Container;
use AiBot\Core\AiBot;
use AiBot\Admin\Admin_Settings;
use AiBot\Context\Database_Agent;
use AiBot\Context\Content_Interaction_Service;
use AiBot\Context\Forum_Structure_Provider;
use AiBot\Core\Generate_Bot_Response;
use AiBot\Core\System_Prompt_Builder;
use AiBot\Core\Bot_Trigger_Service;


$container = new AiBot_Service_Container();



$container->register( 'context.database_agent', function( $c ) {
    return new Database_Agent();
} );


// Register the forum structure provider
$container->register( 'context.forum_structure_provider', function( $c ) {
    return new Forum_Structure_Provider();
} );

// Register the system prompt builder
$container->register( 'core.system_prompt_builder', function( $c ) {
    return new System_Prompt_Builder(
        $c->get( 'context.forum_structure_provider' )
    );
} );

// Register the bot trigger service
$container->register( 'core.bot_trigger_service', function( $c ) {
    return new Bot_Trigger_Service();
} );

// Register the content interaction service
$container->register( 'context.interaction_service', function( $c ) {
    return new Content_Interaction_Service(
        $c->get( 'context.database_agent' )
    );
} );


// Register the response generation service
$container->register( 'core.generate_bot_response', function( $c ) {
    return new Generate_Bot_Response(
        $c->get( 'context.interaction_service' ),
        $c->get( 'core.system_prompt_builder' ),
        $c // Pass the container itself
    );
} );

// Register the main bot class
$container->register( 'bot.main', function( $c ) {
    return new AiBot(
        $c->get( 'core.bot_trigger_service' ),
        $c->get( 'core.generate_bot_response' ),
        $c->get( 'context.interaction_service' ),
        $c->get( 'context.database_agent' )
    );
} );


// Instantiate and Initialize the Bot via the Container
$ai_bot_instance = $container->get( 'bot.main' );
if (method_exists($ai_bot_instance, 'init')) {
    $ai_bot_instance->init();
} else {
}



global $ai_bot_container;
$ai_bot_container = $container;

