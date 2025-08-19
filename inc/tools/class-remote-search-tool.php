<?php

namespace AiBot\Tools;

/**
 * Remote Search Tool Class
 *
 * AI-powered remote content search tool that integrates with the AI HTTP Client library.
 * Queries external WordPress sites via BBP Bot Helper plugin REST API.
 */
class Remote_Search_Tool {

    /**
     * Execute the remote search tool
     *
     * @param array $parameters Tool parameters from AI
     * @param array $tool_def Tool definition
     * @return array Search results
     */
    public function execute( $parameters, $tool_def = [] ) {
        // Extract parameters
        $query = isset( $parameters['query'] ) ? sanitize_text_field( $parameters['query'] ) : '';
        $limit = isset( $parameters['limit'] ) ? max( 1, intval( $parameters['limit'] ) ) : 3;

        if ( empty( $query ) ) {
            return [
                'error' => 'Search query is required',
                'results' => []
            ];
        }

        // Get remote endpoint URL
        $helper_url = get_option( 'ai_bot_remote_endpoint_url' );
        if ( empty( $helper_url ) ) {
            return [
                'error' => 'Remote endpoint URL not configured',
                'results' => []
            ];
        }

        // Build search URL
        $search_url = add_query_arg( [
            'keyword' => urlencode( $query ),
            'limit'   => intval( $limit )
        ], $helper_url );

        // Make API request
        $args = [
            'timeout' => 15
        ];

        $response = wp_remote_get( $search_url, $args );

        // Handle response errors
        if ( is_wp_error( $response ) ) {
            return [
                'error' => 'Remote request failed: ' . $response->get_error_message(),
                'results' => []
            ];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return [
                'error' => 'Remote server returned error code: ' . $response_code,
                'results' => []
            ];
        }

        $response_body = wp_remote_retrieve_body( $response );
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) || ! isset( $data['results'] ) ) {
            return [
                'error' => 'Invalid response format from remote server',
                'results' => []
            ];
        }

        // Format results for AI consumption
        $formatted_results = [];
        $hostname = $this->get_remote_hostname();

        foreach ( $data['results'] as $result ) {
            if ( isset( $result['title'], $result['author'], $result['url'], $result['content'], $result['date'] ) ) {
                $formatted_results[] = [
                    'title' => esc_html( $result['title'] ),
                    'author' => esc_html( $result['author'] ),
                    'date' => esc_html( $result['date'] ),
                    'url' => esc_url( $result['url'] ),
                    'content' => esc_html( $result['content'] ),
                    'source' => esc_html( $hostname )
                ];
            }
        }

        return [
            'query' => $query,
            'source' => $hostname,
            'results_count' => count( $formatted_results ),
            'results' => $formatted_results
        ];
    }

    /**
     * Get the hostname from the configured remote endpoint URL
     *
     * @return string
     */
    private function get_remote_hostname() {
        $remote_url = get_option( 'ai_bot_remote_endpoint_url' );
        $remote_host = 'Remote Source';

        if ( ! empty( $remote_url ) ) {
            $parsed_parts = wp_parse_url( $remote_url );
            $parsed_host = $parsed_parts['host'] ?? null;
            if ( $parsed_host ) {
                $remote_host = $parsed_host;
            }
        }

        return $remote_host;
    }
}

// Self-register with AI tools filter
add_filter( 'ai_tools', function( $tools ) {
    $tools['remote_search'] = [
        'name' => 'remote_search',
        'description' => 'Search remote WordPress site for relevant information via BBP Bot Helper plugin. Use this to find content from external knowledge sources that might help answer the user\'s question.',
        'class' => 'AiBot\\Tools\\Remote_Search_Tool',
        'method' => 'execute',
        'category' => 'search',
        'parameters' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query or keywords to find relevant content on the remote site',
                'required' => true
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of results to return (default: 3, max: 10)',
                'required' => false,
                'default' => 3,
                'minimum' => 1,
                'maximum' => 10
            ]
        ]
    ];
    return $tools;
});