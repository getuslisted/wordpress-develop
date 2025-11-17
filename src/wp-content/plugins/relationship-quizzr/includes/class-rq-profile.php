<?php
/**
 * Profile generation for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Builds public-facing profile data from quiz history.
 */
class RQ_Profile {

    /**
     * Services container.
     *
     * @var array
     */
    protected $services = array();

    /**
     * Constructor.
     *
     * @param array $services Services.
     */
    public function __construct( $services ) {
        $this->services = $services;
    }

    /**
     * Retrieve public profile payload.
     *
     * @param int $user_id User ID.
     *
     * @return array
     */
    public function get_public_profile( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return array();
        }

        $stats = $this->get_quiz_statistics( $user_id );

        return array(
            'user_id'        => $user_id,
            'display_name'   => $user->display_name,
            'avatar'         => get_avatar_url( $user_id ),
            'bio'            => get_user_meta( $user_id, '_rq_bio', true ),
            'pronouns'       => get_user_meta( $user_id, '_rq_pronouns', true ),
            'location'       => get_user_meta( $user_id, '_rq_location', true ),
            'favorite_topic' => $stats['favorite_topic'],
            'compatibility'  => $stats['compatibility'],
            'quizzes_taken'  => $stats['quizzes_taken'],
            'badges'         => $this->get_badges( $user_id, $stats ),
        );
    }

    /**
     * Allow user to update profile fields.
     *
     * @param int   $user_id User ID.
     * @param array $fields  Fields to update.
     *
     * @return bool
     */
    public function update_profile_fields( $user_id, $fields ) {
        $allowed = $this->get_editable_fields();
        $updated = false;

        foreach ( $fields as $key => $value ) {
            if ( ! in_array( $key, $allowed, true ) ) {
                continue;
            }

            switch ( $key ) {
                case 'bio':
                    update_user_meta( $user_id, '_rq_bio', wp_kses_post( $value ) );
                    $updated = true;
                    break;
                case 'pronouns':
                    update_user_meta( $user_id, '_rq_pronouns', sanitize_text_field( $value ) );
                    $updated = true;
                    break;
                case 'location':
                    update_user_meta( $user_id, '_rq_location', sanitize_text_field( $value ) );
                    $updated = true;
                    break;
                case 'age_verified':
                    update_user_meta( $user_id, '_rq_age_verified', (bool) $value );
                    $updated = true;
                    break;
            }
        }

        return $updated;
    }

    /**
     * Return a list of fields end users may update manually.
     *
     * @return array
     */
    public function get_editable_fields() {
        return array( 'bio', 'pronouns', 'location', 'age_verified' );
    }

    /**
     * Aggregate quiz statistics for a user.
     *
     * @param int $user_id User ID.
     *
     * @return array
     */
    protected function get_quiz_statistics( $user_id ) {
        $results = get_posts(
            array(
                'post_type'      => 'rq_result',
                'posts_per_page' => 50,
                'author'         => $user_id,
                'post_status'    => 'publish',
            )
        );

        $quizzes_taken = count( $results );
        $compat_scores = array();
        $topics        = array();

        foreach ( $results as $result ) {
            $compatibility = get_post_meta( $result->ID, '_rq_compatibility', true );
            if ( isset( $compatibility['score'] ) ) {
                $compat_scores[] = (float) $compatibility['score'];
            }

            $quiz_id = (int) get_post_meta( $result->ID, '_rq_quiz_id', true );
            if ( $quiz_id ) {
                $terms = wp_get_post_terms( $quiz_id, 'rq_category', array( 'fields' => 'names' ) );
                foreach ( $terms as $term ) {
                    $topics[ $term ] = isset( $topics[ $term ] ) ? $topics[ $term ] + 1 : 1;
                }
            }
        }

        arsort( $topics );

        return array(
            'quizzes_taken' => $quizzes_taken,
            'compatibility' => $compat_scores ? round( array_sum( $compat_scores ) / count( $compat_scores ), 2 ) : 0,
            'favorite_topic'=> $topics ? key( $topics ) : __( 'General', 'relationship-quizzr' ),
        );
    }

    /**
     * Determine earned badges.
     *
     * @param int   $user_id User ID.
     * @param array $stats   Stats array.
     *
     * @return array
     */
    protected function get_badges( $user_id, $stats ) {
        $badges = array();

        if ( $stats['quizzes_taken'] >= 10 ) {
            $badges[] = array(
                'slug'  => 'quiz-champion',
                'label' => __( 'Quiz Champion', 'relationship-quizzr' ),
            );
        }

        if ( $stats['compatibility'] >= 80 ) {
            $badges[] = array(
                'slug'  => 'perfect-pair',
                'label' => __( 'Perfect Pair', 'relationship-quizzr' ),
            );
        }

        $referrals = $this->services['referrals']->get_referral_stats( $user_id );
        if ( ! empty( $referrals['paid'] ) ) {
            $badges[] = array(
                'slug'  => 'social-butterfly',
                'label' => __( 'Social Butterfly', 'relationship-quizzr' ),
            );
        }

        return $badges;
    }
}
