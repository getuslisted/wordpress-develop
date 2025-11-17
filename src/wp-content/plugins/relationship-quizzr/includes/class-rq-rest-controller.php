<?php
/**
 * REST API endpoints for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers custom REST routes for the plugin.
 */
class RQ_REST_Controller {

    /**
     * Service container.
     *
     * @var array
     */
    protected $services = array();

    /**
     * Constructor.
     *
     * @param array $services Service instances.
     */
    public function __construct( $services ) {
        $this->services = $services;
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route(
            'rq/v1',
            '/connections',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_connection' ),
                    'permission_callback' => array( $this, 'ensure_logged_in' ),
                    'args'                => array(
                        'email'         => array(
                            'required' => false,
                            'type'     => 'string',
                            'sanitize_callback' => 'sanitize_email',
                        ),
                        'referral_code' => array(
                            'required' => false,
                            'type'     => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'type'          => array(
                            'required' => true,
                            'type'     => 'string',
                            'enum'     => $this->get_allowed_connection_types(),
                            'sanitize_callback' => 'sanitize_key',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'rq/v1',
            '/connections/accept',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'accept_connection' ),
                    'permission_callback' => array( $this, 'ensure_logged_in' ),
                    'args'                => array(
                        'hash' => array(
                            'required' => true,
                            'type'     => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'rq/v1',
            '/quizzes/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_quiz' ),
                    'permission_callback' => array( $this, 'ensure_logged_in' ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'submit_quiz' ),
                    'permission_callback' => array( $this, 'ensure_logged_in' ),
                    'args'                => array(
                        'answers'       => array(
                            'required'          => true,
                            'type'              => 'object',
                            'sanitize_callback' => array( $this, 'sanitize_answers_payload' ),
                            'validate_callback' => array( $this, 'validate_answers_payload' ),
                        ),
                        'connection_id' => array(
                            'required'          => false,
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'rq/v1',
            '/leaderboard',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_leaderboard' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'range' => array(
                            'required'          => false,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_key',
                            'validate_callback' => array( $this, 'validate_leaderboard_range' ),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'rq/v1',
            '/referrals',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'register_referral' ),
                    'permission_callback' => array( $this, 'ensure_logged_in' ),
                    'args'                => array(
                        'code' => array(
                            'required'          => true,
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'rq/v1',
            '/subscriptions',
            array(
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( $this, 'create_subscription' ),
                    'permission_callback' => array( $this, 'ensure_logged_in' ),
                    'args'                => array(
                        'tier'    => array(
                            'required'          => true,
                            'type'              => 'string',
                            'enum'              => array( 'individual', 'couple' ),
                            'sanitize_callback' => 'sanitize_key',
                        ),
                        'payment' => array(
                            'required' => false,
                            'type'     => 'object',
                            'sanitize_callback' => array( $this, 'sanitize_payment_payload' ),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'rq/v1',
            '/profile/(?P<user_id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_profile' ),
                    'permission_callback' => array( $this, 'can_view_profile' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'update_profile' ),
                    'permission_callback' => array( $this, 'can_update_profile' ),
                    'args'                => array(
                        'fields' => array(
                            'required'          => true,
                            'type'              => 'object',
                            'validate_callback' => array( $this, 'validate_profile_fields' ),
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Ensure user logged in.
     *
     * @return bool
     */
    public function ensure_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Verify viewer permission.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return bool
     */
    public function can_view_profile( $request ) {
        $requested_id = (int) $request['user_id'];

        if ( is_user_logged_in() && get_current_user_id() === $requested_id ) {
            return true;
        }

        return user_can( get_current_user_id(), 'list_users' );
    }

    /**
     * Verify update permission.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return bool
     */
    public function can_update_profile( $request ) {
        return is_user_logged_in() && ( get_current_user_id() === (int) $request['user_id'] );
    }

    /**
     * Create connection invitation.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_connection( WP_REST_Request $request ) {
        $connections = $this->services['connections'];
        $email       = $request->get_param( 'email' );
        $ref_code    = $request->get_param( 'referral_code' );

        if ( empty( $email ) && empty( $ref_code ) ) {
            return new WP_Error( 'missing_invite_target', __( 'Provide an email address or referral code.', 'relationship-quizzr' ) );
        }

        $invite = $connections->create_invitation(
            get_current_user_id(),
            $email,
            $request->get_param( 'type' ),
            $ref_code
        );

        if ( is_wp_error( $invite ) ) {
            return $invite;
        }

        return new WP_REST_Response( $invite, 201 );
    }

    /**
     * Accept connection invitation.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function accept_connection( WP_REST_Request $request ) {
        $connections = $this->services['connections'];
        $result      = $connections->accept_invitation( $request->get_param( 'hash' ), get_current_user_id() );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * Retrieve quiz data.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response
     */
    public function get_quiz( WP_REST_Request $request ) {
        $quiz_id   = (int) $request['id'];
        $quiz      = get_post( $quiz_id );
        $quiz_data = null;
        $questions = array();

        if ( $quiz && 'rq_quiz' === $quiz->post_type ) {
            $subscriptions   = isset( $this->services['subscriptions'] ) ? $this->services['subscriptions'] : null;
            $quiz_categories = wp_get_post_terms( $quiz_id, 'rq_category', array( 'fields' => 'slugs' ) );
            if ( is_wp_error( $quiz_categories ) ) {
                $quiz_categories = array();
            }
            $requires_adult  = in_array( 'adult', $quiz_categories, true );
            $can_view_adult  = $subscriptions && is_user_logged_in() ? $subscriptions->can_access_adult_category( get_current_user_id() ) : false;

            if ( $requires_adult && ! $can_view_adult ) {
                return new WP_Error( 'adult_restricted', __( 'This quiz is restricted to verified adult subscribers.', 'relationship-quizzr' ), array( 'status' => 403 ) );
            }

            $engine         = $this->services['quiz_engine'];
            $question_posts = $engine->get_quiz_questions( $quiz_id );
            foreach ( $question_posts as $question ) {
                $questions[] = array(
                    'id'       => $question->ID,
                    'title'    => $question->post_title,
                    'content'  => apply_filters( 'the_content', $question->post_content ),
                    'type'     => get_post_meta( $question->ID, '_rq_question_type', true ),
                    'settings' => get_post_meta( $question->ID, '_rq_question_settings', true ),
                    'category' => wp_get_post_terms( $question->ID, 'rq_category', array( 'fields' => 'names' ) ),
                );
            }

            $quiz_data = array(
                'id'       => $quiz->ID,
                'title'    => $quiz->post_title,
                'content'  => apply_filters( 'the_content', $quiz->post_content ),
                'excerpt'  => get_the_excerpt( $quiz ),
                'permalink'=> get_permalink( $quiz ),
                'categories' => $quiz_categories,
            );
        }

        return new WP_REST_Response(
            array(
                'quiz'      => $quiz_data,
                'questions' => $questions,
            )
        );
    }

    /**
     * Submit quiz answers.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function submit_quiz( WP_REST_Request $request ) {
        $quiz_id    = (int) $request['id'];
        $connection = $request->get_param( 'connection_id' ) ? (int) $request->get_param( 'connection_id' ) : null;
        $answers    = $request->get_param( 'answers' );

        if ( empty( $answers ) || ! is_array( $answers ) ) {
            return new WP_Error( 'invalid_answers', __( 'Answers payload missing.', 'relationship-quizzr' ) );
        }

        $subscriptions   = isset( $this->services['subscriptions'] ) ? $this->services['subscriptions'] : null;
        $quiz_categories = wp_get_post_terms( $quiz_id, 'rq_category', array( 'fields' => 'slugs' ) );
        if ( is_wp_error( $quiz_categories ) ) {
            $quiz_categories = array();
        }
        $requires_adult  = in_array( 'adult', $quiz_categories, true );
        $can_view_adult  = $subscriptions && is_user_logged_in() ? $subscriptions->can_access_adult_category( get_current_user_id() ) : false;

        if ( $requires_adult && ! $can_view_adult ) {
            return new WP_Error( 'adult_restricted', __( 'You are not permitted to submit answers for adult quizzes.', 'relationship-quizzr' ), array( 'status' => 403 ) );
        }

        $engine  = $this->services['quiz_engine'];
        $results = $engine->submit_answers( $quiz_id, get_current_user_id(), $answers, $connection );

        do_action( 'rq_quiz_completed_via_rest', $results );

        return new WP_REST_Response( $results, 200 );
    }

    /**
     * Get leaderboard data.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response
     */
    public function get_leaderboard( WP_REST_Request $request ) {
        $range       = $request->get_param( 'range' ) ? $request->get_param( 'range' ) : 'monthly';
        $leaderboard = $this->services['leaderboard']->get_leaderboard( $range );

        return new WP_REST_Response( $leaderboard );
    }

    /**
     * Register referral via REST.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function register_referral( WP_REST_Request $request ) {
        $code = $request->get_param( 'code' );
        if ( empty( $code ) ) {
            return new WP_Error( 'invalid_code', __( 'Referral code is required.', 'relationship-quizzr' ) );
        }

        $referrer = get_users(
            array(
                'meta_key'   => '_rq_referral_code',
                'meta_value' => $code,
                'number'     => 1,
                'fields'     => 'ID',
            )
        );

        if ( empty( $referrer ) ) {
            return new WP_Error( 'code_not_found', __( 'Referral code not found.', 'relationship-quizzr' ) );
        }

        $referrals = $this->services['referrals'];
        $referrals->record_referral( (int) $referrer[0], get_current_user_id(), 'pending' );

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Create subscription from REST.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_subscription( WP_REST_Request $request ) {
        $tier    = $request->get_param( 'tier' );
        $payload = $request->get_param( 'payment' );

        if ( ! in_array( $tier, array( 'individual', 'couple' ), true ) ) {
            return new WP_Error( 'invalid_tier', __( 'Select a valid subscription tier.', 'relationship-quizzr' ) );
        }

        if ( ! is_array( $payload ) ) {
            $payload = array();
        }

        $subscriptions = $this->services['subscriptions'];
        $created       = $subscriptions->create_subscription( get_current_user_id(), $tier, $payload );

        if ( ! $created ) {
            return new WP_Error( 'subscription_failed', __( 'Unable to start subscription.', 'relationship-quizzr' ) );
        }

        return new WP_REST_Response( array( 'success' => true ) );
    }

    /**
     * Retrieve profile data.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response
     */
    public function get_profile( WP_REST_Request $request ) {
        $profile = $this->services['profile']->get_public_profile( (int) $request['user_id'] );

        return new WP_REST_Response( $profile );
    }

    /**
     * Update profile data.
     *
     * @param WP_REST_Request $request Request.
     *
     * @return WP_REST_Response
     */
    public function update_profile( WP_REST_Request $request ) {
        $fields = $request->get_param( 'fields' );
        if ( empty( $fields ) || ! is_array( $fields ) ) {
            return new WP_REST_Response( array( 'updated' => false ) );
        }

        $updated = $this->services['profile']->update_profile_fields( get_current_user_id(), $fields );

        return new WP_REST_Response( array( 'updated' => $updated ) );
    }

    /**
     * Retrieve the connection types available via the connections service.
     *
     * @return array
     */
    protected function get_allowed_connection_types() {
        if ( isset( $this->services['connections'] ) && method_exists( $this->services['connections'], 'get_allowed_types' ) ) {
            return $this->services['connections']->get_allowed_types();
        }

        return array( 'spouse', 'partner', 'friend' );
    }

    /**
     * Sanitize nested quiz answers prior to handing them off to the engine.
     *
     * @param mixed           $value   Raw value.
     * @param WP_REST_Request $request Request.
     * @param string          $param   Parameter name.
     *
     * @return array
     */
    public function sanitize_answers_payload( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $value as $question_id => $answer ) {
            $question_key = absint( $question_id );
            if ( $question_key <= 0 ) {
                continue;
            }

            $sanitized[ $question_key ] = $this->sanitize_mixed_value( $answer );
        }

        return $sanitized;
    }

    /**
     * Validate quiz answer payloads.
     *
     * @param mixed           $value   Raw value.
     * @param WP_REST_Request $request Request.
     * @param string          $param   Parameter name.
     *
     * @return true|WP_Error
     */
    public function validate_answers_payload( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if ( ! is_array( $value ) ) {
            return new WP_Error( 'invalid_answers', __( 'Answers must be provided as an object keyed by question ID.', 'relationship-quizzr' ) );
        }

        foreach ( $value as $question_id => $answer ) {
            if ( ! is_numeric( $question_id ) || absint( $question_id ) <= 0 ) {
                return new WP_Error( 'invalid_answers', __( 'Each answer must reference a valid question.', 'relationship-quizzr' ) );
            }
        }

        return true;
    }

    /**
     * Validate leaderboard range query arguments.
     *
     * @param mixed $value Raw range value.
     *
     * @return true|WP_Error
     */
    public function validate_leaderboard_range( $value ) {
        if ( empty( $value ) ) {
            return true;
        }

        $allowed = array( 'daily', 'weekly', 'monthly', 'all-time' );
        if ( in_array( sanitize_key( $value ), $allowed, true ) ) {
            return true;
        }

        return new WP_Error( 'invalid_range', __( 'Choose a valid leaderboard range.', 'relationship-quizzr' ) );
    }

    /**
     * Sanitize nested payment payloads coming from PayPal.
     *
     * @param mixed           $value   Raw value.
     * @param WP_REST_Request $request Request.
     * @param string          $param   Parameter name.
     *
     * @return array
     */
    public function sanitize_payment_payload( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if ( ! is_array( $value ) ) {
            return array();
        }

        return $this->sanitize_mixed_value( $value );
    }

    /**
     * Validate the fields payload before updating a profile.
     *
     * @param mixed           $value   Raw value.
     * @param WP_REST_Request $request Request.
     * @param string          $param   Parameter name.
     *
     * @return true|WP_Error
     */
    public function validate_profile_fields( $value, $request, $param ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        if ( ! is_array( $value ) ) {
            return new WP_Error( 'invalid_fields', __( 'Profile updates must be sent as an object of editable fields.', 'relationship-quizzr' ) );
        }

        $allowed = $this->get_allowed_profile_fields();
        foreach ( $value as $field => $field_value ) {
            if ( ! in_array( $field, $allowed, true ) ) {
                return new WP_Error( 'invalid_fields', __( 'One or more profile fields cannot be edited.', 'relationship-quizzr' ) );
            }

            if ( 'age_verified' === $field && ! is_bool( $field_value ) && ! in_array( $field_value, array( 0, 1, '0', '1' ), true ) ) {
                return new WP_Error( 'invalid_fields', __( 'Age verification must be passed as a boolean flag.', 'relationship-quizzr' ) );
            }
        }

        return true;
    }

    /**
     * Retrieve the editable profile field keys.
     *
     * @return array
     */
    protected function get_allowed_profile_fields() {
        if ( isset( $this->services['profile'] ) && method_exists( $this->services['profile'], 'get_editable_fields' ) ) {
            return $this->services['profile']->get_editable_fields();
        }

        return array();
    }

    /**
     * Recursively sanitize scalar/array payloads without destroying structure.
     *
     * @param mixed $value Arbitrary value.
     *
     * @return mixed
     */
    protected function sanitize_mixed_value( $value ) {
        if ( is_array( $value ) ) {
            $clean = array();
            foreach ( $value as $key => $item ) {
                $clean_key         = is_string( $key ) ? sanitize_key( $key ) : $key;
                $clean[ $clean_key ] = $this->sanitize_mixed_value( $item );
            }

            return $clean;
        }

        if ( is_bool( $value ) ) {
            return (bool) $value;
        }

        if ( is_numeric( $value ) ) {
            return $value + 0;
        }

        if ( is_scalar( $value ) ) {
            return sanitize_text_field( (string) $value );
        }

        return '';
    }
}
