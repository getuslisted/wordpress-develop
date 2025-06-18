<?php
// wp-content/plugins/google-sheet-csv-updater/includes/class-gscu-frontend.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class GSCU_Frontend
 * Handles shortcode registration and rendering.
 */
class GSCU_Frontend {

    private $styles_enqueued = false;

    /**
     * Constructor. Hooks into WordPress.
     */
    public function __construct() {
        add_shortcode( 'google_sheet_data', array( $this, 'render_shortcode' ) );
        // Enqueue styles in the footer if the shortcode is used.
        // Alternative: enqueue in render_shortcode, but this ensures it's in <head> or footer.
        add_action( 'wp_footer', array( $this, 'maybe_enqueue_styles' ) );
    }

    /**
     * Registers and enqueues the plugin's stylesheet.
     */
    public function enqueue_styles() {
        if ($this->styles_enqueued) {
            return;
        }
        wp_register_style(
            'gscu-styles',
            plugins_url('assets/css/gscu-styles.css', GSCU_PLUGIN_FILE),
            array(),
            GSCU_VERSION
        );
        wp_enqueue_style( 'gscu-styles' );
        $this->styles_enqueued = true;
    }

    /**
     * Checks if styles should be enqueued (e.g. if shortcode was used).
     * This is a simple way to handle it; more complex scenarios might involve
     * checking a flag set by the shortcode. For now, if shortcode runs, this will be true.
     */
    public function maybe_enqueue_styles() {
        // The check for whether the shortcode is actually on the page
        // is implicitly handled by only calling enqueue_styles() from render_shortcode().
        // This wp_footer hook is a fallback or can be used if we set a global flag.
        // For simplicity, render_shortcode will directly call $this->enqueue_styles().
        // So, this specific hook 'wp_footer' might not be strictly necessary if enqueueing from shortcode.
        // However, if we wanted to ensure it's *only* in the footer, this is one way.
        // Let's stick to calling $this->enqueue_styles() from render_shortcode for directness.
    }


    /**
     * Renders the [google_sheet_data] shortcode.
     * Displays synchronized WordPress content based on plugin settings and shortcode attributes.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the shortcode.
     */
    public function render_shortcode( $atts ) {
        // Enqueue styles when the shortcode is rendered.
        $this->enqueue_styles();

        $gscu_content_type = get_option( 'gscu_content_type', 'post' );
        if ( empty( $gscu_content_type ) ) {
            return esc_html__( 'Content type is not configured in plugin settings.', 'google-sheet-csv-updater' );
        }

        $default_posts_per_page = get_option( 'gscu_default_posts_per_page', 10 );
        $default_columns = 'title,' . (defined('GSCU_UNIQUE_ID_META_KEY') ? GSCU_UNIQUE_ID_META_KEY : '_gscu_unique_id');
        $default_table_classes_option = get_option('gscu_default_table_classes', '');

        $atts = shortcode_atts( array(
            'posts_per_page' => $default_posts_per_page,
            'columns'        => $default_columns,
            'table_class'    => '', // New attribute for custom classes per shortcode instance
        ), $atts, 'google_sheet_data' );

        $posts_per_page = absint( $atts['posts_per_page'] );
        $display_columns_str = sanitize_text_field( $atts['columns'] );
        $display_columns = array_map( 'trim', explode( ',', $display_columns_str ) );
        $display_columns = array_filter( $display_columns );

        if ( empty( $display_columns ) ) {
            return esc_html__( 'No columns specified for display.', 'google-sheet-csv-updater' );
        }

        // Compile CSS classes for the table
        $table_classes = array('gscu-table'); // Base class
        if (!empty($default_table_classes_option)) {
            $table_classes = array_merge($table_classes, explode(' ', $default_table_classes_option));
        }
        if (!empty($atts['table_class'])) {
            $table_classes = array_merge($table_classes, explode(' ', sanitize_text_field($atts['table_class'])));
        }

        $predefined_style = get_option('gscu_predefined_style', 'basic');
        if ($predefined_style !== 'none' && $predefined_style !== 'basic') { // 'basic' uses the .gscu-table defaults
            $table_classes[] = 'gscu-table-' . sanitize_html_class($predefined_style);
        }

        $table_classes = array_unique(array_map('sanitize_html_class', $table_classes));
        $compiled_css_classes = implode(' ', array_filter($table_classes));


        $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
        if (is_front_page() && get_query_var('page')) {
            $paged = get_query_var('page');
        }

        $query_args = array(
            'post_type'      => $gscu_content_type,
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => (defined('GSCU_UNIQUE_ID_META_KEY') ? GSCU_UNIQUE_ID_META_KEY : '_gscu_unique_id'),
                    'compare' => 'EXISTS',
                ),
            ),
        );

        $query = new WP_Query( $query_args );
        $output = '';

        if ( $query->have_posts() ) {
            $output .= '<table class="' . esc_attr($compiled_css_classes) . '">';
            $output .= '<thead><tr>';
            foreach ( $display_columns as $column_key ) {
                $header_label = ucwords( str_replace( array( '_', '-' ), ' ', $column_key ) );
                if ($column_key === 'title') {
                    $header_label = __('Title', 'google-sheet-csv-updater');
                } elseif ($column_key === (defined('GSCU_UNIQUE_ID_META_KEY') ? GSCU_UNIQUE_ID_META_KEY : '_gscu_unique_id')) {
                    $header_label = __('Unique ID', 'google-sheet-csv-updater');
                }
                $output .= '<th>' . esc_html( $header_label ) . '</th>';
            }
            $output .= '</tr></thead>';
            $output .= '<tbody>';
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $output .= '<tr>';
                foreach ( $display_columns as $column_key ) {
                    $cell_data = '';
                    if ( 'title' === $column_key ) {
                        $cell_data = get_the_title( $post_id );
                    } elseif ( 'post_content' === $column_key || 'content' === $column_key ) {
                        $cell_data = get_the_content(null, false, $post_id);
                        $cell_data = wp_strip_all_tags($cell_data);
                        $cell_data = wp_trim_words($cell_data, 20, '...');
                    } elseif ( 'post_excerpt' === $column_key || 'excerpt' === $column_key) {
                        $cell_data = get_the_excerpt($post_id);
                         $cell_data = wp_strip_all_tags($cell_data);
                    } elseif ( 'post_date' === $column_key || 'date' === $column_key) {
                        $cell_data = get_the_date('', $post_id);
                    } else {
                        $cell_data = get_post_meta( $post_id, $column_key, true );
                    }
                    $output .= '<td>' . esc_html( $cell_data ) . '</td>';
                }
                $output .= '</tr>';
            }
            $output .= '</tbody>';
            $output .= '</table>';

            if ($query->max_num_pages > 1) {
                $output .= '<div class="gscu-pagination">'; // Added class for styling pagination
                $big = 999999999;
                $output .= paginate_links( array(
                    'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                    'format'  => '?paged=%#%',
                    'current' => max( 1, $paged ),
                    'total'   => $query->max_num_pages,
                    'prev_text' => __('&laquo; Previous'),
                    'next_text' => __('Next &raquo;'),
                ) );
                $output .= '</div>';
            }
        } else {
            $output = '<p>' . esc_html__( 'No synced items to display.', 'google-sheet-csv-updater' ) . '</p>';
        }
        wp_reset_postdata();
        return $output;
    }
}
