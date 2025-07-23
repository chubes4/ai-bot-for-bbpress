<?php

namespace AiBot\Core;

/**
 * Bot Trigger Service Class
 * 
 * Handles all logic for determining when the bot should be triggered to respond.
 * This includes mention detection, keyword matching, forum restrictions, and self-prevention.
 */
class Bot_Trigger_Service {

    /**
     * Check if the bot should be triggered based on various criteria.
     *
     * @param int    $post_id      The ID of the current post/reply.
     * @param string $post_content The content of the current post/reply.
     * @param int    $topic_id     The ID of the topic.
     * @param int    $forum_id     The ID of the forum.
     * @return bool True if interaction should be triggered, false otherwise.
     */
    public function should_trigger_interaction( $post_id, $post_content, $topic_id, $forum_id ) {
        error_log("AI Bot Trigger: Checking post_id=$post_id, topic_id=$topic_id, forum_id=$forum_id");

        // Get the Bot User ID and Username
        $bot_user_id = get_option( 'ai_bot_user_id' );
        error_log("AI Bot Trigger: bot_user_id=$bot_user_id");
        
        $bot_username = null;
        if ( $bot_user_id ) {
            $bot_user_data = get_userdata( $bot_user_id );
            if ( $bot_user_data ) {
                $bot_username = $bot_user_data->user_login;
                error_log("AI Bot Trigger: bot_username=$bot_username");
            }
        }

        // Prevent bot from responding to its own posts
        $post_author_id = get_post_field( 'post_author', $post_id );
        error_log("AI Bot Trigger: post_author_id=$post_author_id");
        if ( $bot_user_id && $post_author_id == $bot_user_id ) {
            error_log("AI Bot Trigger: Skipping - bot responding to its own post");
            return false;
        }

        // Check forum access restrictions
        if ( ! $this->is_forum_allowed( $forum_id ) ) {
            error_log("AI Bot Trigger: Forum $forum_id not allowed");
            return false;
        }
        error_log("AI Bot Trigger: Forum $forum_id is allowed");

        // 1. Check for mention (only if bot username is configured)
        if ( $this->has_bot_mention( $post_content, $bot_username ) ) {
            error_log("AI Bot Trigger: Bot mention found - TRIGGERING");
            return true;
        }
        error_log("AI Bot Trigger: No bot mention found");

        // 2. Check for keywords
        if ( $this->has_trigger_keywords( $post_content ) ) {
            error_log("AI Bot Trigger: Keywords found - TRIGGERING");
            return true;
        }
        error_log("AI Bot Trigger: No keywords found");

        // Add other trigger conditions here (e.g., scheduled tasks)
        error_log("AI Bot Trigger: No trigger conditions met - NOT TRIGGERING");
        return false; // No trigger condition met
    }

    /**
     * Check if the forum is allowed based on access control settings
     *
     * @param int $forum_id The forum ID to check
     * @return bool True if forum is allowed, false otherwise
     */
    private function is_forum_allowed( $forum_id ) {
        $restriction_mode = get_option( 'ai_bot_forum_restriction', 'all' );
        error_log("AI Bot Trigger: restriction_mode=$restriction_mode");
        
        if ( $restriction_mode === 'selected' ) {
            $allowed_forums = get_option( 'ai_bot_allowed_forums', array() );
            error_log("AI Bot Trigger: allowed_forums=" . print_r($allowed_forums, true));
            if ( ! empty( $allowed_forums ) && ! in_array( $forum_id, (array) $allowed_forums ) ) {
                error_log("AI Bot Trigger: Forum $forum_id not in allowed forums list");
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check if post content contains a bot mention
     *
     * @param string $post_content The post content to check
     * @param string $bot_username The bot's username (or null if not configured)
     * @return bool True if bot is mentioned, false otherwise
     */
    private function has_bot_mention( $post_content, $bot_username ) {
        if ( $bot_username && preg_match( '/@' . preg_quote( $bot_username, '/' ) . '/i', $post_content ) ) {
            return true;
        }
        return false;
    }

    /**
     * Check if post content contains any trigger keywords
     *
     * @param string $post_content The post content to check
     * @return bool True if trigger keywords are found, false otherwise
     */
    private function has_trigger_keywords( $post_content ) {
        $keywords_string = get_option( 'ai_bot_trigger_keywords', '' );
        error_log("AI Bot Trigger: keywords_string='$keywords_string'");
        
        if ( ! empty( $keywords_string ) ) {
            // Split keywords by comma or newline, trim whitespace, remove empty entries
            $keywords = preg_split( '/[\s,]+/', $keywords_string, -1, PREG_SPLIT_NO_EMPTY );
            $keywords = array_map( 'trim', $keywords );
            $keywords = array_filter( $keywords );
            error_log("AI Bot Trigger: parsed keywords=" . print_r($keywords, true));

            if ( ! empty( $keywords ) ) {
                // Create a regex pattern to match any keyword (case-insensitive)
                $escaped_keywords = array_map( function( $keyword ) {
                    return preg_quote( $keyword, '/' );
                }, $keywords );
                $pattern = '/\b(' . implode( '|', $escaped_keywords ) . ')\b/i';
                error_log("AI Bot Trigger: keyword pattern='$pattern'");
                error_log("AI Bot Trigger: checking against content: '$post_content'");
                if ( preg_match( $pattern, $post_content ) ) {
                    error_log("AI Bot Trigger: KEYWORD MATCH FOUND!");
                    return true;
                }
            }
        }
        
        return false;
    }
}