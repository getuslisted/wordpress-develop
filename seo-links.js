jQuery(document).ready(function($) {
    $('.dashicons-plus').on('click', function() {
        $('.opportunities').slideUp();
        $(this).next('.opportunities').slideDown();
    });

    $('.button-primary, .button-secondary').on('click', function() {
        var button = $(this);
        var li = button.closest('li');
        var post_id = li.data('post-id');
        var opportunity_id = li.data('opportunity-id');
        var action = button.hasClass('button-primary') ? 'seolinks_create_link' : 'seolinks_dismiss_link';

        $.post(ajaxurl, {
            action: action,
            post_id: post_id,
            opportunity_id: opportunity_id,
            nonce: seolinks_ajax.nonce
        }, function(response) {
            if (response.success) {
                li.fadeOut();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#add-to-first-5, #add-to-first-10').on('click', function() {
        var limit = $(this).is('#add-to-first-5') ? 5 : 10;
        $.post(ajaxurl, {
            action: 'seolinks_bulk_create_links',
            limit: limit,
            nonce: seolinks_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#add-all-external, #add-5-external, #add-10-external').on('click', function() {
        var limit = 0;
        if ( $(this).is('#add-5-external') ) {
            limit = 5;
        } else if ( $(this).is('#add-10-external') ) {
            limit = 10;
        }

        var opportunity_ids = [];
        $('tr[data-opportunity-id]').each(function() {
            opportunity_ids.push($(this).data('opportunity-id'));
        });

        $.post(ajaxurl, {
            action: 'seolinks_bulk_create_external_links',
            keyword: $('input[name="keyword"]').val(),
            url: $('input[name="url"]').val(),
            limit: limit,
            opportunity_ids: opportunity_ids,
            nonce: seolinks_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });
});
