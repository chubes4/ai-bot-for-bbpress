<?php

namespace AiBot\Tools;

use AiBot\Context\Database_Agent;

/**
 * Local Search Tool Class
 *
 * AI-powered local content search tool that integrates with the AI HTTP Client library.
 * Provides semantic search capabilities for WordPress/bbPress content.
 */
class Local_Search_Tool {

    /**
     * @var Database_Agent
     */
    private $database_agent;

    /**
     * Constructor
     */
    public function __construct() {
        // Database_Agent will be injected when needed
    }

    /**
     * Execute the local search tool
     *
     * @param array $parameters Tool parameters from AI
     * @param array $tool_def Tool definition
     * @return array Search results
     */
    public function execute( $parameters, $tool_def = [] ) {
        // Get database agent from service container
        global $ai_bot_container;
        if ( ! $ai_bot_container ) {
            return [
                'error' => 'Service container not available',
                'results' => []
            ];
        }

        $this->database_agent = $ai_bot_container->get('context.database_agent');
        if ( ! $this->database_agent ) {
            return [
                'error' => 'Database agent not available',
                'results' => []
            ];
        }

        // Extract parameters
        $query = isset( $parameters['query'] ) ? sanitize_text_field( $parameters['query'] ) : '';
        $limit = isset( $parameters['limit'] ) ? max( 1, intval( $parameters['limit'] ) ) : 3;
        $exclude_post_id = isset( $parameters['exclude_post_id'] ) ? intval( $parameters['exclude_post_id'] ) : null;
        $topic_id = isset( $parameters['topic_id'] ) ? intval( $parameters['topic_id'] ) : 0;

        if ( empty( $query ) ) {
            return [
                'error' => 'Search query is required',
                'results' => []
            ];
        }

        // Perform search using existing database agent method
        $search_results = $this->database_agent->search_local_content_by_keywords( 
            $query, 
            $limit, 
            $exclude_post_id, 
            $topic_id 
        );

        // Format results for AI consumption
        $formatted_results = [];
        foreach ( $search_results as $post ) {
            $formatted_result = [
                'id' => $post->ID,
                'title' => get_the_title( $post ),
                'type' => $post->post_type,
                'date' => get_the_date( '', $post ),
                'url' => get_permalink( $post ),
                'content' => $this->get_clean_content( $post ),
            ];

            // Add forum information for bbPress content
            if ( function_exists( 'bbp_get_topic_post_type' ) && function_exists( 'bbp_get_reply_post_type' ) ) {
                $topic_post_type = bbp_get_topic_post_type();
                $reply_post_type = bbp_get_reply_post_type();

                if ( $post->post_type === $topic_post_type ) {
                    $forum_id = bbp_get_topic_forum_id( $post->ID );
                    if ( $forum_id ) {
                        $formatted_result['forum'] = bbp_get_forum_title( $forum_id );
                    }
                } elseif ( $post->post_type === $reply_post_type ) {
                    $forum_id = bbp_get_reply_forum_id( $post->ID );
                    if ( $forum_id ) {
                        $formatted_result['forum'] = bbp_get_forum_title( $forum_id );
                    }
                }
            }

            $formatted_results[] = $formatted_result;
        }

        return [
            'query' => $query,
            'results_count' => count( $formatted_results ),
            'results' => $formatted_results
        ];
    }

    /**
     * Get clean content from post
     *
     * @param \WP_Post $post
     * @return string
     */
    private function get_clean_content( $post ) {
        $content = get_post_field( 'post_content', $post );
        return trim( html_entity_decode( wp_strip_all_tags( $content ), ENT_QUOTES, 'UTF-8' ) );
    }
}

// Self-register with AI tools filter
add_filter( 'ai_tools', function( $tools ) {
    $tools['local_search'] = [
        'name' => 'local_search',
        'description' => 'Search local WordPress/bbPress content for relevant information. Use this to find related posts, topics, replies, and pages that might help answer the user\'s question.',
        'class' => 'AiBot\\Tools\\Local_Search_Tool',
        'method' => 'execute',
        'category' => 'search',
        'parameters' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query or keywords to find relevant content',
                'required' => true
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of results to return (default: 3, max: 10)',
                'required' => false,
                'default' => 3,
                'minimum' => 1,
                'maximum' => 10
            ],
            'exclude_post_id' => [
                'type' => 'integer',
                'description' => 'Post ID to exclude from search results',
                'required' => false
            ],
            'topic_id' => [
                'type' => 'integer',
                'description' => 'Current topic ID to exclude replies from this topic',
                'required' => false
            ]
        ]
    ];
    return $tools;
});