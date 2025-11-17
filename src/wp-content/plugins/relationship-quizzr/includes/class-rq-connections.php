<?php
/**
 * Connection management for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles user invitations and pairings.
 */
class RQ_Connections {

    /**
     * Allowed relationship types.
     *
     * @var array
     */
    protected $allowed_types = array( 'spouse', 'partner', 'friend' );

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'user_register', array( $this, 'maybe_auto_accept_pending_invites' ) );
    }

    /**
     * Expose the supported relationship types.
     *
     * @return array
     */
    public function get_allowed_types() {
        return $this->allowed_types;
    }

    /**
     * Create a connection invitation.
     *
     * @param int    $user_id Inviter user ID.
     * @param string $email         Email address for invitation.
     * @param string $type          Relationship type.
     * @param string $referral_code Optional referral code.
     *
     * @return array|WP_Error
     */
    public function create_invitation( $user_id, $email, $type, $referral_code = '' ) {
        $type = strtolower( sanitize_key( $type ) );

        if ( ! in_array( $type, $this->allowed_types, true ) ) {
            return new WP_Error( 'invalid_type', __( 'Invalid connection type.', 'relationship-quizzr' ) );
        }

        $email = sanitize_email( $email );
        $referral_code = sanitize_text_field( $referral_code );

        if ( $referral_code ) {
            $target_id = $this->get_user_id_by_referral_code( $referral_code );

            if ( ! $target_id ) {
                return new WP_Error( 'invalid_referral_code', __( 'Referral code not found.', 'relationship-quizzr' ) );
            }

            if ( (int) $target_id === (int) $user_id ) {
                return new WP_Error( 'self_referral', __( 'You cannot connect with your own referral code.', 'relationship-quizzr' ) );
            }

            return $this->create_accepted_connection( $user_id, (int) $target_id, $type );
        }

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'invalid_email', __( 'Please enter a valid email address or referral code.', 'relationship-quizzr' ) );
        }

        global $wpdb;

        $hash  = wp_generate_password( 20, false, false );
        $table = $wpdb->prefix . 'rq_connections';

        $wpdb->insert(
            $table,
            array(
                'connection_hash'   => $hash,
                'user_one'          => $user_id,
                'email'             => sanitize_email( $email ),
                'relationship_type' => sanitize_text_field( $type ),
                'status'            => 'pending',
                'created_at'        => current_time( 'mysql', true ),
            ),
            array( '%s', '%d', '%s', '%s', '%s', '%s' )
        );

        $invite = array(
            'hash'  => $hash,
            'email' => $email,
            'type'  => $type,
            'from'  => $user_id,
        );

        do_action( 'rq_connection_invite', $invite );

        return $invite;
    }

    /**
     * Retrieve user ID by referral code.
     *
     * @param string $code Referral code.
     *
     * @return int|null
     */
    protected function get_user_id_by_referral_code( $code ) {
        $users = get_users(
            array(
                'meta_key'   => '_rq_referral_code',
                'meta_value' => $code,
                'number'     => 1,
                'fields'     => 'ID',
            )
        );

        return ! empty( $users ) ? (int) $users[0] : null;
    }

    /**
     * Create a connection that is immediately accepted.
     *
     * @param int    $user_one User initiating the connection.
     * @param int    $user_two Target user.
     * @param string $type     Connection type.
     *
     * @return array|WP_Error
     */
    protected function create_accepted_connection( $user_one, $user_two, $type ) {
        global $wpdb;

        $type = sanitize_text_field( $type );

        $user_one_data = get_userdata( $user_one );
        $user_two_data = get_userdata( $user_two );

        if ( ! $user_one_data || ! $user_two_data ) {
            return new WP_Error( 'connection_users_missing', __( 'Unable to locate both users for this connection.', 'relationship-quizzr' ) );
        }

        $table    = $wpdb->prefix . 'rq_connections';
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE ( user_one = %d AND user_two = %d ) OR ( user_one = %d AND user_two = %d )",
                $user_one,
                $user_two,
                $user_two,
                $user_one
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        if ( $existing ) {
            return new WP_Error( 'connection_exists', __( 'You are already connected with this user.', 'relationship-quizzr' ) );
        }

        $hash = wp_generate_password( 20, false, false );

        $wpdb->insert(
            $table,
            array(
                'connection_hash'   => $hash,
                'user_one'          => $user_one,
                'user_two'          => $user_two,
                'email'             => '',
                'relationship_type' => $type,
                'status'            => 'accepted',
                'created_at'        => current_time( 'mysql', true ),
                'updated_at'        => current_time( 'mysql', true ),
            ),
            array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
        );

        $connection_id = (int) $wpdb->insert_id;

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'rq_connection',
                'post_status' => 'publish',
                'post_title'  => sprintf(
                    __( 'Connection: %1$s & %2$s', 'relationship-quizzr' ),
                    $user_one_data->display_name,
                    $user_two_data->display_name
                ),
                'post_author' => $user_one,
                'meta_input'  => array(
                    '_rq_connection_hash' => $hash,
                    '_rq_connection_type' => $type,
                    '_rq_connection_users'=> array( (int) $user_one, (int) $user_two ),
                ),
            )
        );

        do_action(
            'rq_connection_accepted',
            array(
                'hash'       => $hash,
                'post_id'    => $post_id,
                'user_one'   => (int) $user_one,
                'user_two'   => (int) $user_two,
                'connection' => (object) array(
                    'id'                => $connection_id,
                    'connection_hash'   => $hash,
                    'user_one'          => $user_one,
                    'user_two'          => $user_two,
                    'relationship_type' => $type,
                ),
            )
        );

        return array(
            'hash'          => $hash,
            'status'        => 'accepted',
            'connection_id' => $connection_id,
            'from'          => (int) $user_one,
            'to'            => (int) $user_two,
            'type'          => $type,
            'email'         => '',
        );
    }

    /**
     * Accept a connection invitation.
     *
     * @param string $hash    Connection hash.
     * @param int    $user_id Accepting user ID.
     *
     * @return bool|WP_Error
     */
    public function accept_invitation( $hash, $user_id ) {
        global $wpdb;

        $table  = $wpdb->prefix . 'rq_connections';
        $invite = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE connection_hash = %s", $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        if ( ! $invite ) {
            return new WP_Error( 'invalid_hash', __( 'Invitation not found.', 'relationship-quizzr' ) );
        }

        if ( (int) $invite->user_two === $user_id ) {
            return true;
        }

        $wpdb->update(
            $table,
            array(
                'user_two'   => $user_id,
                'status'     => 'accepted',
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( 'id' => $invite->id ),
            array( '%d', '%s', '%s' ),
            array( '%d' )
        );

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'rq_connection',
                'post_status' => 'publish',
                'post_title'  => sprintf( __( 'Connection: %1$s & %2$s', 'relationship-quizzr' ), get_userdata( $invite->user_one )->display_name, get_userdata( $user_id )->display_name ),
                'post_author' => $invite->user_one,
                'meta_input'  => array(
                    '_rq_connection_hash' => $hash,
                    '_rq_connection_type' => $invite->relationship_type,
                    '_rq_connection_users'=> array( (int) $invite->user_one, (int) $user_id ),
                ),
            )
        );

        do_action(
            'rq_connection_accepted',
            array(
                'hash'       => $hash,
                'post_id'    => $post_id,
                'user_one'   => (int) $invite->user_one,
                'user_two'   => (int) $user_id,
                'connection' => $invite,
            )
        );

        return true;
    }

    /**
     * Automatically accept connection for new users if invitation exists.
     *
     * @param int $user_id User ID.
     */
    public function maybe_auto_accept_pending_invites( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'rq_connections';

        $invites = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s AND status = 'pending'", $user->user_email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        foreach ( $invites as $invite ) {
            $this->accept_invitation( $invite->connection_hash, $user_id );
        }
    }

    /**
     * Retrieve connections for a user.
     *
     * @param int $user_id User ID.
     *
     * @return array
     */
    public function get_user_connections( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_connections';

        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_one = %d OR user_two = %d", $user_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return array_map( array( $this, 'prepare_connection' ), $results );
    }

    /**
     * Prepare connection output.
     *
     * @param array $connection Connection data.
     *
     * @return array
     */
    protected function prepare_connection( $connection ) {
        $connection['user_one'] = (int) $connection['user_one'];
        $connection['user_two'] = $connection['user_two'] ? (int) $connection['user_two'] : null;

        return $connection;
    }
}
