(function( $ ) {
    'use strict';

    $(function() {
        $('.lce-undo-button').on('click', function(e) {
            e.preventDefault();

            var $this = $(this);
            var logId = $this.data('log-id');

            if ( ! confirm('Are you sure you want to undo this change?') ) {
                return;
            }

            $this.text('Undoing...').prop('disabled', true);

            $.ajax({
                url: lce_log_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lce_undo_change',
                    nonce: lce_log_ajax.undo_nonce,
                    log_id: logId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $this.text('Undo').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred.');
                    $this.text('Undo').prop('disabled', false);
                }
            });
        });
    });

})( jQuery );
