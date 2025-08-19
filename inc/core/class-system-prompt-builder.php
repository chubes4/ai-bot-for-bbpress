<?php

namespace AiBot\Core;

use AiBot\Context\Forum_Structure_Provider;

/**
 * System Prompt Builder Class
 * 
 * Centralizes all logic related to constructing prompts and instructions sent to the AI model.
 */
class System_Prompt_Builder {

    /**
     * @var Forum_Structure_Provider
     */
    private $forum_structure_provider;

    /**
     * Constructor
     *
     * @param Forum_Structure_Provider $forum_structure_provider The forum structure provider instance.
     */
    public function __construct(
        Forum_Structure_Provider $forum_structure_provider
    ) {
        $this->forum_structure_provider = $forum_structure_provider;
    }

    /**
     * Build complete system prompt with all context and instructions
     *
     * @param string $bot_username The bot's username for identity instructions
     * @return string Complete system prompt ready for API
     */
    public function build_system_prompt( $bot_username ) {
        $system_prompt = get_option( 'ai_bot_system_prompt' );
        
        // Build all instruction blocks
        $date_instruction = $this->build_date_time_instruction();
        $bot_identity_instruction = $this->build_bot_identity_instruction( $bot_username );
        $forum_context = $this->build_forum_context_instruction();
        
        // Combine: Date + Identity + Forum Context + Base System Prompt
        return $date_instruction . $bot_identity_instruction . $forum_context . "\n\n" . $system_prompt;
    }

    /**
     * Build response format instructions for the user prompt
     *
     * @param string $bot_username The bot's username
     * @param string $triggering_username_slug The username slug of the user who triggered the bot
     * @param string $remote_host The hostname of the remote knowledge source
     * @return string Formatted response instructions
     */
    public function build_response_instructions( $bot_username, $triggering_username_slug, $remote_host ) {
        return sprintf(
            "\n--- Your Response Instructions ---\n".
            "1. Understand the user query from the 'CURRENT INTERACTION' section. The user who wrote this (and who you are replying to) is @%s.\n".
            "2. Use the conversation history provided in the message structure above to understand the flow of discussion and maintain context.\n".
            "3. Primarily use information from 'RELEVANT KNOWLEDGE BASE (LOCAL)' and 'RELEVANT KNOWLEDGE BASE (REMOTE)' to answer the query, if they contain relevant information.\n".
            "4. You are @%s. Address @%s directly in your reply if appropriate (e.g., 'Hi @%s, ...').\n".
            "5. **Mentioning Users:** When you need to mention users from the conversation, use the `@username-slug` format from their message prefixes. If you are addressing the user who triggered you, use @%s.\n".
            "6. Cite the source URL (if provided in the context) primarily when presenting specific facts, figures, or direct quotes from the 'RELEVANT KNOWLEDGE BASE' sections (local or remote) to support your response. For general discussion drawing on the context, citation is less critical.\n".
            "7. Your *entire* response must be formatted using only HTML tags suitable for direct rendering in a web page (e.g., <p>, <b>, <i>, <a>, <ul>, <ol>, <li>). \n".
            "8. **Important:** Do NOT wrap your response in Markdown code fences (like ```html) or any other non-HTML wrappers.\n".
            "9. Prefer local knowledge base information if available and relevant.\n".
            "10. Use the remote knowledge base (from %s) to supplement local information or when local context is insufficient.",
            $triggering_username_slug, // For instruction 1
            $bot_username,             // For instruction 4 (who the bot is)
            $triggering_username_slug, // For instruction 4 (who to address)
            $triggering_username_slug, // For instruction 4 (example)
            $triggering_username_slug, // For instruction 5
            $remote_host               // For instruction 10
        );
    }


    /**
     * Build date/time instruction block
     *
     * @return string Formatted date/time instructions
     */
    private function build_date_time_instruction() {
        // Get the current time according to WordPress settings
        $current_datetime = current_time( 'mysql' ); // Format: YYYY-MM-DD HH:MM:SS
        $current_date = current_time( 'Y-m-d' ); // Format: YYYY-MM-DD

        return sprintf(
            "--- MANDATORY TIME CONTEXT ---\n".
            "CURRENT DATE & TIME: %s\n".
            "RULE: You MUST treat %s as the definitive 'today' for determining past/present/future tense.\n".
            "ACTION: Frame all events relative to %s. Use past tense for completed events. Use present/future tense appropriately ONLY for events happening on or after %s.\n".
            "CONSTRAINT: DO NOT discuss events completed before %s as if they are still upcoming.\n".
            "KNOWLEDGE CUTOFF: Your internal knowledge cutoff is irrelevant; operate solely based on this date and provided context.\n".
            "--- END TIME CONTEXT ---",
            $current_datetime,
            $current_date,
            $current_date,
            $current_date,
            $current_date
        );
    }

    /**
     * Build bot identity instruction block
     *
     * @param string $bot_username The bot's username
     * @return string Formatted bot identity instructions
     */
    private function build_bot_identity_instruction( $bot_username ) {
        return sprintf(
            "\n--- YOUR IDENTITY ---\n".
            "YOUR USERNAME: @%s\n".
            "IMPORTANT: You are @%s in this forum. When users mention @%s, they are talking TO YOU, not about someone else.\n".
            "WHEN MENTIONED: Recognize that @%s mentions are directed at you personally and respond accordingly.\n".
            "SELF-REFERENCE: You may refer to yourself as @%s when appropriate in conversations.\n".
            "--- END IDENTITY ---",
            $bot_username,
            $bot_username,
            $bot_username,
            $bot_username,
            $bot_username
        );
    }

    /**
     * Build forum structure context instruction block
     *
     * @return string Formatted forum structure context or empty string
     */
    private function build_forum_context_instruction() {
        $forum_structure_json = $this->forum_structure_provider->get_forum_structure_json();
        
        // Check if the provider returned valid JSON
        if ( ! is_null($forum_structure_json) && json_decode($forum_structure_json) !== null ) {
            $forum_context_header = "--- FORUM CONTEXT ---\n";
            $forum_context_header .= "The following JSON object describes the structure of this forum site. Use this information to understand the site's overall organization and purpose when formulating your responses:\n";
            $forum_context_footer = "\n--- END FORUM CONTEXT ---\n";

            return $forum_context_header . $forum_structure_json . $forum_context_footer;
        }
        
        return '';
    }
}