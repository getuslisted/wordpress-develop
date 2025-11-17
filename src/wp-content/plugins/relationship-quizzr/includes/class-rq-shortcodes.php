<?php
/**
 * Shortcode renderers.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers shortcodes for frontend output.
 */
class RQ_Shortcodes {

    /**
     * Service map.
     *
     * @var array
     */
    protected $services = array();

    /**
     * Constructor.
     *
     * @param array $services Services container.
     */
    public function __construct( $services ) {
        $this->services = $services;

        add_shortcode( 'rq_quiz_list', array( $this, 'render_quiz_list' ) );
        add_shortcode( 'rq_quiz_play', array( $this, 'render_quiz_play' ) );
        add_shortcode( 'rq_leaderboard', array( $this, 'render_leaderboard' ) );
        add_shortcode( 'rq_profile', array( $this, 'render_profile' ) );
    }

    /**
     * Render quiz list shortcode.
     *
     * @param array $atts Attributes.
     *
     * @return string
     */
    public function render_quiz_list( $atts ) {
        $atts = shortcode_atts(
            array(
                'category' => '',
                'limit'    => 8,
            ),
            $atts
        );

        $args = array(
            'post_type'      => 'rq_quiz',
            'posts_per_page' => absint( $atts['limit'] ),
            'post_status'    => 'publish',
        );

        if ( ! empty( $atts['category'] ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'rq_category',
                    'field'    => 'slug',
                    'terms'    => sanitize_title( $atts['category'] ),
                ),
            );
        }

        $quizzes        = get_posts( $args );
        $current_user   = get_current_user_id();
        $subscriptions  = isset( $this->services['subscriptions'] ) ? $this->services['subscriptions'] : null;
        $can_view_adult = $subscriptions && $current_user ? $subscriptions->can_access_adult_category( $current_user ) : false;

        if ( ! empty( $atts['category'] ) && 'adult' === sanitize_title( $atts['category'] ) && ! $can_view_adult ) {
            return '<div class="rq-empty">' . esc_html__( 'Adult quizzes are available to verified subscribers only.', 'relationship-quizzr' ) . '</div>';
        }

        $quizzes = array_filter(
            $quizzes,
            function ( $quiz ) use ( $can_view_adult ) {
                $categories = wp_get_post_terms( $quiz->ID, 'rq_category', array( 'fields' => 'slugs' ) );
                if ( is_wp_error( $categories ) ) {
                    $categories = array();
                }

                return $can_view_adult || ! in_array( 'adult', $categories, true );
            }
        );

        if ( empty( $quizzes ) ) {
            return '<div class="rq-empty">' . esc_html__( 'No quizzes available yet.', 'relationship-quizzr' ) . '</div>';
        }

        ob_start();
        echo '<div class="rq-quiz-grid row g-4">';
        foreach ( $quizzes as $quiz ) {
            $template = RQ_PLUGIN_PATH . 'templates/quiz-card.php';
            if ( file_exists( $template ) ) {
                include $template;
            }
        }
        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Render play quiz shortcode.
     *
     * @param array $atts Attributes.
     *
     * @return string
     */
    public function render_quiz_play( $atts ) {
        $atts = shortcode_atts(
            array(
                'id'           => 0,
                'connection'   => 0,
                'show_submit'  => true,
            ),
            $atts
        );

        $quiz_id = absint( $atts['id'] );
        if ( ! $quiz_id ) {
            return '';
        }

        $quiz      = get_post( $quiz_id );
        $questions = $this->services['quiz_engine']->get_quiz_questions( $quiz_id );

        if ( ! $quiz || 'rq_quiz' !== $quiz->post_type ) {
            return '';
        }

        $subscriptions  = isset( $this->services['subscriptions'] ) ? $this->services['subscriptions'] : null;
        $current_user   = get_current_user_id();
        $categories     = wp_get_post_terms( $quiz_id, 'rq_category', array( 'fields' => 'slugs' ) );
        if ( is_wp_error( $categories ) ) {
            $categories = array();
        }
        $requires_adult = in_array( 'adult', $categories, true );
        $can_view_adult = $subscriptions && $current_user ? $subscriptions->can_access_adult_category( $current_user ) : false;

        if ( $requires_adult && ! $can_view_adult ) {
            return '<div class="rq-empty">' . esc_html__( 'You must be an age-verified subscriber to view this quiz.', 'relationship-quizzr' ) . '</div>';
        }

        ob_start();
        $template = RQ_PLUGIN_PATH . 'templates/quiz-play.php';
        if ( file_exists( $template ) ) {
            include $template;
        }

        return ob_get_clean();
    }

    /**
     * Render leaderboard shortcode.
     *
     * @param array $atts Attributes.
     *
     * @return string
     */
    public function render_leaderboard( $atts ) {
        $atts = shortcode_atts(
            array(
                'range' => 'monthly',
            ),
            $atts
        );

        $leaderboard = $this->services['leaderboard']->get_leaderboard( $atts['range'] );

        ob_start();
        $template = RQ_PLUGIN_PATH . 'templates/leaderboard.php';
        if ( file_exists( $template ) ) {
            $entries = $leaderboard;
            include $template;
        }

        return ob_get_clean();
    }

    /**
     * Render profile shortcode.
     *
     * @param array $atts Attributes.
     *
     * @return string
     */
    public function render_profile( $atts ) {
        $atts = shortcode_atts(
            array(
                'user_id' => get_current_user_id(),
            ),
            $atts
        );

        $profile = $this->services['profile']->get_public_profile( absint( $atts['user_id'] ) );

        ob_start();
        $template = RQ_PLUGIN_PATH . 'templates/profile-card.php';
        if ( file_exists( $template ) ) {
            include $template;
        }

        return ob_get_clean();
    }
}
