jQuery(document).ready(function($) {
    $('.gulkl-toggle-opportunities').on('click', function(e) {
        e.preventDefault();
        $(this).next('.gulkl-opportunities').slideToggle();
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
            action: 'gulkl_save_keyword',
            post_id: post_id,
            keyword: keyword,
            nonce: gulkl_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#scan-for-broken-links').on('click', function() {
        var button = $(this);
        var posts = button.data('posts');
        var results = $('#broken-links-results');
        var i = 0;

        button.text('Scanning...').prop('disabled', true);
        results.empty().append('<p>Scanning...</p>');

        function scan_next_post() {
            if (i >= posts.length) {
                button.text('Scan for Broken Links').prop('disabled', false);
                results.find('p').text('Scan complete.');
                return;
            }

            $.post(ajaxurl, {
                action: 'gulkl_scan_for_broken_links',
                post_id: posts[i],
                nonce: gulkl_ajax.nonce
            }, function(response) {
                if (response.success) {
                    if (response.data.broken_links.length > 0) {
                        if (results.find('table').length === 0) {
                            results.empty().append('<table class="wp-list-table widefat fixed striped"><thead><tr><th>Post</th><th>Broken Link</th></tr></thead><tbody></tbody></table>');
                        }
                        $.each(response.data.broken_links, function(index, item) {
                            results.find('tbody').append('<tr><td>' + item.post_title + '</td><td>' + item.link + '</td></tr>');
                        });
                    }
                }
                i++;
                scan_next_post();
            });
        }

        scan_next_post();
    });

    $('.undo-action').on('click', function() {
        var button = $(this);
        var action_id = button.data('action-id');

        $.post(ajaxurl, {
            action: 'gulkl_undo_action',
            action_id: action_id,
            nonce: gulkl_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });
});
