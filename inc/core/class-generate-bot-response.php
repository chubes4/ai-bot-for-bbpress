<?php

namespace AiBot\Core;

use AiBot\Context\Content_Interaction_Service;
use AiBot\Core\System_Prompt_Builder;
use AiBot\Core\AiBot_Service_Container;
use AiBot\Core\AiBot;

/**
 * Generate Bot Response Class
 * 
 * Orchestrates AI response generation by coordinating context retrieval, 
 * prompt construction, and API communication.
 */
class Generate_Bot_Response {

    /**
     * @var \AI_HTTP_Client
     */
    private $ai_http_client;

    /**
     * @var Content_Interaction_Service
     */
    private $content_interaction_service;

    /**
     * @var System_Prompt_Builder
     */
    private $system_prompt_builder;

    /**
     * @var AiBot_Service_Container
     */
    private $container;

    /**
     * Constructor
     *
     * @param \AI_HTTP_Client $ai_http_client The AI HTTP Client instance.
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     * @param System_Prompt_Builder $system_prompt_builder The system prompt builder instance.
     * @param AiBot_Service_Container $container The service container instance.
     */
    public function __construct(
        \AI_HTTP_Client $ai_http_client,
        Content_Interaction_Service $content_interaction_service,
        System_Prompt_Builder $system_prompt_builder,
        AiBot_Service_Container $container
    ) {
        $this->ai_http_client = $ai_http_client;
        $this->content_interaction_service = $content_interaction_service;
        $this->system_prompt_builder = $system_prompt_builder;
        $this->container = $container;
    }

    /**
     * Cron function to generate and post AI response
     */
    public function generate_and_post_ai_response_cron( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {

        // Get the Bot User ID from options
        $bot_user_id = get_option( 'ai_bot_user_id' );

        // Check if Bot User ID is set
        if ( ! $bot_user_id ) {
            return; // Stop execution if no bot user is configured
        }

        // Get the user data based on the ID
        $bot_user_data = get_userdata( $bot_user_id );

        // Check if user data was retrieved successfully
        if ( ! $bot_user_data ) {
            return; // Stop execution if user doesn't exist
        }

        // Get the username (user_login) to use for mentions/prompts
        $bot_username = $bot_user_data->user_login;

        // Get triggering user's information
        $triggering_username_slug = 'the user'; // Default
        if ( $reply_author != 0 ) {
            $triggering_user_data = get_userdata( $reply_author );
            if ( $triggering_user_data ) {
                $triggering_username_slug = $triggering_user_data->user_nicename; // Use nicename (slug)
            }
        }

        try {
            $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );

            // Generate AI response using the simplified orchestration
            $response_content = $this->generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug );

            // Check if response generation resulted in an error
            if ( is_wp_error( $response_content ) ) {
                 // Post a user-friendly error message so users know the bot received their message
                 $fallback_message = __( 'I received your message but I\'m having some technical difficulties right now. Please try again in a few minutes!', 'ai-bot-for-bbpress' );
                 
                 // Get bot instance from container and post the fallback message
                 $bot_instance = $this->container->get('bot.main');
                 if ($bot_instance instanceof AiBot) {
                     $bot_instance->post_bot_reply( $topic_id, $fallback_message );
                 }
                 return;
            }

            // Get bot instance from container and call the post method
            $bot_instance = $this->container->get('bot.main');
            if ($bot_instance instanceof AiBot) {
                 $bot_instance->post_bot_reply( $topic_id, $response_content );
            }
        } catch (\Exception $e) {
            // Catch any exceptions - fallback error handling could go here
        }
    }

    /**
     * Generate AI response using centralized prompt building and context retrieval
     */
    private function generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug ) {
        error_log("AI Bot Response: generate_ai_response called for post_id=$post_id");
        // Build complete system prompt using centralized builder
        $system_prompt = $this->system_prompt_builder->build_system_prompt( $bot_username );

        // Get relevant context and remote hostname from content service
        $context_string = $this->content_interaction_service->get_relevant_context( $post_id, $post_content, $topic_id, $forum_id );
        $remote_host = $this->content_interaction_service->get_remote_hostname();

        // Build response instructions using centralized builder
        $response_instructions = $this->system_prompt_builder->build_response_instructions( 
            $bot_username, 
            $triggering_username_slug, 
            $remote_host 
        );

        // Combine context with instructions
        $final_prompt = $context_string . $response_instructions;

        // Get structured conversation messages for proper OpenAI flow
        $conversation_messages = $this->content_interaction_service->get_conversation_messages( $post_id, $post_content, $topic_id, $forum_id );
        
        // Build AI HTTP Client request format - library handles configuration automatically
        // Model/temperature/provider come from library settings or our backwards compatibility filter
        $request = array(
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $final_prompt)
            )
        );
        
        // Add conversation history if available
        if (!empty($conversation_messages)) {
            // Insert conversation messages before the final user prompt
            array_splice($request['messages'], -1, 0, $conversation_messages);
        }
        
        // Generate Response using AI HTTP Client (configuration handled by library + our filters)
        error_log("AI Bot Response: Sending main response request to AI HTTP Client");
        $response = $this->ai_http_client->send_request($request);
        error_log("AI Bot Response: AI HTTP Client response received: " . print_r($response, true));

        if (!$response['success'] || empty($response['data']['content'])) {
            error_log("AI Bot Response: API call failed or empty content");
            return new \WP_Error('api_error', __('Sorry, I\'m having trouble generating a response right now. Please try again later.', 'ai-bot-for-bbpress'));
        }
        
        error_log("AI Bot Response: Successfully generated response");
        return $response['data']['content'];
    }

}