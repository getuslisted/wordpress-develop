(function( $ ) {
	'use strict';

	$(function() {

        // Function to initialize the color picker on a given element.
        function initColorPicker( element ) {
            element.wpColorPicker();
        }

        // Initialize color pickers for existing fields.
        $('.lce-color-picker').each(function(){
            initColorPicker($(this));
        });

        // Add new row
        $('#lce-add-row-button').on('click', function() {
            var template = $('#lce-color-mapping-template').html();
            var newRow = $(template);
            $('#lce-color-mappings-wrapper').append(newRow);
            initColorPicker(newRow.find('.lce-color-picker'));
        });

        // Remove row
        $('#lce-color-mappings-wrapper').on('click', '.lce-remove-row-button', function() {
            // Don't remove the last row
            if ($('.lce-color-mapping-row').length > 1) {
                $(this).closest('.lce-color-mapping-row').remove();
            } else {
                // Clear the values if it is the last row
                $(this).closest('.lce-color-mapping-row').find('input').val('');
                // For color picker, we need to clear the color
                $(this).closest('.lce-color-mapping-row').find('.wp-color-picker').wpColorPicker('color', '');
            }
        });

        // Scan for colors
        $('#lce-scan-colors-button').on('click', function() {
            var $this = $(this);
            $this.text('Scanning...').prop('disabled', true);

            $.ajax({
                url: lce_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'lce_scan_colors',
                    nonce: lce_admin_ajax.scan_nonce
                },
                success: function(response) {
                    if (response.success) {
                        var existingColors = [];
                        $('input[name="lce_color_mappings[old_color][]"]').each(function(){
                            if($(this).val()) {
                                existingColors.push($(this).val().toLowerCase());
                            }
                        });

                        var wrapper = $('#lce-color-mappings-wrapper');
                        response.data.forEach(function(color) {
                            if ($.inArray(color.toLowerCase(), existingColors) === -1) {
                                var template = $('#lce-color-mapping-template').html();
                                var newRow = $(template);
                                newRow.find('input[name="lce_color_mappings[old_color][]"]').val(color);
                                wrapper.append(newRow);
                                initColorPicker(newRow.find('.lce-color-picker'));
                                newRow.find('.wp-color-picker').wpColorPicker('color', color);
                            }
                        });
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred during scanning.');
                },
                complete: function() {
                    $this.text('Scan Homepage for Colors').prop('disabled', false);
                }
            });
        });

	});

})( jQuery );
