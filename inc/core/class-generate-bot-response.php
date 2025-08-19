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
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     * @param System_Prompt_Builder $system_prompt_builder The system prompt builder instance.
     * @param AiBot_Service_Container $container The service container instance.
     */
    public function __construct(
        Content_Interaction_Service $content_interaction_service,
        System_Prompt_Builder $system_prompt_builder,
        AiBot_Service_Container $container
    ) {
        $this->content_interaction_service = $content_interaction_service;
        $this->system_prompt_builder = $system_prompt_builder;
        $this->container = $container;
    }

    /**
     * Cron function to generate and post AI response
     */
    public function generate_and_post_ai_response_cron( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {

        $bot_user_id = get_option( 'ai_bot_user_id' );

        if ( ! $bot_user_id ) {
            return;
        }

        $bot_user_data = get_userdata( $bot_user_id );

        if ( ! $bot_user_data ) {
            return;
        }

        $bot_username = $bot_user_data->user_login;

        $triggering_username_slug = 'the user';
        if ( $reply_author != 0 ) {
            $triggering_user_data = get_userdata( $reply_author );
            if ( $triggering_user_data ) {
                $triggering_username_slug = $triggering_user_data->user_nicename;
            }
        }

        $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );
        $response_content = $this->generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug );

        if ( is_wp_error( $response_content ) ) {
             $response_content = __( 'I received your message but I\'m having some technical difficulties right now. Please try again in a few minutes!', 'ai-bot-for-bbpress' );
        }

        $bot_instance = $this->container->get('bot.main');
        $bot_instance->post_bot_reply( $topic_id, $response_content );
    }

    /**
     * Create standard API error
     */
    private function create_api_error() {
        return new \WP_Error('api_error', __('Sorry, I\'m having trouble generating a response right now. Please try again later.', 'ai-bot-for-bbpress'));
    }

    /**
     * Generate AI response using tool-based architecture
     */
    private function generate_ai_response( $bot_username, $post_content, $topic_id, $forum_id, $post_id, $triggering_username_slug ) {
        
        // Build complete system prompt using centralized builder
        $system_prompt = $this->system_prompt_builder->build_system_prompt( $bot_username );

        // Get current interaction context only
        $current_context = $this->content_interaction_service->get_current_interaction_context( $post_id, $post_content, $topic_id, $forum_id );

        // Get structured conversation messages for AI provider flow
        $conversation_messages = $this->content_interaction_service->get_conversation_messages( $post_id, $post_content, $topic_id, $forum_id );
        
        // Get available search tools
        $available_tools = apply_filters( 'ai_tools', [] );
        $search_tools = array_filter( $available_tools, function( $tool ) {
            return isset( $tool['category'] ) && $tool['category'] === 'search';
        });

        // Build tool definitions for AI request
        $tools = [];
        foreach ( $search_tools as $tool_name => $tool_def ) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool_def['name'],
                    'description' => $tool_def['description'],
                    'parameters' => $this->convert_tool_parameters( $tool_def['parameters'] ?? [] )
                ]
            ];
        }

        // Build initial request with tools
        $request = array(
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $current_context . "Please help respond to this user's question. You have access to search tools if you need to find relevant information.")
            ),
            'tools' => $tools,
            'tool_choice' => 'auto'
        );
        
        // Add model if configured  
        $model = get_option('ai_bot_selected_model');
        if (!empty($model)) {
            $request['model'] = $model;
        }
        
        // Add temperature if configured
        $temperature = get_option('ai_bot_temperature');
        if (!empty($temperature)) {
            $request['temperature'] = floatval($temperature);
        }
        
        // Add conversation history if available
        if (!empty($conversation_messages)) {
            // Insert conversation messages before the final user prompt
            array_splice($request['messages'], -1, 0, $conversation_messages);
        }
        
        // Get selected provider (required by new library)
        $selected_provider = get_option('ai_bot_selected_provider', 'openai');
        
        $response = apply_filters('ai_request', $request, $selected_provider);

        // Handle tool calls if present
        if ( is_array($response) && isset($response['success']) && $response['success'] === true ) {
            $response_data = $response['data'] ?? [];
            
            // Check if AI made tool calls
            if ( isset($response_data['response']['tool_calls']) && !empty($response_data['response']['tool_calls']) ) {
                
                // Execute tool calls and collect results
                $tool_results = [];
                foreach ( $response_data['response']['tool_calls'] as $tool_call ) {
                    $tool_name = $tool_call['name'] ?? '';
                    $parameters = $tool_call['parameters'] ?? [];
                    
                    // Add context parameters
                    $parameters['exclude_post_id'] = $post_id;
                    $parameters['topic_id'] = $topic_id;
                    
                    // Execute tool via AI HTTP Client library
                    $tool_result = ai_http_execute_tool( $tool_name, $parameters );
                    $tool_results[] = [
                        'tool_name' => $tool_name,
                        'result' => $tool_result
                    ];
                }
                
                // Build follow-up request with tool results
                $followup_request = $request; // Copy original request
                $followup_request['messages'][] = [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => $response_data['response']['tool_calls']
                ];
                
                // Add tool results as tool messages
                foreach ( $tool_results as $tool_result ) {
                    $followup_request['messages'][] = [
                        'role' => 'tool',
                        'content' => wp_json_encode( $tool_result['result'] ),
                        'tool_call_id' => uniqid() // Generate unique ID
                    ];
                }
                
                // Remove tools from follow-up request
                unset( $followup_request['tools'] );
                unset( $followup_request['tool_choice'] );
                
                $final_response = apply_filters('ai_request', $followup_request, $selected_provider);
                
                if ( is_array($final_response) && isset($final_response['success']) && $final_response['success'] === true ) {
                    $final_data = $final_response['data'] ?? [];
                    if ( isset($final_data['response']['content']) ) {
                        return $final_data['response']['content'];
                    }
                }
            } else {
                // No tool calls, return direct response
                if ( isset($response_data['response']['content']) ) {
                    return $response_data['response']['content'];
                }
            }
        }

        if (empty($response)) {
            return $this->create_api_error();
        }
        
        if (is_array($response) && isset($response['success']) && $response['success'] === false) {
            return $this->create_api_error();
        }
        
        if (is_string($response) && !empty($response)) {
            return $response;
        }
        
        return new \WP_Error('api_error', __('Sorry, I\'m having trouble generating a response right now. Please try again later.', 'ai-bot-for-bbpress'));
    }

    /**
     * Convert tool parameters to OpenAI-compatible format
     *
     * @param array $parameters Tool parameters
     * @return array OpenAI-compatible parameters
     */
    private function convert_tool_parameters( $parameters ) {
        $openai_params = [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];

        foreach ( $parameters as $param_name => $param_config ) {
            $openai_params['properties'][$param_name] = [
                'type' => $param_config['type'] ?? 'string',
                'description' => $param_config['description'] ?? ''
            ];

            if ( isset($param_config['required']) && $param_config['required'] ) {
                $openai_params['required'][] = $param_name;
            }
        }

        return $openai_params;
    }

}