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

    $('.save-keyword').on('click', function() {
        var button = $(this);
        var post_id = button.data('post-id');
        var keyword = button.prev('input').val();

        $.post(ajaxurl, {
            action: 'seolinks_save_keyword',
            post_id: post_id,
            keyword: keyword,
            nonce: seolinks_ajax.nonce
        }, function(response) {
            if (response.success) {
                // Do nothing on success, as the page will be reloaded.
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#scan-for-broken-links').on('click', function() {
        var button = $(this);
        button.text('Scanning...').prop('disabled', true);

        $.post(ajaxurl, {
            action: 'seolinks_scan_for_broken_links',
            nonce: seolinks_ajax.nonce
        }, function(response) {
            button.text('Scan for Broken Links').prop('disabled', false);
            if (response.success) {
                var results = $('#broken-links-results');
                results.empty();
                if (response.data.broken_links.length > 0) {
                    var table = $('<table class="wp-list-table widefat fixed striped"><thead><tr><th>Post</th><th>Broken Link</th></tr></thead><tbody></tbody></table>');
                    $.each(response.data.broken_links, function(index, item) {
                        table.find('tbody').append('<tr><td>' + item.post_title + '</td><td>' + item.link + '</td></tr>');
                    });
                    results.append(table);
                } else {
                    results.append('<p>No broken links found.</p>');
                }
            } else {
                alert(response.data.message);
            }
        });
    });
});
