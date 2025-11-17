<?php
/**
 * Custom post types and taxonomies.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers Relationship Quizzr post types and taxonomies.
 */
class RQ_Post_Types {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register' ) );
        add_action( 'init', array( $this, 'register_taxonomies' ) );
        add_action( 'init', array( $this, 'register_default_terms' ), 11 );
    }

    /**
     * Register post types.
     */
    public function register() {
        $this->register_quiz_post_type();
        $this->register_question_post_type();
        $this->register_result_post_type();
        $this->register_connection_post_type();
        $this->register_referral_post_type();
    }

    /**
     * Register quiz post type.
     */
    protected function register_quiz_post_type() {
        register_post_type(
            'rq_quiz',
            array(
                'labels'       => array(
                    'name'          => __( 'Quizzes', 'relationship-quizzr' ),
                    'singular_name' => __( 'Quiz', 'relationship-quizzr' ),
                ),
                'public'       => true,
                'has_archive'  => true,
                'show_in_rest' => true,
                'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
                'menu_icon'    => 'dashicons-heart',
            )
        );
    }

    /**
     * Register question post type.
     */
    protected function register_question_post_type() {
        register_post_type(
            'rq_question',
            array(
                'labels'       => array(
                    'name'          => __( 'Questions', 'relationship-quizzr' ),
                    'singular_name' => __( 'Question', 'relationship-quizzr' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => true,
                'supports'     => array( 'title', 'editor', 'author', 'custom-fields' ),
                'menu_icon'    => 'dashicons-edit',
            )
        );
    }

    /**
     * Register result post type.
     */
    protected function register_result_post_type() {
        register_post_type(
            'rq_result',
            array(
                'labels'       => array(
                    'name'          => __( 'Results', 'relationship-quizzr' ),
                    'singular_name' => __( 'Result', 'relationship-quizzr' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => false,
                'supports'     => array( 'title', 'custom-fields', 'author' ),
                'menu_icon'    => 'dashicons-chart-pie',
            )
        );
    }

    /**
     * Register connection post type.
     */
    protected function register_connection_post_type() {
        register_post_type(
            'rq_connection',
            array(
                'labels'       => array(
                    'name'          => __( 'Connections', 'relationship-quizzr' ),
                    'singular_name' => __( 'Connection', 'relationship-quizzr' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => true,
                'supports'     => array( 'title', 'custom-fields', 'author' ),
                'menu_icon'    => 'dashicons-admin-users',
            )
        );
    }

    /**
     * Register referral post type.
     */
    protected function register_referral_post_type() {
        register_post_type(
            'rq_referral',
            array(
                'labels'       => array(
                    'name'          => __( 'Referrals', 'relationship-quizzr' ),
                    'singular_name' => __( 'Referral', 'relationship-quizzr' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'show_in_rest' => false,
                'supports'     => array( 'title', 'custom-fields', 'author' ),
                'menu_icon'    => 'dashicons-groups',
            )
        );
    }

    /**
     * Register taxonomies.
     */
    public function register_taxonomies() {
        register_taxonomy(
            'rq_category',
            array( 'rq_quiz', 'rq_question' ),
            array(
                'labels'       => array(
                    'name'          => __( 'Quiz Categories', 'relationship-quizzr' ),
                    'singular_name' => __( 'Quiz Category', 'relationship-quizzr' ),
                ),
                'public'       => true,
                'show_in_rest' => true,
                'hierarchical' => true,
            )
        );
    }

    /**
     * Ensure default categories exist.
     */
    public function register_default_terms() {
        $defaults = array( 'general', 'couple', 'adult' );

        foreach ( $defaults as $slug ) {
            if ( ! term_exists( $slug, 'rq_category' ) ) {
                wp_insert_term(
                    ucfirst( $slug ),
                    'rq_category',
                    array(
                        'slug'        => $slug,
                        'description' => sprintf( __( 'Default %s category.', 'relationship-quizzr' ), $slug ),
                    )
                );
            }
        }
    }
}
