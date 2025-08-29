(function( $ ) {
	'use strict';

	$(function() {

        var panel = $('#lce-editor-panel');
        var wrapper = $('#lce-frontend-mappings-wrapper');

        // Toggle panel
        $('#lce-editor-toggle').on('click', function() {
            panel.toggle();
        });

        // Function to initialize the color picker on a given element.
        function initColorPicker( element ) {
            element.wpColorPicker();
        }

        // Initialize color pickers for existing fields.
        wrapper.find('.lce-color-picker').each(function(){
            initColorPicker($(this));
        });

        // Add new row
        $('#lce-frontend-add-row').on('click', function() {
            var template = $('#lce-frontend-mapping-template').html();
            var newRow = $(template);
            wrapper.append(newRow);
            initColorPicker(newRow.find('.lce-color-picker'));
        });

        // Remove row
        wrapper.on('click', '.lce-frontend-remove-row', function() {
            if (wrapper.find('.lce-frontend-mapping-row').length > 1) {
                $(this).closest('.lce-frontend-mapping-row').remove();
            } else {
                 $(this).closest('.lce-frontend-mapping-row').find('input').val('');
                 $(this).closest('.lce-frontend-mapping-row').find('.wp-color-picker').wpColorPicker('color', '');
            }
        });

        // Handle form submission
        $('#lce-frontend-form').on('submit', function(e) {
            e.preventDefault();

            var $button = $('#lce-frontend-save');
            $button.text('Saving...').prop('disabled', true);

            var formData = $(this).serialize();

            $.ajax({
                url: lce_ajax_object.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if(response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.text('Save & Refresh').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred.');
                    $button.text('Save & Refresh').prop('disabled', false);
                }
            });
        });

        // Scan for colors
        $('#lce-frontend-scan-colors').on('click', function() {
            var $this = $(this);
            $this.text('Scanning...').prop('disabled', true);

            $.ajax({
                url: lce_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'lce_scan_colors',
                    nonce: lce_ajax_object.scan_nonce
                },
                success: function(response) {
                    if (response.success) {
                        var existingColors = [];
                        wrapper.find('input[name="lce_color_mappings[old_color][]"]').each(function(){
                            if($(this).val()) {
                                existingColors.push($(this).val().toLowerCase());
                            }
                        });

                        response.data.forEach(function(color) {
                            if ($.inArray(color.toLowerCase(), existingColors) === -1) {
                                var template = $('#lce-frontend-mapping-template').html();
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
                    $this.text('Scan Page').prop('disabled', false);
                }
            });
        });
	});

})( jQuery );
