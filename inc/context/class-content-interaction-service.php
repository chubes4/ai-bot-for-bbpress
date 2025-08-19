<?php

namespace AiBot\Context;

use AiBot\Context\Database_Agent;

/**
 * Content Interaction Service Class
 *
 * Orchestrates conversation formatting for AI providers using tool-based architecture.
 * No longer handles search directly - AI uses tools for search functionality.
 */
class Content_Interaction_Service {

    /**
     * Database Agent instance
     *
     * @var Database_Agent
     */
    private $database_agent;

    /**
     * Constructor
     *
     * @param Database_Agent $database_agent Instance of the database agent.
     */
    public function __construct( Database_Agent $database_agent ) {
        $this->database_agent = $database_agent;
    }


    /**
     * Get current interaction context
     *
     * @param int    $post_id      The ID of the current post/reply.
     * @param string $post_content The content of the current post/reply.
     * @param int    $topic_id     The ID of the topic.
     * @param int    $forum_id     The ID of the forum.
     * @return string Formatted current interaction context
     */
    public function get_current_interaction_context( $post_id, $post_content, $topic_id, $forum_id ) {
        $context = "--- CURRENT INTERACTION ---\n";
        
        $forum_title = bbp_get_forum_title( $forum_id );
        $topic_title = bbp_get_topic_title( $topic_id );
        $context .= "Forum: " . $forum_title . "\n";
        $context .= "Topic: " . $topic_title . "\n";

        // Get author of the current post
        $triggering_post_author_id = get_post_field( 'post_author', $post_id );
        $triggering_author_slug = 'anonymous';
        if ( $triggering_post_author_id ) {
            $triggering_author_data = get_userdata( $triggering_post_author_id );
            if ( $triggering_author_data ) {
                $triggering_author_slug = "@" . $triggering_author_data->user_nicename;
            }
        }
        $context .= "Author of Current Post: " . $triggering_author_slug . "\n";

        // Add the post content that triggered the bot
        $cleaned_post_content = html_entity_decode( wp_strip_all_tags( $post_content ), ENT_QUOTES, 'UTF-8' );
        $cleaned_post_content = str_replace( "\u{00A0}", " ", $cleaned_post_content );
        $context .= "Current Post Content:\n" . trim( $cleaned_post_content ) . "\n";
        $context .= "--- END CURRENT INTERACTION ---\n\n";

        return $context;
    }

    /**
     * Get conversation history as structured array for AI provider message flow
     *
     * @param int $post_id      The ID of the current post/reply.
     * @param string $post_content The content of the current post/reply.
     * @param int $topic_id     The ID of the topic.
     * @param int $forum_id     The ID of the forum.
     * @return array Array of conversation messages with role/content structure
     */
    public function get_conversation_messages( $post_id, $post_content, $topic_id, $forum_id ) {
        $messages = array();
        
        $bot_user_id = get_option( 'ai_bot_user_id' );
        
        // --- Add Topic Starter as First User Message ---
        $topic_starter_post = get_post($topic_id);
        if ($topic_starter_post && $topic_starter_post->post_author != $bot_user_id) {
            $starter_content = bbp_get_topic_content( $topic_id );
            $starter_author_obj = get_userdata( $topic_starter_post->post_author );
            $starter_author_name = $starter_author_obj ? '@' . $starter_author_obj->user_nicename : '@anonymous';
            
            $cleaned_starter_content = trim(html_entity_decode( wp_strip_all_tags( $starter_content ), ENT_QUOTES, 'UTF-8' ));
            
            $messages[] = array(
                'role' => 'user',
                'content' => $starter_author_name . ': ' . $cleaned_starter_content
            );
        }
        
        // --- Add Chronological Replies ---
        $reply_limit = (int) get_option('ai_bot_reply_history_limit', 10);
        $chronological_replies = $this->database_agent->get_chronological_topic_replies( $topic_id, $reply_limit, array( $post_id ) );
        
        if ( ! empty( $chronological_replies ) ) {
            // Reverse to get oldest first
            $ordered_replies = array_reverse( $chronological_replies );
            
            foreach ( $ordered_replies as $reply ) {
                $reply_content = bbp_get_reply_content( $reply->ID );
                $reply_author_obj = get_userdata( $reply->post_author );
                $is_bot = ( $bot_user_id && $reply->post_author == $bot_user_id );
                
                // Clean content
                $cleaned_content = trim(html_entity_decode( wp_strip_all_tags( $reply_content ), ENT_QUOTES, 'UTF-8' ));
                
                if ( $is_bot ) {
                    // Bot's previous response
                    $messages[] = array(
                        'role' => 'assistant',
                        'content' => $cleaned_content
                    );
                } else {
                    // User message
                    $author_name = $reply_author_obj ? '@' . $reply_author_obj->user_nicename : '@anonymous';
                    $messages[] = array(
                        'role' => 'user',
                        'content' => $author_name . ': ' . $cleaned_content
                    );
                }
            }
        }
        
        // --- Add Current Triggering Message ---
        $triggering_author_obj = get_userdata( get_post($post_id)->post_author );
        $triggering_author_name = $triggering_author_obj ? '@' . $triggering_author_obj->user_nicename : '@anonymous';
        $cleaned_current_content = trim(html_entity_decode( wp_strip_all_tags( $post_content ), ENT_QUOTES, 'UTF-8' ));
        
        $messages[] = array(
            'role' => 'user',
            'content' => $triggering_author_name . ': ' . $cleaned_current_content
        );
        
        return $messages;
    }

} // End class Content_Interaction_Service