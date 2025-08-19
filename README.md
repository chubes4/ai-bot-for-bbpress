# AI Bot for bbPress

A professional WordPress plugin that integrates AI-powered bots into bbPress forums with multi-provider support (OpenAI, Anthropic, Google Gemini, Grok, OpenRouter).

## Features

- **Multi-Provider AI Support**: OpenAI, Anthropic, Google Gemini, Grok, OpenRouter
- **AI-Powered Search**: AI intelligently determines and executes search queries for relevant context
- **Smart Bot Triggers**: Responds to @mentions, keywords, and forum restrictions
- **Forum Access Control**: Restrict bot to specific forums with hierarchical selection
- **bbPress Integration**: Full compatibility with bbPress notifications, points systems, and activity feeds
- **Remote Content Integration**: Query external WordPress sites via BBP Bot Helper plugin

## Installation

1. **Requirements**:
   - WordPress 5.0+
   - PHP 7.4+
   - bbPress plugin installed and activated
   - API key from supported AI provider

2. **Install Plugin**:
   ```bash
   # Via WordPress admin
   Plugins > Add New > Upload Plugin > Select ai-bot-for-bbpress.zip

   # Via WordPress CLI
   wp plugin install ai-bot-for-bbpress.zip --activate
   ```

3. **Configure Bot**:
   - Navigate to Settings > Forum AI Bot
   - Configure AI provider and API key
   - Set bot user account and behavior settings

## Configuration

### Basic Setup

```php
// Example: Configure bot via WordPress options
update_option('ai_bot_selected_provider', 'openai');
update_option('ai_bot_selected_model', 'gpt-4');
update_option('ai_bot_user_id', 2); // WordPress user ID for bot account
update_option('ai_bot_trigger_keywords', 'help,question,support');
```

### AI Provider Configuration

The plugin uses the AI HTTP Client library for multi-provider support:

```php
// API keys are managed via ai_provider_api_keys filter
add_filter('ai_provider_api_keys', function($keys) {
    $keys['openai'] = 'sk-your-openai-key-here';
    $keys['anthropic'] = 'your-anthropic-key-here';
    return $keys;
});
```

### Forum Access Control

```php
// Restrict bot to specific forums
update_option('ai_bot_forum_restriction', 'selected');
update_option('ai_bot_allowed_forums', [12, 15, 18]); // Forum IDs
```

### Context Settings

```php
// Configure context retrieval limits
update_option('ai_bot_local_search_limit', 5);    // Local content results
update_option('ai_bot_remote_search_limit', 3);   // Remote content results
update_option('ai_bot_remote_endpoint_url', 'https://example.com/wp-json/');
```

## Usage Examples

### Bot Triggers

**@Mentions**: Bot responds when mentioned in topics or replies
```
@botuser Can you help me with WordPress hooks?
```

**Keywords**: Bot responds to configured trigger keywords
```
I have a question about bbPress integration
```

**Manual Triggers**: Force bot response with specific phrases
```
Hey bot, what do you think about this?
```

### System Prompts

Configure bot personality and behavior:

```php
// System prompt for bot behavior
update_option('ai_bot_system_prompt', 
    'You are a helpful WordPress expert specializing in bbPress forums. 
     Provide clear, actionable advice with code examples when appropriate.'
);

// Additional custom instructions
update_option('ai_bot_custom_prompt',
    'Always include relevant documentation links and suggest best practices.'
);
```

### Temperature Control

```php
// Control AI creativity (0.0-1.0)
update_option('ai_bot_temperature', 0.7); // Balanced creativity
```

## Development

### Architecture

The plugin follows a clean service container pattern with AI tool-based search:

```php
// Main services
AiBot\Core\AiBot                      // WordPress hooks orchestrator
AiBot\Core\Bot_Trigger_Service        // Mention/keyword detection  
AiBot\Core\Generate_Bot_Response      // AI response orchestration with tool calling
AiBot\Core\System_Prompt_Builder      // Centralized prompt construction

// AI search tools
AiBot\Tools\Local_Search_Tool         // AI tool for local content search
AiBot\Tools\Remote_Search_Tool        // AI tool for remote content search

// Context system
AiBot\Context\Content_Interaction_Service  // Conversation message formatting
AiBot\Context\Database_Agent               // Database search engine
```

### Adding Custom AI Tools

```php
// Register custom AI search tool
add_filter('ai_tools', function($tools) {
    $tools['custom_search'] = [
        'name' => 'custom_search',
        'description' => 'Search custom data source for relevant information',
        'class' => 'Custom\Search_Tool',
        'method' => 'execute',
        'category' => 'search',
        'parameters' => [
            'query' => [
                'type' => 'string',
                'description' => 'Search query for custom data',
                'required' => true
            ]
        ]
    ];
    return $tools;
});
```

### Custom Bot Triggers

```php
// Add custom trigger logic
add_filter('ai_bot_should_respond', function($should_respond, $content, $topic_id) {
    // Custom logic for when bot should respond
    if (strpos($content, 'emergency') !== false) {
        return true;
    }
    return $should_respond;
}, 10, 3);

// Modify available tools per request
add_filter('ai_tools', function($tools, $context) {
    // Remove remote search for sensitive topics
    if (isset($context['sensitive']) && $context['sensitive']) {
        unset($tools['remote_search']);
    }
    return $tools;
}, 10, 2);
```

### Response Filtering

```php
// Modify bot responses before posting
add_filter('ai_bot_response_content', function($response, $context) {
    // Add signature or modify response
    return $response . "\n\n*This response was generated by AI*";
}, 10, 2);
```

## Hooks & Filters

### Actions

```php
// Fires when bot posts a reply
do_action('ai_bot_posted_reply', $reply_id, $topic_id, $response_content);

// Fires on AI API errors
do_action('ai_api_error', $error_data);
```

### Filters

```php
// Modify API request before sending
apply_filters('ai_bot_api_request', $request_data, $provider);

// Modify system prompt
apply_filters('ai_bot_system_prompt', $prompt, $context);

// Control bot response decision
apply_filters('ai_bot_should_respond', $should_respond, $content, $topic_id);

// Modify final response
apply_filters('ai_bot_response_content', $response, $context);
```

## Troubleshooting

### Common Issues

**Bot not responding**:
```php
// Check bot configuration
$provider = get_option('ai_bot_selected_provider');
$model = get_option('ai_bot_selected_model'); 
$user_id = get_option('ai_bot_user_id');

// Verify API keys
$api_keys = apply_filters('ai_provider_api_keys', []);
```

**API errors**:
```php
// Enable error logging
add_action('ai_api_error', function($error_data) {
    error_log('AI Bot Error: ' . $error_data['message']);
});
```

**Forum restrictions**:
```php
// Check forum access
$restriction = get_option('ai_bot_forum_restriction');
$allowed_forums = get_option('ai_bot_allowed_forums', []);
```

### Debug Mode

```php
// Enable detailed logging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// View logs
tail -f /wp-content/debug.log
```

## Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Follow WordPress coding standards
4. Test with multiple AI providers
5. Submit pull request

## Requirements

- **WordPress**: 5.0+
- **PHP**: 7.4+
- **bbPress**: Latest version
- **API Keys**: At least one supported AI provider

## License

GPLv2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Support

- **Documentation**: See CLAUDE.md for technical details
- **Issues**: Report bugs via plugin repository
- **WordPress.org**: https://wordpress.org/plugins/ai-bot-for-bbpress/