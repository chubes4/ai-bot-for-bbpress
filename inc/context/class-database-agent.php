<?php

namespace AiBot\Context;

/**
 * Database Agent Class
 *
 * Database search engine for WordPress/bbPress content.
 * Provides content search functionality for AI tools.
 */
class Database_Agent {

    /**
     * Constructor
     */
    public function __construct() {
        // Constructor logic if needed (currently empty)
    }

    /**
     * Get forum replies for a topic, sorted by upvote count.
     *
     * @param int $topic_id The topic ID.
     * @param int $limit    Maximum number of replies to retrieve (optional, default: 5).
     * @return array Array of forum reply post objects, sorted by upvote count (descending).
     */
    public function get_forum_replies_sorted_by_upvotes( $topic_id, $limit = 5 ) {
        $replies = [];

        $reply_ids = bbp_get_all_child_ids( $topic_id, bbp_get_reply_post_type() );

        if ( empty( $reply_ids ) ) {
            return $replies; // Return empty array if no replies
        }

        // Array to store replies with upvote counts
        $replies_with_upvotes = [];
        foreach ( $reply_ids as $reply_id ) {
            $upvote_count = get_upvote_count( $reply_id ); // Use the upvote count function from theme
            $replies_with_upvotes[] = [
                'reply_id'     => $reply_id,
                'upvote_count' => $upvote_count,
            ];
        }

        // Sort replies by upvote count in descending order
        usort( $replies_with_upvotes, function( $a, $b ) {
            return $b['upvote_count'] <=> $a['upvote_count']; // Descending sort
        } );

        // Get the top N reply IDs based on the limit
        $top_reply_ids = array_slice( array_column( $replies_with_upvotes, 'reply_id' ), 0, $limit );

        if ( ! empty( $top_reply_ids ) ) {
            $replies = get_posts( [
                'post_type'   => bbp_get_reply_post_type(),
                'post__in'    => $top_reply_ids,
                'orderby'     => 'post__in', // Preserve order from $top_reply_ids
                'order'       => 'ASC',
                'numberposts' => -1, // Retrieve all matching replies
            ] );
        }

        return $replies;
    }

    /**
     * Get the most recent forum replies for a topic.
     *
     * @param int   $topic_id         The topic ID.
     * @param int   $limit            Maximum number of replies to retrieve (optional, default: 3).
     * @param array $exclude_post_ids Array of reply IDs to exclude (optional).
     * @param array $exclude_author_ids Array of author IDs to exclude (optional).
     * @return array Array of forum reply post objects, sorted by date (descending).
     */
    public function get_recent_forum_replies( $topic_id, $limit = 3, $exclude_post_ids = array(), $exclude_author_ids = array() ) {
        $args = [
            'post_type'      => bbp_get_reply_post_type(),
            'post_parent'    => $topic_id,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $exclude_post_ids ) ) {
            $args['post__not_in'] = $exclude_post_ids;
        }

        if ( ! empty( $exclude_author_ids ) ) {
            // Ensure it's an array for the query parameter
            $args['author__not_in'] = (array) $exclude_author_ids;
        }

        $recent_replies = get_posts( $args );
        return $recent_replies;
    }

    /**
     * Get the most recent forum replies by the bot user in a specific topic.
     *
     * @param int   $topic_id    The topic ID.
     * @param int   $bot_user_id The user ID of the bot.
     * @param int   $limit       Maximum number of replies to retrieve (optional, default: 5).
     * @param array $exclude     Array of reply IDs to exclude (optional).
     * @return array Array of forum reply post objects by the bot, sorted by date (descending).
     */
    public function get_bot_replies_in_topic( $topic_id, $bot_user_id, $limit = 5, $exclude = array() ) {
        if ( empty( $bot_user_id ) ) {
            return []; // Cannot fetch bot replies without a user ID
        }

        $args = [
            'post_type'      => bbp_get_reply_post_type(),
            'post_parent'    => $topic_id,
            'author'         => $bot_user_id, // Filter by bot user ID
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! empty( $exclude ) ) {
            $args['post__not_in'] = $exclude;
        }

        $bot_replies = get_posts( $args );
        return $bot_replies;
    }

    /**
     * Get chronological replies for a topic, excluding a specific post.
     *
     * @param int   $topic_id         The topic ID.
     * @param int   $limit            Maximum number of replies to retrieve (optional, default: 10).
     * @param array $exclude_post_ids Array of reply IDs to exclude (optional).
     * @return array Array of forum reply post objects, sorted by date (ascending).
     */
    public function get_chronological_topic_replies( $topic_id, $limit = 10, $exclude_post_ids = array() ) {
        $args = [
            'post_type'      => bbp_get_reply_post_type(),
            'post_parent'    => $topic_id,
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC', // Order newest first
            'post_status'    => 'publish' // Ensure only published replies are fetched
        ];

        if ( ! empty( $exclude_post_ids ) ) {
            $args['post__not_in'] = $exclude_post_ids;
        }

        $replies = get_posts( $args );

        // Note: get_posts already returns oldest first with order=ASC
        return $replies;
    }

    /**
     * Search local WordPress/bbPress content using keyword matching.
     * Used by Local_Search_Tool for AI-powered content retrieval.
     *
     * @param string $query           Search query string.
     * @param int    $limit           Maximum number of results to retrieve (default: 3).
     * @param int    $exclude_post_id Optional. The ID of the post to exclude from search results.
     * @param int    $topic_id        Optional. The ID of the current topic to exclude replies from.
     * @return array Array of relevant post objects.
     */
    public function search_local_content_by_keywords( $query, $limit = 3, $exclude_post_id = null, $topic_id = 0 ) {
        if ( empty( $query ) ) {
            return [];
        }

        $args = [
            'post_type'      => [ 'post', 'page', bbp_get_topic_post_type(), bbp_get_reply_post_type() ],
            's'              => sanitize_text_field( $query ),
            'posts_per_page' => intval( $limit ),
            'post_status'    => 'publish',
            'orderby'        => 'relevance',
        ];

        if ( ! empty( $exclude_post_id ) ) {
            $args['post__not_in'] = [ intval( $exclude_post_id ) ];
        }

        // Filter out replies from the current topic if specified
        if ( ! empty( $topic_id ) ) {
            $topic_reply_ids = bbp_get_all_child_ids( $topic_id, bbp_get_reply_post_type() );
            if ( ! empty( $topic_reply_ids ) ) {
                $existing_exclusions = $args['post__not_in'] ?? [];
                $args['post__not_in'] = array_merge( $existing_exclusions, $topic_reply_ids );
            }
        }

        $query_results = get_posts( $args );
        return $query_results;
    }
}
