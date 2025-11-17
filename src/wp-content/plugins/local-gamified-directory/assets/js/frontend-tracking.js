(function () {
        if ( 'undefined' === typeof window.LGDActivity || 'undefined' === typeof window.fetch ) {
                return;
        }

        function parseDetails(raw) {
                if ( ! raw ) {
                        return {};
                }

                try {
                        return JSON.parse( raw );
                } catch ( error ) {
                        return {};
                }
        }

        function trackEvent(element) {
                var endpoint = window.LGDActivity.root + 'activity';
                var payload = {
                        event: element.getAttribute('data-lgd-track-event'),
                        object_type: element.getAttribute('data-lgd-track-type') || '',
                        object_id: parseInt(element.getAttribute('data-lgd-track-object') || '0', 10) || 0,
                        details: parseDetails(element.getAttribute('data-lgd-track-details'))
                };

                if ( ! payload.event ) {
                        return;
                }

                fetch(endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': window.LGDActivity.nonce
                        },
                        body: JSON.stringify(payload)
                }).catch(function () {
                        // Silently ignore network failures.
                });
        }

        document.addEventListener('click', function (event) {
                var target = event.target.closest('[data-lgd-track-event]');

                if ( target ) {
                        trackEvent(target);
                }
        });
})();
