<?php
/**
 * Quiz play template.
 *
 * @var WP_Post  $quiz
 * @var WP_Post[] $questions
 * @var array    $atts
 */

if ( ! $quiz ) {
    return;
}

?>
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h4 mb-3"><?php echo esc_html( get_the_title( $quiz ) ); ?></h2>
        <p class="text-muted"><?php echo esc_html( wp_trim_words( $quiz->post_content, 50 ) ); ?></p>
        <form class="rq-quiz-form" data-quiz="<?php echo esc_attr( $quiz->ID ); ?>" data-connection="<?php echo esc_attr( $atts['connection'] ); ?>">
            <?php foreach ( $questions as $index => $question ) :
                $type     = get_post_meta( $question->ID, '_rq_question_type', true );
                $settings = get_post_meta( $question->ID, '_rq_question_settings', true );
                ?>
                <div class="mb-4">
                    <h3 class="h6"><?php echo esc_html( ( $index + 1 ) . '. ' . $question->post_title ); ?></h3>
                    <div class="text-muted small mb-2"><?php echo wp_kses_post( wpautop( $question->post_content ) ); ?></div>
                    <?php if ( 'multiple_choice' === $type ) :
                        $choices = isset( $settings['choices'] ) && is_array( $settings['choices'] ) ? $settings['choices'] : array();
                        $multiple = ! empty( $settings['allow_multiple'] );
                        foreach ( $choices as $choice_key => $choice_label ) :
                            $field_id = 'rq-question-' . $question->ID . '-' . sanitize_title( $choice_key );
                            ?>
                            <div class="form-check">
                                <input class="form-check-input" type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>" name="rq-question-<?php echo esc_attr( $question->ID ); ?><?php echo $multiple ? '[]' : ''; ?>" value="<?php echo esc_attr( $choice_key ); ?>" id="<?php echo esc_attr( $field_id ); ?>" data-question="<?php echo esc_attr( $question->ID ); ?>">
                                <label class="form-check-label" for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $choice_label ); ?></label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ( 'rating' === $type ) :
                        $scale = isset( $settings['scale'] ) ? (int) $settings['scale'] : 5;
                        ?>
                        <input type="range" class="form-range" min="1" max="<?php echo esc_attr( $scale ); ?>" step="1" value="1" data-question="<?php echo esc_attr( $question->ID ); ?>">
                    <?php elseif ( 'boolean' === $type ) :
                        $yes_label = isset( $settings['truthy'] ) ? $settings['truthy'] : __( 'Yes', 'relationship-quizzr' );
                        $no_label  = isset( $settings['falsy'] ) ? $settings['falsy'] : __( 'No', 'relationship-quizzr' );
                        ?>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="rq-question-<?php echo esc_attr( $question->ID ); ?>" value="<?php echo esc_attr( $yes_label ); ?>" data-question="<?php echo esc_attr( $question->ID ); ?>" id="rq-question-<?php echo esc_attr( $question->ID ); ?>-yes">
                            <label class="form-check-label" for="rq-question-<?php echo esc_attr( $question->ID ); ?>-yes"><?php echo esc_html( $yes_label ); ?></label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="rq-question-<?php echo esc_attr( $question->ID ); ?>" value="<?php echo esc_attr( $no_label ); ?>" data-question="<?php echo esc_attr( $question->ID ); ?>" id="rq-question-<?php echo esc_attr( $question->ID ); ?>-no">
                            <label class="form-check-label" for="rq-question-<?php echo esc_attr( $question->ID ); ?>-no"><?php echo esc_html( $no_label ); ?></label>
                        </div>
                    <?php else : ?>
                        <textarea class="form-control" rows="3" data-question="<?php echo esc_attr( $question->ID ); ?>" placeholder="<?php esc_attr_e( 'Type your answer…', 'relationship-quizzr' ); ?>"></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if ( $atts['show_submit'] ) : ?>
                <button type="submit" class="btn btn-success"><?php esc_html_e( 'Submit quiz', 'relationship-quizzr' ); ?></button>
            <?php endif; ?>
        </form>
    </div>
</div>
