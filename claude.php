<?php
/**
 * Plugin Name: Claude 3.x Chat Interface
 * Plugin URI: https://github.com/TurtleEngr/WP-Claude-Interface/tree/main
 * Description: Adds a Claude AI chat interface to your WordPress site using a shortcode.
 * Version: mVerStr
 * Author: Volkan Kücükbudak, enh: TurtleEngr
 */


/* Define the available models */
define('CLAUDE_MODELS', [
        'claude-3-haiku-20240307'      => 'Claude 3.0 Haiku',
        'claude-3-5-haiku-20241022'    => 'Claude 3.5 Haiku',
        'claude-haiku-4-5-20251001'    => 'Claude 4.5 Haiku',
        'claude-3-5-sonnet-20241022'   => 'Claude 3.5 Sonnet',
        'claude-3-7-sonnet-20250219'   => 'Claude 3.7 Sonnet',
        'claude-sonnet-4-5-20250929'   => 'Claude 4.5 Sonnet',
    ]);

/* Register settings */
function claude_chat_register_settings() {
    register_setting('claude_chat_options', 'claude_chat_api_key');
    register_setting('claude_chat_options', 'claude_chat_model');
    register_setting('claude_chat_options', 'claude_chat_temperature');
    register_setting('claude_chat_options', 'claude_chat_max_tokens');
    register_setting('claude_chat_options', 'claude_chat_prefix_prompt', [
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
}
add_action('admin_init', 'claude_chat_register_settings');

/* Enqueue necessary scripts and styles */
function claude_chat_enqueue_scripts() {
    wp_enqueue_style('claude-chat-style', plugin_dir_url(__FILE__) . 'css/claude-chat.css');
    wp_enqueue_script('claude-chat-script', plugin_dir_url(__FILE__) . 'js/claude-chat.js', array('jquery'), 'mVerStr', true);
    wp_localize_script('claude-chat-script', 'claudeChat', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('claude-chat-nonce'),
        ));
}
add_action('wp_enqueue_scripts', 'claude_chat_enqueue_scripts');

/* Shortcode to display the chat interface */
function claude_chat_shortcode() {
    ob_start();
?>
    <div id="claude-chat-interface">
        <div id="claude-chat-messages"></div>
        <textarea id="claude-chat-input" placeholder="Ask Claude something..." rows="3"></textarea>
        <button id="claude-chat-submit">Send</button>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('claude_chat', 'claude_chat_shortcode');

/*
   FIX: Transient-based rate limiter — max 10 requests per minute per IP.
   Returns true when the request is allowed, false when the limit is exceeded.
*/
function claude_chat_check_rate_limit() {
    $ip            = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $transient_key = 'claude_chat_rate_' . md5($ip);
    $count         = get_transient($transient_key);

    if ($count === false) {
        /* First request in this window — start the counter with a
           60-second TTL. */
        set_transient($transient_key, 1, 60);
        return true;
    }

    if (intval($count) >= 10) {
        return false; /* Rate limit exceeded. */
    }

    /* Increment without resetting the existing TTL by reusing the same key. */
    set_transient($transient_key, intval($count) + 1, 60);
    return true;
}


/* AJAX handler for chat requests */
function claude_chat_ajax_handler() {
    check_ajax_referer('claude-chat-nonce', 'nonce');

    /* FIX: Enforce rate limit before doing any further work. */
    if ( ! claude_chat_check_rate_limit() ) {
        wp_send_json_error('Rate limit exceeded. Please wait a moment before sending another message.');
        return;
    }

    /* FIX: Use sanitize_textarea_field so newlines in multi-line messages
       are preserved (sanitize_text_field strips them). */
    $message = sanitize_textarea_field($_POST['message']);

    $response = claude_chat_api_request($message);
    if ($response) {
        wp_send_json_success($response);
    } else {
        wp_send_json_error('Error: No response from API');
    }
}
add_action('wp_ajax_claude_chat',        'claude_chat_ajax_handler');
add_action('wp_ajax_nopriv_claude_chat', 'claude_chat_ajax_handler');

/*
  Logging helpers
*/


/**
 * Returns the absolute filesystem path to a file inside the claude uploads
 * subdirectory, creating the directory if it does not yet exist.
 *
 *                            (default: 'claude')
 *
 * @param string  $log_subdir Subdirectory name inside wp-content/uploads/
 * @param string  $log_file   Filename inside that subdirectory.
 * @return string|false       Absolute path on success, false on failure.
 */
function claude_chat_get_log_path( $log_subdir = 'claude', $log_file = '' ) {
    $upload_info = wp_upload_dir();

    if ( ! empty( $upload_info['error'] ) ) {
        return false;
    }

    /* e.g. /var/www/html/wp-content/uploads/claude */
    $dir = trailingslashit( $upload_info['basedir'] ) . $log_subdir;

    if ( ! is_dir( $dir ) ) {
        /* wp_mkdir_p() creates intermediate directories and returns
           false on failure. */
        if ( ! wp_mkdir_p( $dir ) ) {
            return false;
        }
    }

    return $log_file !== '' ? trailingslashit( $dir ) . $log_file : $dir;
}


/**
 * Appends a user-message / Claude-response entry to claude_log.org in
 * Org-mode format:
 *
 *   ** YYYY-MM-DD HH:MM message
 *   $message
 *   *** response
 *   $response
 *
 * @param string  $message  The sanitised user message sent to the API.
 * @param string  $response The text returned by the Claude API.
 */
function claude_chat_log_message( $message, $response ) {
    $log_subdir = 'claude';
    $log_file   = 'claude_log.org';

    $path = claude_chat_get_log_path( $log_subdir, $log_file );
    if ( $path === false ) {
        return; /* Could not resolve / create the directory — fail silently. */
    }

    $date = new DateTime('now', new DateTimeZone('America/Los_Angeles'));
    $timestamp = $date->format('Y-m-d H:i:s T');

    $entry  = "** {$timestamp} message\n";
    $entry .= $message . "\n";
    $entry .= "*** response\n";
    $entry .= $response . "\n\n";

    /* error_log() mode 3 appends to an arbitrary file. */
    error_log( $entry, 3, $path );
}


/**
 * Appends an error entry to claude.log inside the same uploads subdirectory.
 *
 * @param string  $error_type    Short label, e.g. 'HTTP Error', 'API Error'.
 * @param string  $error_message Full error detail.
 */
function claude_chat_log_error( $error_type, $error_message ) {
    $log_subdir = 'claude';
    $log_file   = 'claude.log';

    $path = claude_chat_get_log_path( $log_subdir, $log_file );
    if ( $path === false ) {
        return;
    }

    $log_message = date( 'Y-m-d H:i:s' ) . " - {$error_type}: {$error_message}\n";
    error_log( $log_message, 3, $path );
}


/*
  Claude API request
*/

/* Claude API request function with logging */
function claude_chat_api_request($message) {
    $api_key       = get_option('claude_chat_api_key');
    $model         = get_option('claude_chat_model');
    $temperature   = get_option('claude_chat_temperature');
    $max_tokens    = get_option('claude_chat_max_tokens');
    $prefix_prompt = trim(get_option('claude_chat_prefix_prompt', ''));

    /* Use the correct API-Endpoint. */
    $url = 'https://api.anthropic.com/v1/messages';

        $headers = array(
        'Content-Type'      => 'application/json',
        'x-api-key'         => $api_key,
        'anthropic-version' => '2023-06-01',
        /* Required to enable cache_control on system/content blocks. */
        'anthropic-beta'    => 'prompt-caching-2024-07-31',
    );

    /*
      FIX: Move the prefix prompt to the dedicated `system` parameter.

      Placing it in `system` gives it architectural separation from
      the conversation turn — it cannot be overridden by "Ignore
      previous instructions…" style user inputs and benefits from
      Claude's distinct system-prompt handling.

      The array form is used (rather than a plain string) so that
      cache_control can be set on the block, preserving the
      prompt-caching benefit of the original implementation.
    */
    $body = array(
        'model'      => $model,
        'max_tokens' => intval($max_tokens),
        'messages'   => array(
            array(
                'role'    => 'user',
                'content' => $message,   /* plain string — no prefix bundled in here */
            ),
        ),
    );

    if ($prefix_prompt !== '') {
        $body['system'] = array(
            array(
                'type'          => 'text',
                'text'          => $prefix_prompt,
                'cache_control' => array('type' => 'ephemeral'),
            ),
        );
    }

    /* Only include temperature when set (0 is falsy but valid, so check !== '') */
    if ($temperature !== '') {
        $body['temperature'] = floatval($temperature);
    }

    $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 60,
        ));

    if (is_wp_error($response)) {
        claude_chat_log_error('HTTP Error', $response->get_error_message());
        return 'Error: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['content'][0]['text'])) {
        $response_text = $data['content'][0]['text'];

        /* Log the user message and Claude response to claude_log.org. */
        claude_chat_log_message( $message, $response_text );

        return $response_text;

    } elseif (isset($data['error'])) {
        claude_chat_log_error('API Error', print_r($data, true));
        return 'API Error: ' . $data['error']['message'];
    } else {
        claude_chat_log_error('Unknown Error', 'Unable to get a response from Claude API. Response: ' . print_r($data, true));
        return 'Error: Unable to get a response from Claude API.';
    }
}


/* 
   Clear Logs handler
   Deletes claude_log.org and claude.log, then redirects back to the
   settings page with a confirmation flag.
*/
function claude_chat_clear_logs() {
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__('Unauthorized', 'claude-chat') );
    }
    check_admin_referer('claude_chat_clear_logs_action', 'claude_chat_clear_logs_nonce');

    foreach ( array('claude_log.org', 'claude.log') as $log_file ) {
        $path = claude_chat_get_log_path('claude', $log_file);
        if ( $path && file_exists($path) ) {
            wp_delete_file($path);
        }
        error_log( "* Log\n", 3, $path );
    }

    wp_redirect( add_query_arg(
        array('page' => 'claude-chat-settings', 'logs-cleared' => '1'),
        admin_url('options-general.php')
    ) );
    exit;
}
add_action('admin_post_claude_chat_clear_logs', 'claude_chat_clear_logs');


/* Add settings page */
function claude_chat_settings_page() {
    add_options_page(
        'Claude Chat Settings',
        'Claude Chat',
        'manage_options',
        'claude-chat-settings',
        'claude_chat_settings_page_html'
    );
}
add_action('admin_menu', 'claude_chat_settings_page');

/* Settings page HTML */
function claude_chat_settings_page_html() {
    $homeUrl = home_url();
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <?php if ( isset($_GET['logs-cleared']) && $_GET['logs-cleared'] === '1' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Log files cleared successfully.', 'claude-chat'); ?></p>
        </div>
        <?php endif; ?>

        <form action="options.php" method="post">
            <?php
    settings_fields('claude_chat_options');
    do_settings_sections('claude-chat-settings');
    submit_button('Save Settings');
?>
        </form>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
              style="margin-top:12px;">
            <input type="hidden" name="action" value="claude_chat_clear_logs">
            <?php wp_nonce_field('claude_chat_clear_logs_action', 'claude_chat_clear_logs_nonce'); ?>
            <?php submit_button('Clear Logs', 'delete', 'claude_chat_clear_logs_submit', false);
            echo '<p>Before clearing the logs, they can be viewed at:<br>';
            echo '<a href="' . home_url('/wp-content/uploads/claude/claude_log.org') . '" target="_blank">';
            echo home_url('/wp-content/uploads/claude/claude_log.org') . '</a><br>';
            echo '<a href="' . home_url('/wp-content/uploads/claude/claude.log') . '" target="_blank">';
            echo home_url('/wp-content/uploads/claude/claude.log') . '</a></p>';
            ?>
        </form>
    </div>
    <?php
}


/* Initialize settings */
function claude_chat_settings_init() {
    add_settings_section(
        'claude_chat_settings_section',
        'Claude API Settings',
        'claude_chat_settings_section_callback',
        'claude-chat-settings'
    );

    add_settings_field(
        'claude_chat_api_key',
        'API Key',
        'claude_chat_api_key_field_callback',   /* FIX: dedicated callback uses type="password" */
        'claude-chat-settings',
        'claude_chat_settings_section',
        array('label_for' => 'claude_chat_api_key')
    );

    add_settings_field(
        'claude_chat_model',
        'Model',
        'claude_chat_model_dropdown_callback',
        'claude-chat-settings',
        'claude_chat_settings_section',
        array('label_for' => 'claude_chat_model')
    );

    add_settings_field(
        'claude_chat_temperature',
        'Temperature',
        'claude_chat_number_field_callback',
        'claude-chat-settings',
        'claude_chat_settings_section',
        array(
            'label_for' => 'claude_chat_temperature',
            'description' => 'Range: 0 to 1',
            'min' => 0,
            'max' => 1,
            'step' => 0.1,
        )
    );

    add_settings_field(
        'claude_chat_max_tokens',
        'Max Tokens',
        'claude_chat_number_field_callback',
        'claude-chat-settings',
        'claude_chat_settings_section',
        array(
            'label_for' => 'claude_chat_max_tokens',
            'description' => 'Range: 1 to 8096',
            'min' => 1,
            'max' => 8096,
        )
    );

    add_settings_field(
        'claude_chat_prefix_prompt',
        'Prefix Prompt',
        'claude_chat_textarea_field_callback',
        'claude-chat-settings',
        'claude_chat_settings_section',
        array(
            'label_for'   => 'claude_chat_prefix_prompt',
            'description' => 'Optional. Sent as the system prompt on every request, keeping it separate from user input. Uses cache_control to save costs. Leave blank to disable.',
        )
    );
}
add_action('admin_init', 'claude_chat_settings_init');

/* Field render callbacks */
function claude_chat_settings_section_callback($args) {
    echo '<p>Enter your Claude API settings below:</p>';
    echo '<p>Click <a href="https://github.com/TurtleEngr/WP-Claude-Interface/blob/main/README.md" target="_blank">HERE</a> for help.</p>';
}


/* FIX: Render the API key as a password field so it is masked in the browser. */
function claude_chat_api_key_field_callback($args) {
    $option = get_option($args['label_for']);
    echo '<input type="password" id="'  . esc_attr($args['label_for'])
        . '" name="'                     . esc_attr($args['label_for'])
        . '" value="'                    . esc_attr($option)
        . '" class="regular-text"'
        . ' autocomplete="new-password">';
    if ( ! empty($args['description'])) {
        echo '<p class="description">' . wp_kses($args['description'], array('code' => array())) . '</p>';
    }
}

function claude_chat_number_field_callback($args) {
    $option = get_option($args['label_for']);
    echo '<input type="number" id="' . esc_attr($args['label_for'])
        . '" name="'                  . esc_attr($args['label_for'])
        . '" value="'                 . esc_attr($option)
        . '" class="regular-text"'
        . ' min="'                    . esc_attr($args['min'])
        . '" max="'                   . esc_attr($args['max'])
        . '" step="'                  . (isset($args['step']) ? esc_attr($args['step']) : '1')
        . '">';
    if ( ! empty($args['description'])) {
        echo '<p class="description">' . wp_kses($args['description'], array('code' => array())) . '</p>';
    }
}


function claude_chat_model_dropdown_callback($args) {
    $selected_model = get_option($args['label_for']);
    echo '<select id="'   . esc_attr($args['label_for'])
        . '" name="'       . esc_attr($args['label_for'])
        . '" class="regular-text">';
    foreach (CLAUDE_MODELS as $model_key => $model_name) {
        $selected = ($selected_model == $model_key) ? 'selected="selected"' : '';
        echo '<option value="' . esc_attr($model_key) . '" ' . $selected . '>'
            . esc_html($model_name) . '</option>';
    }
    echo '</select>';
    if ( ! empty($args['description'])) {
        echo '<p class="description">' . wp_kses($args['description'], array('code' => array())) . '</p>';
    }
}


function claude_chat_textarea_field_callback($args) {
    $option = get_option($args['label_for'], '');
    echo '<textarea id="'   . esc_attr($args['label_for'])
        . '" name="'         . esc_attr($args['label_for'])
        . '" rows="6" cols="60" class="large-text code">'
        . esc_textarea($option)
        . '</textarea>';
    if ( ! empty($args['description'])) {
        echo '<p class="description">' . wp_kses($args['description'], array('code' => array())) . '</p>';
    }
}
