(function( $ ) {
    'use strict';

    $( function() { // Equivalent to $(document).ready()
        if (typeof gpdAdminUiParams === 'undefined') {
            console.error('Gemini Duplicator: gpdAdminUiParams not defined.');
            return;
        }

        const screen = $('.wrap h1.wp-heading-inline'); // Target the main heading (e.g., "Pages" or "Posts")
        if (screen.length) {
            const buttonHtml =
                '<a href="' +
                gpdAdminUiParams.addNewAiUrl +
                '" class="page-title-action gpd-add-new-ai-button">' +
                gpdAdminUiParams.buttonText +
                '</a>';
            
            // Check if button already exists to prevent duplicates on AJAX reloads within list table
            if( $('.gpd-add-new-ai-button').length === 0 ){
                screen.after(buttonHtml); // Add button after the h1 heading
            }
        }
    });

})( jQuery );
