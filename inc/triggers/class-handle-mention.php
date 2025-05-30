<?php

namespace AiBot\Triggers;

use AiBot\Context\Content_Interaction_Service; // Update use statement namespace

/**
 * AI Bot for bbPress - Handle Mention/Trigger Class
 */
class Handle_Mention {

    /**
     * Content Interaction Service instance
     *
     * @var Content_Interaction_Service
     */
    private $content_interaction_service;

    /**
     * Constructor
     *
     * @param Content_Interaction_Service $content_interaction_service The content interaction service instance.
     */
    public function __construct( Content_Interaction_Service $content_interaction_service ) {
        $this->content_interaction_service = $content_interaction_service;
    }

    /**
     * Initialize the handler
     */
    public function init() {
        // Directly register the hooks needed for this handler
        add_action( 'bbp_new_reply', array( $this, 'handle_bot_trigger' ), 9, 5 ); // Priority 9 (Earlier)
        add_action( 'bbp_new_topic', array( $this, 'handle_bot_trigger' ), 9, 4 ); // Priority 9 (Earlier)
    }

    /**
     * Handle mention or keyword trigger
     */
    public function handle_bot_trigger( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author = 0 ) {
        // *** DEBUG LOG: Trigger Start ***
        // error_log("AI Bot Debug: handle_bot_trigger started for Post ID: " . $post_id . " in Topic ID: " . $topic_id);
        // *** END DEBUG LOG ***

        $post_content = ($reply_author == 0) ? bbp_get_topic_content( $post_id ) : bbp_get_reply_content( $post_id );

        // Check if the interaction should be triggered using the injected service
        if ( $this->content_interaction_service->should_trigger_interaction( $post_id, $post_content, $topic_id, $forum_id ) ) {

            // Log that a matching condition for a bot reply was triggered
            // error_log( 'AI Bot Info: Matching condition for bot reply triggered for post ID: ' . $post_id );

            // Schedule cron event to generate and post AI response - Use new event name
            $scheduled = wp_schedule_single_event(
                time(),
                'ai_bot_generate_bot_response_event',
                array( $post_id, $topic_id, $forum_id, $anonymous_data, $reply_author )
            );

            // Log whether the event scheduling was successful
            if ( $scheduled ) {
                // error_log( 'AI Bot Info: Successfully scheduled response event for post ID: ' . $post_id );
            } else {
                // error_log( 'AI Bot Error: FAILED to schedule response event for post ID: ' . $post_id . ' (Might already be scheduled)' );
            }

        }
    }
}