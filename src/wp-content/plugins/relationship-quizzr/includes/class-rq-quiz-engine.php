<?php
/**
 * Quiz engine for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles quiz metadata, scoring, and compatibility calculations.
 */
class RQ_Quiz_Engine {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_meta' ) );
        add_action( 'save_post_rq_quiz', array( $this, 'sync_quiz_questions' ), 10, 3 );
    }

    /**
     * Register post meta fields for quizzes and questions.
     */
    public function register_meta() {
        register_post_meta(
            'rq_question',
            '_rq_question_type',
            array(
                'type'         => 'string',
                'single'       => true,
                'default'      => 'multiple_choice',
                'show_in_rest' => true,
                'auth_callback'=> '__return_true',
            )
        );

        register_post_meta(
            'rq_question',
            '_rq_question_settings',
            array(
                'type'         => 'object',
                'single'       => true,
                'show_in_rest' => array(
                    'schema' => array(
                        'type'       => 'object',
                        'properties' => array(),
                    ),
                ),
                'auth_callback'=> '__return_true',
            )
        );

        register_post_meta(
            'rq_quiz',
            '_rq_question_ids',
            array(
                'type'         => 'array',
                'single'       => true,
                'show_in_rest' => true,
                'auth_callback'=> '__return_true',
            )
        );
    }

    /**
     * Ensure question IDs stored as integers.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function sync_quiz_questions( $post_id, $post, $update ) {
        $questions = get_post_meta( $post_id, '_rq_question_ids', true );
        if ( empty( $questions ) || ! is_array( $questions ) ) {
            return;
        }

        $questions = array_map( 'absint', $questions );
        update_post_meta( $post_id, '_rq_question_ids', array_values( array_filter( $questions ) ) );
    }

    /**
     * Retrieve quiz questions.
     *
     * @param int $quiz_id Quiz ID.
     *
     * @return WP_Post[]
     */
    public function get_quiz_questions( $quiz_id ) {
        $question_ids = get_post_meta( $quiz_id, '_rq_question_ids', true );
        if ( empty( $question_ids ) || ! is_array( $question_ids ) ) {
            return array();
        }

        $args = array(
            'post_type'      => 'rq_question',
            'post__in'       => array_map( 'absint', $question_ids ),
            'posts_per_page' => -1,
            'orderby'        => 'post__in',
        );

        return get_posts( $args );
    }

    /**
     * Submit answers for a quiz.
     *
     * @param int   $quiz_id       Quiz ID.
     * @param int   $user_id       User ID.
     * @param array $answers       Array of question_id => answer value.
     * @param int   $connection_id Optional connection ID.
     *
     * @return array
     */
    public function submit_answers( $quiz_id, $user_id, $answers, $connection_id = null ) {
        if ( empty( $answers ) || ! is_array( $answers ) ) {
            return array();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rq_quiz_answers';

        foreach ( $answers as $question_id => $answer ) {
            $score = $this->score_answer( $question_id, $answer );

            $wpdb->insert(
                $table,
                array(
                    'quiz_id'       => $quiz_id,
                    'question_id'   => absint( $question_id ),
                    'user_id'       => $user_id,
                    'connection_id' => $connection_id,
                    'answer'        => maybe_serialize( $answer ),
                    'score'         => $score,
                    'created_at'    => current_time( 'mysql', true ),
                ),
                array( '%d', '%d', '%d', '%d', '%s', '%f', '%s' )
            );
        }

        $user_score       = $this->calculate_user_score( $quiz_id, $user_id );
        $compatibility    = null;
        $connection_users = array();

        if ( $connection_id ) {
            $compatibility    = $this->calculate_compatibility( $quiz_id, $connection_id );
            $connection_users = $compatibility['users'];
        }

        $result_id = wp_insert_post(
            array(
                'post_type'   => 'rq_result',
                'post_status' => 'publish',
                'post_title'  => sprintf( __( 'Quiz #%1$d result for user %2$d', 'relationship-quizzr' ), $quiz_id, $user_id ),
                'post_author' => $user_id,
                'meta_input'  => array(
                    '_rq_quiz_id'        => $quiz_id,
                    '_rq_connection_id'  => $connection_id,
                    '_rq_user_score'     => $user_score,
                    '_rq_compatibility'  => $compatibility,
                    '_rq_connection_map' => $connection_users,
                ),
            )
        );

        do_action(
            'rq_quiz_submitted',
            array(
                'quiz_id'       => $quiz_id,
                'user_id'       => $user_id,
                'connection_id' => $connection_id,
                'score'         => $user_score,
                'result_id'     => $result_id,
                'compatibility' => $compatibility,
            )
        );

        return array(
            'score'         => $user_score,
            'compatibility' => $compatibility,
            'result_id'     => $result_id,
        );
    }

    /**
     * Score a question answer.
     *
     * @param int   $question_id Question ID.
     * @param mixed $answer      User answer.
     *
     * @return float
     */
    public function score_answer( $question_id, $answer ) {
        $type      = get_post_meta( $question_id, '_rq_question_type', true );
        $settings  = get_post_meta( $question_id, '_rq_question_settings', true );
        $score_max = isset( $settings['max_score'] ) ? (float) $settings['max_score'] : 1.0;

        switch ( $type ) {
            case 'rating':
                $max   = isset( $settings['scale'] ) ? (int) $settings['scale'] : 5;
                $value = min( max( (int) $answer, 0 ), $max );

                return $max > 0 ? ( $value / $max ) * $score_max : 0;

            case 'boolean':
                $truthy = ! empty( $settings['truthy'] ) ? $settings['truthy'] : 'yes';

                return strtolower( $answer ) === strtolower( $truthy ) ? $score_max : 0;

            case 'text':
                return ! empty( $answer ) ? $score_max : 0;

            case 'multiple_choice':
            default:
                $correct = isset( $settings['correct'] ) ? $settings['correct'] : null;
                if ( is_array( $answer ) ) {
                    $answer = array_map( 'strval', $answer );
                }
                if ( is_array( $correct ) ) {
                    sort( $correct );
                    $given = (array) $answer;
                    sort( $given );

                    return $given === $correct ? $score_max : 0;
                }

                return (string) $answer === (string) $correct ? $score_max : 0;
        }
    }

    /**
     * Calculate aggregate score for user.
     *
     * @param int $quiz_id Quiz ID.
     * @param int $user_id User ID.
     *
     * @return float
     */
    public function calculate_user_score( $quiz_id, $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_quiz_answers';

        $sum = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(score) FROM {$table} WHERE quiz_id = %d AND user_id = %d", $quiz_id, $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return $sum ? (float) $sum : 0;
    }

    /**
     * Calculate compatibility for a connection on a quiz.
     *
     * @param int $quiz_id       Quiz ID.
     * @param int $connection_id Connection ID.
     *
     * @return array
     */
    public function calculate_compatibility( $quiz_id, $connection_id ) {
        global $wpdb;
        $table        = $wpdb->prefix . 'rq_quiz_answers';
        $connections  = $wpdb->prefix . 'rq_connections';
        $connection   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$connections} WHERE id = %d", $connection_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $compat_score = 0;
        $questions    = 0;

        if ( empty( $connection['user_one'] ) || empty( $connection['user_two'] ) ) {
            return array(
                'score'  => 0,
                'shared' => 0,
                'users'  => array(),
            );
        }

        $answers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT question_id, user_id, answer FROM {$table} WHERE quiz_id = %d AND connection_id = %d",
                $quiz_id,
                $connection_id
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $grouped = array();
        foreach ( $answers as $row ) {
            $qid = (int) $row['question_id'];
            if ( ! isset( $grouped[ $qid ] ) ) {
                $grouped[ $qid ] = array();
            }
            $grouped[ $qid ][ (int) $row['user_id'] ] = maybe_unserialize( $row['answer'] );
        }

        foreach ( $grouped as $question_id => $by_user ) {
            if ( count( $by_user ) < 2 ) {
                continue;
            }

            $questions++;

            $type     = get_post_meta( $question_id, '_rq_question_type', true );
            $settings = get_post_meta( $question_id, '_rq_question_settings', true );

            $score = $this->compare_answers( $type, $by_user, $settings );
            $compat_score += $score;
        }

        $percentage = $questions > 0 ? round( ( $compat_score / $questions ) * 100, 2 ) : 0;

        return array(
            'score'  => $percentage,
            'shared' => $questions,
            'users'  => array( (int) $connection['user_one'], (int) $connection['user_two'] ),
        );
    }

    /**
     * Compare answers between users by question type.
     *
     * @param string $type     Question type.
     * @param array  $answers  Answers keyed by user ID.
     * @param array  $settings Question settings.
     *
     * @return float
     */
    protected function compare_answers( $type, $answers, $settings ) {
        $values = array_values( $answers );

        switch ( $type ) {
            case 'rating':
                $scale = isset( $settings['scale'] ) ? (int) $settings['scale'] : 5;
                $diff  = abs( (int) $values[0] - (int) $values[1] );
                $scale = max( $scale, 1 );

                return 1 - ( $diff / $scale );

            case 'boolean':
            case 'multiple_choice':
                return strtolower( (string) $values[0] ) === strtolower( (string) $values[1] ) ? 1 : 0;

            case 'text':
            default:
                similar_text( wp_strip_all_tags( $values[0] ), wp_strip_all_tags( $values[1] ), $percent );

                return min( 1, $percent / 100 );
        }
    }
}
