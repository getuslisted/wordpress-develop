<?php
/**
 * Leaderboard and gamification logic.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles point accrual, levels, and leaderboard queries.
 */
class RQ_Leaderboard {

    /**
     * Services container.
     *
     * @var array
     */
    protected $services = array();

    /**
     * Constructor.
     *
     * @param array $services Services array.
     */
    public function __construct( $services ) {
        $this->services = $services;

        add_action( 'rq_quiz_submitted', array( $this, 'award_points_for_quiz' ) );
    }

    /**
     * Award points when a quiz is submitted.
     *
     * @param array $payload Submission payload.
     */
    public function award_points_for_quiz( $payload ) {
        $base_points = 50;
        $bonus       = ! empty( $payload['compatibility']['score'] ) ? (int) $payload['compatibility']['score'] : 0;
        $points      = $base_points + (int) floor( $bonus / 10 );

        $this->log_points( $payload['user_id'], $points, 'quiz_submission' );
    }

    /**
     * Persist points in database.
     *
     * @param int    $user_id User ID.
     * @param int    $points  Points awarded.
     * @param string $context Context string.
     */
    public function log_points( $user_id, $points, $context ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_points';

        $wpdb->insert(
            $table,
            array(
                'user_id'   => $user_id,
                'points'    => $points,
                'context'   => sanitize_text_field( $context ),
                'created_at'=> current_time( 'mysql', true ),
            ),
            array( '%d', '%d', '%s', '%s' )
        );
    }

    /**
     * Retrieve leaderboard entries.
     *
     * @param string $range Range key (daily|weekly|monthly|all-time).
     *
     * @return array
     */
    public function get_leaderboard( $range = 'monthly' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_points';

        $where = '1=1';
        switch ( $range ) {
            case 'daily':
                $where = $wpdb->prepare( 'DATE(created_at) = %s', gmdate( 'Y-m-d' ) );
                break;
            case 'weekly':
                $where = $wpdb->prepare( 'YEARWEEK(created_at, 1) = YEARWEEK(%s, 1)', gmdate( 'Y-m-d' ) );
                break;
            case 'monthly':
                $where = $wpdb->prepare( 'DATE_FORMAT(created_at, "%%Y-%%m") = %s', gmdate( 'Y-m' ) );
                break;
            case 'all-time':
            default:
                $where = '1=1';
                break;
        }

        $query = "SELECT user_id, SUM(points) as total FROM {$table} WHERE {$where} GROUP BY user_id ORDER BY total DESC LIMIT 20";
        $rows  = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $leaderboard = array();
        foreach ( $rows as $row ) {
            $user            = get_userdata( $row['user_id'] );
            $leaderboard[] = array(
                'user_id' => (int) $row['user_id'],
                'name'    => $user ? $user->display_name : __( 'Unknown', 'relationship-quizzr' ),
                'points'  => (int) $row['total'],
                'level'   => $this->determine_level( (int) $row['total'] ),
            );
        }

        return $leaderboard;
    }

    /**
     * Determine level from points.
     *
     * @param int $points Points total.
     *
     * @return string
     */
    public function determine_level( $points ) {
        if ( $points > 2000 ) {
            return __( 'Soulmate Sage', 'relationship-quizzr' );
        }

        if ( $points > 1000 ) {
            return __( 'Harmony Hero', 'relationship-quizzr' );
        }

        if ( $points > 500 ) {
            return __( 'Dynamic Duo', 'relationship-quizzr' );
        }

        return __( 'Newly Matched', 'relationship-quizzr' );
    }
}
