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

        function setHelpState(help, isOpen) {
                if ( ! help ) {
                        return;
                }

                help.setAttribute('data-lgd-open', isOpen ? 'true' : 'false');

                var toggle = help.querySelector('.lgd-help__icon');

                if ( toggle ) {
                        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                }
        }

        function closeAllHelp(except) {
                var open = document.querySelectorAll('.lgd-help[data-lgd-open="true"]');

                if ( ! open.length ) {
                        return;
                }

                Array.prototype.forEach.call(open, function (help) {
                        if ( except && help === except ) {
                                return;
                        }

                        setHelpState(help, false);
                });
        }

        document.addEventListener('click', function (event) {
                var toggle = event.target.closest('.lgd-help__icon');

                if ( toggle ) {
                        event.preventDefault();
                        var container = toggle.closest('.lgd-help');
                        if ( ! container ) {
                                return;
                        }

                        var isOpen = container.getAttribute('data-lgd-open') === 'true';
                        closeAllHelp(container);
                        setHelpState(container, ! isOpen);
                        return;
                }

                closeAllHelp();

                var target = event.target.closest('[data-lgd-track-event]');

                if ( target ) {
                        trackEvent(target);
                }
        });

        document.addEventListener('keydown', function (event) {
                if ( event.key === 'Escape' ) {
                        closeAllHelp();
                }
        });
})();
