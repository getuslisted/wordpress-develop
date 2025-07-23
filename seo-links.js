jQuery(document).ready(function($) {
    // Tab switching
    $('.sub-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.sub-tab').removeClass('active');
        $(this).addClass('active');
        $('.content').removeClass('active');
        $('#' + tab).addClass('active');
    });

    // Expandable toggle
    window.toggleExpand = function(element) {
        $(element).toggleClass('open');
        $(element).next('.backlinks').slideToggle();
    }

    // Row toggle
    window.toggleRow = function(element) {
        $(element).toggleClass('active');
        var row = $(element).parent();
        row.toggleClass('active');
        row.toggleClass('inactive');
        var span = row.find('span');
        if (row.hasClass('active')) {
            span.text(span.text().replace('❌', '✅'));
        } else {
            span.text(span.text().replace('✅', '❌'));
        }
    }

    // Table sorting
    const table = document.getElementById('sortable-table');
    if (table) {
        const headers = table.querySelectorAll('th');
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const sortKey = header.dataset.sort;
                const rows = Array.from(table.tBodies[0].rows).filter(row => !row.querySelector('.expandable'));
                const isAscending = header.classList.toggle('asc');
                header.classList.toggle('desc', !isAscending);
                rows.sort((a, b) => {
                    const aVal = a.querySelector(`td:nth-child(${Array.from(headers).indexOf(header) + 1})`).textContent.trim();
                    const bVal = b.querySelector(`td:nth-child(${Array.from(headers).indexOf(header) + 1})`).textContent.trim();
                    return isAscending ? aVal.localeCompare(bVal, undefined, {numeric: true}) : bVal.localeCompare(aVal, undefined, {numeric: true});
                });
                rows.forEach(row => table.tBodies[0].appendChild(row));
            });
        });
    }

    $('.gulkl-toggle-opportunities').on('click', function(e) {
        e.preventDefault();
        $(this).closest('.gulkl-main-row').next('.gulkl-opportunities').slideToggle();
    });

    $('.button-primary, .button-secondary').on('click', function() {
        var button = $(this);
        var li = button.closest('li');
        var post_id = li.data('post-id');
        var opportunity_id = li.data('opportunity-id');
        var action = button.hasClass('button-primary') ? 'gulkl_create_link' : 'gulkl_dismiss_link';

        $.post(ajaxurl, {
            action: action,
            post_id: post_id,
            opportunity_id: opportunity_id,
            nonce: gulkl_ajax.nonce
        }, function(response) {
            if (response.success) {
                li.fadeOut();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('#add-to-first-5, #add-to-first-10, #add-all').on('click', function() {
        var limit = 0;
        if ($(this).is('#add-to-first-5')) {
            limit = 5;
        } else if ($(this).is('#add-to-first-10')) {
            limit = 10;
        }

        $.post(ajaxurl, {
            action: 'gulkl_bulk_create_links',
            limit: limit,
            nonce: gulkl_ajax.nonce
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.redirect_url;
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
            action: 'gulkl_bulk_create_external_links',
            keyword: $('input[name="keyword"]').val(),
            url: $('input[name="url"]').val(),
            limit: limit,
            opportunity_ids: opportunity_ids,
            nonce: gulkl_ajax.nonce
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

    $('.gulkl-undo-action').on('click', function() {
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

    $('#undo-all-actions').on('click', function() {
        $.post(ajaxurl, {
            action: 'gulkl_undo_all_actions',
            nonce: gulkl_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    $('.gulkl-remove-all-backlinks').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var post_id = button.data('post-id');

        $.post(ajaxurl, {
            action: 'gulkl_remove_all_internal_backlinks',
            post_id: post_id,
            nonce: gulkl_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message);
            }
        });
    });

    if ( $('#real-time-log').length ) {
        var log = $('#real-time-log');
        var i = 0;

        function process_next_action() {
            $.post(ajaxurl, {
                action: 'gulkl_process_action_queue',
                nonce: gulkl_ajax.nonce
            }, function(response) {
                if (response.success) {
                    if (response.data.message) {
                        log.append('<p>' + response.data.message + '</p>');
                        process_next_action();
                    } else {
                        log.append('<p>All actions complete.</p>');
                    }
                } else {
                    log.append('<p>An error occurred: ' + response.data.message + '</p>');
                }
            });
        }

        process_next_action();
    }
});
