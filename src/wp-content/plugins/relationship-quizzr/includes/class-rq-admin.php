<?php
/**
 * Admin settings and moderation UI.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Adds settings pages to the WordPress dashboard.
 */
class RQ_Admin {

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

        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Register admin menu.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Relationship Quizzr', 'relationship-quizzr' ),
            __( 'Relationship Quizzr', 'relationship-quizzr' ),
            'manage_options',
            'relationship-quizzr',
            array( $this, 'render_settings_page' ),
            'dashicons-heart',
            58
        );

        add_submenu_page(
            'relationship-quizzr',
            __( 'Quiz Moderation', 'relationship-quizzr' ),
            __( 'Quiz Moderation', 'relationship-quizzr' ),
            'edit_posts',
            'relationship-quizzr-moderation',
            array( $this, 'render_moderation_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'relationship_quizzr', 'relationship_quizzr_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'rq_paypal_section',
            __( 'PayPal Configuration', 'relationship-quizzr' ),
            '__return_false',
            'relationship-quizzr'
        );

        add_settings_field(
            'paypal_client_id',
            __( 'Client ID', 'relationship-quizzr' ),
            array( $this, 'render_text_field' ),
            'relationship-quizzr',
            'rq_paypal_section',
            array(
                'label_for' => 'paypal_client_id',
                'type'      => 'text',
            )
        );

        add_settings_field(
            'paypal_client_secret',
            __( 'Client Secret', 'relationship-quizzr' ),
            array( $this, 'render_text_field' ),
            'relationship-quizzr',
            'rq_paypal_section',
            array(
                'label_for' => 'paypal_client_secret',
                'type'      => 'password',
            )
        );

        add_settings_section(
            'rq_pricing_section',
            __( 'Subscription Pricing', 'relationship-quizzr' ),
            '__return_false',
            'relationship-quizzr'
        );

        add_settings_field(
            'individual_price',
            __( 'Individual Monthly Price', 'relationship-quizzr' ),
            array( $this, 'render_number_field' ),
            'relationship-quizzr',
            'rq_pricing_section',
            array( 'label_for' => 'individual_price' )
        );

        add_settings_field(
            'couple_price',
            __( 'Couple Monthly Price', 'relationship-quizzr' ),
            array( $this, 'render_number_field' ),
            'relationship-quizzr',
            'rq_pricing_section',
            array( 'label_for' => 'couple_price' )
        );

        add_settings_section(
            'rq_moderation_section',
            __( 'Quiz Moderation', 'relationship-quizzr' ),
            '__return_false',
            'relationship-quizzr'
        );

        add_settings_field(
            'moderation_required',
            __( 'Require admin approval for community quizzes', 'relationship-quizzr' ),
            array( $this, 'render_checkbox_field' ),
            'relationship-quizzr',
            'rq_moderation_section',
            array(
                'label_for'    => 'moderation_required',
                'toggle_label' => __( 'Enable moderation', 'relationship-quizzr' ),
            )
        );

        add_settings_field(
            'adult_category_lock',
            __( 'Restrict adult quizzes to verified subscribers', 'relationship-quizzr' ),
            array( $this, 'render_checkbox_field' ),
            'relationship-quizzr',
            'rq_moderation_section',
            array(
                'label_for'    => 'adult_category_lock',
                'toggle_label' => __( 'Enforce restriction', 'relationship-quizzr' ),
                'description'  => __( 'When enabled, only age-verified subscribers can browse or play adult-category quizzes.', 'relationship-quizzr' ),
            )
        );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Raw input.
     *
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        $sanitized['paypal_client_id']     = sanitize_text_field( $input['paypal_client_id'] ?? '' );
        $sanitized['paypal_client_secret'] = sanitize_text_field( $input['paypal_client_secret'] ?? '' );
        $sanitized['individual_price']     = isset( $input['individual_price'] ) ? (float) $input['individual_price'] : 0;
        $sanitized['couple_price']         = isset( $input['couple_price'] ) ? (float) $input['couple_price'] : 0;
        $sanitized['moderation_required']  = ! empty( $input['moderation_required'] );
        $sanitized['adult_category_lock']  = ! empty( $input['adult_category_lock'] );

        return $sanitized;
    }

    /**
     * Render text field.
     *
     * @param array $args Field args.
     */
    public function render_text_field( $args ) {
        $options = get_option( 'relationship_quizzr_settings', array() );
        $value   = esc_attr( $options[ $args['label_for'] ] ?? '' );

        printf( '<input type="%1$s" id="%2$s" name="relationship_quizzr_settings[%2$s]" value="%3$s" class="regular-text" />', esc_attr( $args['type'] ), esc_attr( $args['label_for'] ), $value );
    }

    /**
     * Render number field.
     *
     * @param array $args Field args.
     */
    public function render_number_field( $args ) {
        $options = get_option( 'relationship_quizzr_settings', array() );
        $value   = esc_attr( $options[ $args['label_for'] ] ?? '' );

        printf( '<input type="number" id="%1$s" step="0.01" min="0" name="relationship_quizzr_settings[%1$s]" value="%2$s" />', esc_attr( $args['label_for'] ), $value );
    }

    /**
     * Render checkbox field.
     *
     * @param array $args Field args.
     */
    public function render_checkbox_field( $args ) {
        $options = get_option( 'relationship_quizzr_settings', array() );
        $value   = ! empty( $options[ $args['label_for'] ] );

        $toggle_label = isset( $args['toggle_label'] ) ? $args['toggle_label'] : __( 'Enable', 'relationship-quizzr' );

        printf(
            '<label><input type="checkbox" id="%1$s" name="relationship_quizzr_settings[%1$s]" value="1" %2$s /> %3$s</label>',
            esc_attr( $args['label_for'] ),
            checked( $value, true, false ),
            esc_html( $toggle_label )
        );

        if ( ! empty( $args['description'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
        }
    }

    /**
     * Render settings page markup.
     */
    public function render_settings_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Relationship Quizzr Settings', 'relationship-quizzr' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'relationship_quizzr' );
        do_settings_sections( 'relationship-quizzr' );
        submit_button();
        echo '</form></div>';
    }

    /**
     * Render moderation queue.
     */
    public function render_moderation_page() {
        $query = new WP_Query(
            array(
                'post_type'      => 'rq_quiz',
                'post_status'    => 'pending',
                'posts_per_page' => 20,
            )
        );

        echo '<div class="wrap"><h1>' . esc_html__( 'Quiz Moderation', 'relationship-quizzr' ) . '</h1>';
        if ( $query->have_posts() ) {
            echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Quiz', 'relationship-quizzr' ) . '</th><th>' . esc_html__( 'Author', 'relationship-quizzr' ) . '</th><th>' . esc_html__( 'Actions', 'relationship-quizzr' ) . '</th></tr></thead><tbody>';
            while ( $query->have_posts() ) {
                $query->the_post();
                echo '<tr><td>' . esc_html( get_the_title() ) . '</td><td>' . esc_html( get_the_author() ) . '</td><td>';
                echo '<a class="button button-primary" href="' . esc_url( get_edit_post_link() ) . '">' . esc_html__( 'Review', 'relationship-quizzr' ) . '</a>';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            echo '<p>' . esc_html__( 'No quizzes awaiting approval.', 'relationship-quizzr' ) . '</p>';
        }
        echo '</div>';
    }
}
