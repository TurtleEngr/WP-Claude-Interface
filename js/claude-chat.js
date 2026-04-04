jQuery(document).ready(function($) {
    $('#claude-chat-submit').on('click', function() {
        var userInput = $('#claude-chat-input').val();
        if (userInput.trim() === '') {
            console.log('Empty message, nothing sent.');
            return;
        }

        // FIX: The addon prompt text no longer lives in claudeChat.
        // Send only a boolean flag so the server can append the prompt server-side.
        var addonEnabled = false;
        var $addonCheckbox = $('#claude-chat-addon-checkbox');
        if ($addonCheckbox.length && $addonCheckbox.is(':checked') && claudeChat.addon_enabled) {
            addonEnabled = true;
        }

        console.log('Message sent.');

        // FIX: Use .text() to set the user message so any HTML special
        // characters in the input are treated as plain text, preventing XSS.
        var $userMsg = $('<div class="user-message"></div>').text(userInput);
        $('#claude-chat-messages').append($userMsg);
        $('#claude-chat-input').val('');

        $.ajax({
            url: claudeChat.ajax_url,
            type: 'POST',
            data: {
                action:       'claude_chat',
                nonce:        claudeChat.nonce,
                message:      userInput,
                // FIX: boolean flag only — prompt text stays on the server.
                addon_enabled: addonEnabled ? '1' : '0',
            },
            success: function(response) {
                console.log('AJAX-Success:', response);
                if (response.success) {
                    var $claudeMsg = $('<div class="claude-message"></div>').html(response.data);
                    $('#claude-chat-messages').append($claudeMsg);
                } else {
                    console.log('Response error:', response);
                    $('#claude-chat-messages').append('<div class="error-message">Error: Unable to get a response</div>');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX-Error:', error);
                console.log('AJAX-Status:', status);
                console.log('AJAX-XHR:', xhr);
                $('#claude-chat-messages').append('<div class="error-message">Error: Unable to send message</div>');
            }
        });
    });
});
