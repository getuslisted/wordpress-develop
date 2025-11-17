(function ($) {
    'use strict';

    $(document).on('submit', '.rq-quiz-form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var quizId = $form.data('quiz');
        var connectionId = $form.data('connection') || null;
        var answers = {};

        $form.find('[data-question]').each(function () {
            var $input = $(this);
            var questionId = $input.data('question');
            var value = $input.val();

            if ($input.is(':checkbox')) {
                if (!answers[questionId]) {
                    answers[questionId] = [];
                }
                if ($input.is(':checked')) {
                    answers[questionId].push(value);
                }
            } else if ($input.is(':radio')) {
                if ($input.is(':checked')) {
                    answers[questionId] = value;
                }
            } else {
                answers[questionId] = value;
            }
        });

        var endpoint = (RQSettings.rest_url || '').replace(/\/$/, '');

        fetch(endpoint + '/quizzes/' + quizId, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': RQSettings.nonce || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                answers: answers,
                connection_id: connectionId
            })
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (data && data.score !== undefined) {
                    $form.find('.rq-quiz-result').remove();
                    var message = '<div class="alert alert-success rq-quiz-result">' +
                        'Score: ' + data.score +
                        (data.compatibility ? '<br/>Compatibility: ' + data.compatibility.score + '% ' : '') +
                        '</div>';
                    $form.append(message);
                }
            })
            .catch(function (error) {
                console.error('Quiz submission error', error);
            });
    });
})(jQuery);
