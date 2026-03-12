jQuery(document).ready(function($) {
    $('#claude-chat-submit').on('click', function() {
        var message = $('#claude-chat-input').val();
        if (message.trim() === '') {
            console.log('Empty message, nothing sent.');
            return;
        }

        console.log('Message sent:', message);
        $('#claude-chat-messages').append('<div class="user-message">' + message + '</div>');
        $('#claude-chat-input').val('');

        $.ajax({
            url: claudeChat.ajax_url,
            type: 'POST',
            data: {
                action: 'claude_chat',
                nonce: claudeChat.nonce,
                message: message
            },
            success: function(response) {
                console.log('AJAX-Success:', response);
                if (response.success) {
                    $('#claude-chat-messages').append('<div class="claude-message">' + response.data + '</div>');
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
