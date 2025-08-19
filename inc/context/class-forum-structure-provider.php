<?php

namespace AiBot\Context;

/**
 * Class Forum_Structure_Provider
 *
 * Responsible for fetching, formatting, and caching the forum structure.
 */
class Forum_Structure_Provider {

    const TRANSIENT_KEY = 'ai_bot_forum_structure_json';
    const CACHE_DURATION = DAY_IN_SECONDS;

    /**
     * Get the forum structure as a JSON string, using cache if available.
     *
     * Checks if bbPress functions are available before proceeding.
     *
     * @return string|null JSON string of the forum structure, or null if bbPress is unavailable or structure cannot be generated.
     */
    public function get_forum_structure_json() {
        // --- Check if bbPress is active before trying to get forum structure ---
        if ( ! function_exists('bbp_get_forums') ) {
            return null; // Cannot generate without bbPress
        }

        // --- Get Cached Forum Structure (JSON) ---
        $forum_structure_json = get_transient( self::TRANSIENT_KEY );

        if ( false === $forum_structure_json ) {
            // Data not in cache or expired, regenerate it
            $site_name = get_bloginfo('name');
            $site_desc = get_bloginfo('description');
            $forum_structure_data = [
                'site_name'        => $site_name ? esc_html($site_name) : 'N/A',
                'site_description' => $site_desc ? esc_html($site_desc) : 'N/A',
                'main_forums'      => [],
            ];

            // Double-check specific functions exist before calling
            if ( function_exists('bbp_get_forum_post_type') && function_exists('bbp_get_public_status_id') ) {
                $forum_args = [
                    'post_type'      => \bbp_get_forum_post_type(),
                    'post_parent'    => 0,
                    'post_status'    => \bbp_get_public_status_id(),
                    'posts_per_page' => 15, // Limit the number of main forums
                    'orderby'        => 'menu_order',
                    'order'          => 'ASC',
                ];

                // Call bbp_get_forums (we already know it exists from the outer check)
                $main_forums = \bbp_get_forums($forum_args);

                if ( ! empty($main_forums) && function_exists('bbp_get_forum_title') && function_exists('bbp_get_forum_content') ) {
                    foreach ($main_forums as $forum) {
                        $forum_title = \bbp_get_forum_title($forum->ID);
                        $forum_desc  = wp_trim_words(wp_strip_all_tags(\bbp_get_forum_content($forum->ID)), 15, '...'); // Slightly longer trim
                        $forum_structure_data['main_forums'][] = [
                            'title'       => $forum_title ? esc_html($forum_title) : 'Untitled Forum',
                            'description' => $forum_desc ? esc_html($forum_desc) : ''
                        ];
                    }
                } elseif ( empty($main_forums) ) {
                    // Log if query returned no results, but functions exist
                } else {
                    // Log if specific loop functions are missing
                }
            } else {
                // Log if primary query functions are missing
            }

            // Encode as JSON
            $forum_structure_json = wp_json_encode($forum_structure_data);
            // Cache the result
            set_transient( self::TRANSIENT_KEY, $forum_structure_json, self::CACHE_DURATION );
        } // End if cache expired

        return $forum_structure_json;
    }
}