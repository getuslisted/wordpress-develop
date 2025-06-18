<?php
// wp-content/plugins/google-sheet-csv-updater/includes/class-gscu-admin.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class GSCU_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_post_gscu_manual_sync', array( $this, 'handle_manual_sync' ) );
        add_action( 'admin_post_gscu_clear_logs', array( $this, 'handle_clear_logs' ) );

        $plugin_basename = plugin_basename( GSCU_PLUGIN_FILE );
        add_filter( "plugin_action_links_{$plugin_basename}", array( $this, 'add_settings_link' ) );
    }

    public function admin_menu() {
        add_options_page(
            __( 'Google Sheet CSV Updater Settings', 'google-sheet-csv-updater' ),
            __( 'Google Sheet Updater', 'google-sheet-csv-updater' ),
            'manage_options',
            'gscu-settings',
            array( $this, 'render_settings_page' )
        );
    }

    // --- Sanitization Callbacks ---
    public function sanitize_validate_sheet_url( $input ) {
        $old_url = get_option( 'gscu_google_sheet_url' );
        $output = esc_url_raw( trim( $input ) );
        if ( empty( $output ) && !empty( $input ) ) {
             add_settings_error( 'gscu_google_sheet_url', 'gscu_invalid_url_format', __( 'The entered URL format was invalid.', 'google-sheet-csv-updater' ), 'error' );
            return $old_url;
        }
        if ( ! empty( $output ) ) {
            $parsed_url = wp_parse_url( $output );
            if ( ! $parsed_url || ! isset( $parsed_url['host'] ) || ( strpos( $parsed_url['host'], 'docs.google.com' ) === false && strpos( $parsed_url['host'], 'spreadsheets.google.com' ) === false ) ) {
                add_settings_error( 'gscu_google_sheet_url', 'gscu_invalid_host', __( 'The URL must be from docs.google.com or spreadsheets.google.com.', 'google-sheet-csv-updater' ), 'error' );
                return $old_url;
            }
            if ( ! isset( $parsed_url['query'] ) || strpos( $parsed_url['query'], 'output=csv' ) === false ) {
                add_settings_error( 'gscu_google_sheet_url', 'gscu_missing_output_csv', __( 'The URL must be a CSV export link (containing "output=csv").', 'google-sheet-csv-updater' ), 'error' );
                return $old_url;
            }
        } elseif (empty($output) && empty($input)) {
            return '';
        }
        return $output;
    }
    public function sanitize_csv_header_row($input) { $val = absint($input); return ($val > 0) ? $val : 1; }
    public function sanitize_post_status($input) {
        $allowed_statuses = array('draft', 'pending', 'publish');
        $status = sanitize_key($input);
        return in_array($status, $allowed_statuses) ? $status : 'draft';
    }
    public function sanitize_table_classes($input) { return trim(preg_replace('/[^a-zA-Z0-9\s_-]/', '', $input)); }
    public function sanitize_sync_schedule($input) {
        $allowed_schedules = array('disabled', 'hourly', 'twicedaily', 'daily');
        $schedule = sanitize_key($input);
        return in_array($schedule, $allowed_schedules) ? $schedule : 'daily';
    }
    public function sanitize_stale_content_action($input) {
        $allowed_actions = array('do_nothing', 'draft', 'trash', 'delete');
        $action = sanitize_key($input);
        return in_array($action, $allowed_actions) ? $action : 'do_nothing';
    }

    public function register_settings() {
        // General Settings
        register_setting( 'gscu_options_group', 'gscu_google_sheet_url', array( 'type' => 'string', 'sanitize_callback' => array($this,'sanitize_validate_sheet_url'), 'default' => '' ) );
        register_setting( 'gscu_options_group', 'gscu_cache_interval', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 3600 ) );
        add_settings_section( 'gscu_general_settings_section', __( 'General Settings', 'google-sheet-csv-updater' ), array($this,'general_settings_section_callback'), 'gscu_settings_general_tab' );
        add_settings_field( 'gscu_google_sheet_url_field', __( 'Google Sheet CSV URL', 'google-sheet-csv-updater' ), array($this,'render_sheet_url_field'), 'gscu_settings_general_tab', 'gscu_general_settings_section' );
        add_settings_field( 'gscu_cache_interval_field', __( 'Cache Interval (seconds)', 'google-sheet-csv-updater' ), array($this,'render_cache_interval_field'), 'gscu_settings_general_tab', 'gscu_general_settings_section' );

        // Mapping Settings
        register_setting( 'gscu_options_group', 'gscu_csv_header_row', array( 'type' => 'integer', 'sanitize_callback' => array($this,'sanitize_csv_header_row'), 'default' => 1 ) );
        register_setting( 'gscu_options_group', 'gscu_unique_id_column', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        register_setting( 'gscu_options_group', 'gscu_content_type', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'post' ) );
        register_setting( 'gscu_options_group', 'gscu_new_post_status', array( 'type' => 'string', 'sanitize_callback' => array($this,'sanitize_post_status'), 'default' => 'draft' ) );
        register_setting( 'gscu_options_group', 'gscu_map_title', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        register_setting( 'gscu_options_group', 'gscu_map_content', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        add_settings_section( 'gscu_mapping_settings_section', __( 'Content Type & Mapping Settings', 'google-sheet-csv-updater' ), array($this,'mapping_settings_section_callback'), 'gscu_settings_mapping_tab' );
        add_settings_field( 'gscu_csv_header_row_field', __( 'CSV Header Row Number', 'google-sheet-csv-updater' ), array($this,'render_csv_header_row_field'), 'gscu_settings_mapping_tab', 'gscu_mapping_settings_section' );
        add_settings_field( 'gscu_unique_id_column_field', __( 'Unique ID CSV Column Name', 'google-sheet-csv-updater' ), array($this,'render_unique_id_column_field'), 'gscu_settings_mapping_tab', 'gscu_mapping_settings_section' );
        add_settings_field( 'gscu_content_type_field', __( 'Select Content Type', 'google-sheet-csv-updater' ), array($this,'render_content_type_field'), 'gscu_settings_mapping_tab', 'gscu_mapping_settings_section' );
        add_settings_field( 'gscu_new_post_status_field', __( 'Status for New Posts', 'google-sheet-csv-updater' ), array($this,'render_new_post_status_field'), 'gscu_settings_mapping_tab', 'gscu_mapping_settings_section' );
        add_settings_field( 'gscu_map_title_field', __( 'Map to Post Title', 'google-sheet-csv-updater' ), array($this,'render_map_title_field'), 'gscu_settings_mapping_tab', 'gscu_mapping_settings_section' );
        add_settings_field( 'gscu_map_content_field', __( 'Map to Post Content', 'google-sheet-csv-updater' ), array($this,'render_map_content_field'), 'gscu_settings_mapping_tab', 'gscu_mapping_settings_section' );

        // Appearance Settings
        register_setting( 'gscu_options_group', 'gscu_default_posts_per_page', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 10 ) );
        register_setting( 'gscu_options_group', 'gscu_default_table_classes', array( 'type' => 'string', 'sanitize_callback' => array($this,'sanitize_table_classes'), 'default' => '' ) );
        register_setting( 'gscu_options_group', 'gscu_predefined_style', array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'basic' ) );
        add_settings_section( 'gscu_appearance_settings_section', __( 'Frontend Display & Appearance', 'google-sheet-csv-updater' ), array($this,'appearance_settings_section_callback'), 'gscu_settings_appearance_tab' );
        add_settings_field( 'gscu_default_posts_per_page_field', __( 'Default Items Per Page (Shortcode)', 'google-sheet-csv-updater' ), array($this,'render_default_posts_per_page_field'), 'gscu_settings_appearance_tab', 'gscu_appearance_settings_section' );
        add_settings_field( 'gscu_default_table_classes_field', __( 'Default Table CSS Classes', 'google-sheet-csv-updater' ), array($this,'render_default_table_classes_field'), 'gscu_settings_appearance_tab', 'gscu_appearance_settings_section' );
        add_settings_field( 'gscu_predefined_style_field', __( 'Predefined Table Style', 'google-sheet-csv-updater' ), array($this,'render_predefined_style_field'), 'gscu_settings_appearance_tab', 'gscu_appearance_settings_section' );

        // Sync Settings
        register_setting( 'gscu_options_group', 'gscu_enable_auto_sync', array( 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0 ) );
        register_setting( 'gscu_options_group', 'gscu_sync_schedule', array( 'type' => 'string', 'sanitize_callback' => array($this, 'sanitize_sync_schedule'), 'default' => 'daily' ) );
        register_setting( 'gscu_options_group', 'gscu_stale_content_action', array( 'type' => 'string', 'sanitize_callback' => array($this, 'sanitize_stale_content_action'), 'default' => 'do_nothing') ); // New Stale setting
        add_settings_section( 'gscu_sync_settings_section', __( 'Synchronization Settings', 'google-sheet-csv-updater'), array($this, 'sync_settings_section_callback'), 'gscu_settings_sync_tab');
        add_settings_field( 'gscu_enable_auto_sync_field', __( 'Enable Automatic Sync', 'google-sheet-csv-updater'), array($this, 'render_enable_auto_sync_field'), 'gscu_settings_sync_tab', 'gscu_sync_settings_section');
        add_settings_field( 'gscu_sync_schedule_field', __( 'Sync Schedule', 'google-sheet-csv-updater'), array($this, 'render_sync_schedule_field'), 'gscu_settings_sync_tab', 'gscu_sync_settings_section');
        add_settings_field( 'gscu_stale_content_action_field', __( 'Action for Stale Content', 'google-sheet-csv-updater'), array($this, 'render_stale_content_action_field'), 'gscu_settings_sync_tab', 'gscu_sync_settings_section'); // New Stale field
        add_settings_field( 'gscu_manual_sync_field', __( 'Manual Sync', 'google-sheet-csv-updater'), array($this, 'render_manual_sync_button'), 'gscu_settings_sync_tab', 'gscu_sync_settings_section');

        $options_to_monitor_cache = ['gscu_google_sheet_url', 'gscu_csv_header_row', 'gscu_cache_interval'];
        foreach ($options_to_monitor_cache as $option_name) {
            add_action("update_option_{$option_name}", array($this, 'clear_caches_on_setting_update'), 10, 3);
        }
        $options_to_monitor_cron = ['gscu_enable_auto_sync', 'gscu_sync_schedule'];
        foreach ($options_to_monitor_cron as $option_name) {
            add_action("update_option_{$option_name}", array('GSCU_Cron', 'schedule_or_clear_events'), 10, 0);
        }
    }

    // --- Section Callbacks ---
    public function general_settings_section_callback() { /* ... (unchanged) ... */
        echo '<p>' . esc_html__( 'Configure the general behavior of the Google Sheet CSV Updater plugin.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function mapping_settings_section_callback() { /* ... (unchanged) ... */
        echo '<p>' . esc_html__( 'Define how CSV data maps to your WordPress content. You must save a valid Google Sheet URL in General Settings first to populate mapping dropdowns.', 'google-sheet-csv-updater' ) . '</p>';
        echo '<p><em>' . esc_html__( 'Note: For now, only Title and Content mapping are supported. Custom field and taxonomy mapping will be added in future updates.', 'google-sheet-csv-updater' ) . '</em></p>';
    }
    public function appearance_settings_section_callback() { /* ... (unchanged) ... */
        echo '<p>' . esc_html__( 'Customize the appearance of the [google_sheet_data] shortcode output.', 'google-sheet-csv-updater' ) . '</p>';
        echo '<h4>' . esc_html__( 'Shortcode Usage Reminder:', 'google-sheet-csv-updater') . '</h4>';
        echo '<p>' . esc_html__( 'Use the shortcode:', 'google-sheet-csv-updater') . ' <code>[google_sheet_data]</code></p>';
        echo '<p>' . esc_html__( 'Available attributes:', 'google-sheet-csv-updater') . '</p>';
        echo '<ul>';
        echo '<li><code>posts_per_page</code>: ' . esc_html__( 'Number of items to show per page.', 'google-sheet-csv-updater') . ' (e.g., <code>posts_per_page="5"</code>)</li>';
        echo '<li><code>columns</code>: ' . esc_html__( 'Comma-separated list of columns to display. Special values: "title", "content", "excerpt", "date". Other values are treated as post meta keys.', 'google-sheet-csv-updater') . ' (e.g., <code>columns="title, my_custom_field, ' . esc_attr(GSCU_UNIQUE_ID_META_KEY) . '"</code>)</li>';
        echo '<li><code>table_class</code>: ' . esc_html__( 'Additional CSS classes to apply to the table.', 'google-sheet-csv-updater') . ' (e.g., <code>table_class="my-custom-table another-class"</code>)</li>';
        echo '</ul>';
    }
     public function sync_settings_section_callback() { /* ... (unchanged, displays next scheduled time) ... */
        echo '<p>' . esc_html__( 'Configure automatic synchronization schedules and trigger manual syncs.', 'google-sheet-csv-updater' ) . '</p>';
        $timestamp = wp_next_scheduled( GSCU_CRON_HOOK );
        if ( $timestamp && get_option('gscu_enable_auto_sync', 0) ) {
            $next_run = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
            echo '<p><strong>' . esc_html__( 'Next scheduled sync:', 'google-sheet-csv-updater' ) . '</strong> ' . esc_html( $next_run ) . '</p>';
        } elseif (get_option('gscu_enable_auto_sync', 0)){
            echo '<p><strong>' . esc_html__( 'Next scheduled sync:', 'google-sheet-csv-updater' ) . '</strong> ' . esc_html__('Not currently scheduled, save settings to apply schedule.', 'google-sheet-csv-updater') . '</p>';
        } else {
            echo '<p><strong>' . esc_html__( 'Automatic sync is currently disabled.', 'google-sheet-csv-updater' ) . '</strong></p>';
        }
    }

    // --- Field Rendering Callbacks ---
    public function render_sheet_url_field() { /* ... (unchanged) ... */
        $url = get_option( 'gscu_google_sheet_url', '' );
        echo '<input type="text" name="gscu_google_sheet_url" value="' . esc_attr( $url ) . '" class="regular-text" placeholder="' . esc_attr__('Enter your public Google Sheet CSV URL', 'google-sheet-csv-updater') .'">';
        echo '<p class="description">' . esc_html__( 'Ensure the link is a direct CSV export link (usually ends with /pub?output=csv). Changes here will clear the CSV cache.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_cache_interval_field() { /* ... (unchanged) ... */
        $interval = get_option( 'gscu_cache_interval', 3600 );
        echo '<input type="number" name="gscu_cache_interval" value="' . esc_attr( $interval ) . '" class="small-text" min="60">';
        echo '<p class="description">' . esc_html__( 'Time in seconds to cache the data (e.g., 3600 for 1 hour, 86400 for 1 day). Minimum 60. Changes here will clear the CSV cache.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_default_posts_per_page_field() { /* ... (unchanged) ... */
        $val = get_option( 'gscu_default_posts_per_page', 10 );
        echo '<input type="number" name="gscu_default_posts_per_page" value="' . esc_attr( $val ) . '" class="small-text" min="1">';
        echo '<p class="description">' . esc_html__( 'Default number of items to display per page for the [google_sheet_data] shortcode.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_default_table_classes_field() { /* ... (unchanged) ... */
        $val = get_option( 'gscu_default_table_classes', '' );
        echo '<input type="text" name="gscu_default_table_classes" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="' . esc_attr__('e.g., table-responsive my-table', 'google-sheet-csv-updater') .'">';
        echo '<p class="description">' . esc_html__( 'Space-separated CSS classes to add to all shortcode output tables by default.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_predefined_style_field() { /* ... (unchanged) ... */
        $current_style = get_option( 'gscu_predefined_style', 'basic' );
        $styles = array(
            'none' => __('None (No plugin styles beyond base .gscu-table)', 'google-sheet-csv-updater'),
            'basic' => __('Basic (Plugin Default - includes borders, header)', 'google-sheet-csv-updater'),
            'striped' => __('Minimalist Striped', 'google-sheet-csv-updater'),
            'bordered' => __('Bordered Table', 'google-sheet-csv-updater'),
        );
        echo '<select name="gscu_predefined_style">';
        foreach ($styles as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current_style, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Select a predefined visual style for the shortcode table. Styles are applied by adding CSS classes.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_csv_header_row_field() { /* ... (unchanged) ... */
        $val = get_option( 'gscu_csv_header_row', 1 );
        echo '<input type="number" name="gscu_csv_header_row" value="' . esc_attr( $val ) . '" class="small-text" min="1">';
        echo '<p class="description">' . esc_html__( 'The row number in your CSV that contains the column headers (e.g., 1 for the first row).', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_unique_id_column_field() { /* ... (unchanged) ... */
        $val = get_option( 'gscu_unique_id_column', '' );
        echo '<input type="text" name="gscu_unique_id_column" value="' . esc_attr( $val ) . '" class="regular-text" placeholder="' . esc_attr__('e.g., SKU or ProductID', 'google-sheet-csv-updater') .'">';
        echo '<p class="description">' . esc_html__( 'Mandatory. The exact column name from your CSV that uniquely identifies each row.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_content_type_field() { /* ... (unchanged) ... */
        $current_type = get_option( 'gscu_content_type', 'post' );
        $post_types = get_post_types( array('public' => true), 'objects' );
        echo '<select name="gscu_content_type">';
        foreach ( $post_types as $post_type ) {
            if ( in_array($post_type->name, ['attachment']) ) continue;
            echo '<option value="' . esc_attr( $post_type->name ) . '" ' . selected( $current_type, $post_type->name, false ) . '>' . esc_html( $post_type->labels->singular_name ) . ' (' . esc_html($post_type->name) . ')</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Select the WordPress content type to update or create.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_new_post_status_field() { /* ... (unchanged) ... */
        $current_status = get_option( 'gscu_new_post_status', 'draft' );
        $statuses = array( 'draft' => __('Draft'), 'pending' => __('Pending Review'), 'publish' => __('Publish') );
        echo '<select name="gscu_new_post_status">';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current_status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Select the status for newly created posts.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_mapping_dropdown( $option_name, $current_value, $headers ) { /* ... (unchanged) ... */
        echo '<select name="' . esc_attr( $option_name ) . '" ' . ( empty( $headers ) || is_string($headers) ? 'disabled' : '' ) . '>';
        echo '<option value="">' . esc_html__( '-- Select CSV Column --', 'google-sheet-csv-updater' ) . '</option>';
        if ( is_array( $headers ) && !empty( $headers ) ) {
            foreach ( $headers as $header ) {
                echo '<option value="' . esc_attr( $header ) . '" ' . selected( $current_value, $header, false ) . '>' . esc_html( $header ) . '</option>';
            }
        }
        echo '</select>';
         if ( empty( $headers ) || is_string($headers) ) {
            $url_set = get_option('gscu_google_sheet_url');
            if (empty($url_set)) {
                echo '<p class="description notice notice-warning" style="display:inline-block; padding:2px 5px;">' . esc_html__( 'Save a valid Google Sheet URL first.', 'google-sheet-csv-updater' ) . '</p>';
            } elseif (is_string($headers)) {
                 echo '<p class="description notice notice-error" style="display:inline-block; padding:2px 5px;">' . esc_html__( 'Could not fetch CSV headers. Check URL and Header Row setting. Error: ', 'google-sheet-csv-updater') . esc_html($headers) . '</p>';
            } else {
                 echo '<p class="description notice notice-warning" style="display:inline-block; padding:2px 5px;">' . esc_html__( 'Could not retrieve CSV headers. Ensure URL is correct and CSV is accessible.', 'google-sheet-csv-updater' ) . '</p>';
            }
        }
    }
    public function render_map_title_field() { /* ... (unchanged) ... */
        $current_value = get_option( 'gscu_map_title', '' );
        $headers = class_exists('GSCU_Sync') ? GSCU_Sync::get_csv_headers() : [];
        $this->render_mapping_dropdown( 'gscu_map_title', $current_value, $headers );
        echo '<p class="description">' . esc_html__( 'Select the CSV column to use for the post title.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_map_content_field() { /* ... (unchanged) ... */
        $current_value = get_option( 'gscu_map_content', '' );
        $headers = class_exists('GSCU_Sync') ? GSCU_Sync::get_csv_headers() : [];
        $this->render_mapping_dropdown( 'gscu_map_content', $current_value, $headers );
        echo '<p class="description">' . esc_html__( 'Select the CSV column to use for the main post content.', 'google-sheet-csv-updater' ) . '</p>';
    }
    public function render_enable_auto_sync_field() { /* ... (unchanged) ... */
        $enabled = get_option('gscu_enable_auto_sync', 0);
        echo '<input type="checkbox" name="gscu_enable_auto_sync" value="1" ' . checked(1, $enabled, false) . '>';
        echo '<p class="description">' . esc_html__('Enable automatic background synchronization.', 'google-sheet-csv-updater') . '</p>';
    }
    public function render_sync_schedule_field() { /* ... (unchanged) ... */
        $current_schedule = get_option('gscu_sync_schedule', 'daily');
        $schedules = wp_get_schedules();
        $valid_schedules = array_intersect_key($schedules, array_flip(['hourly', 'twicedaily', 'daily']));
        echo '<select name="gscu_sync_schedule" ' . disabled(!get_option('gscu_enable_auto_sync', 0), true, false) . '>';
        echo '<option value="disabled" '.selected($current_schedule, 'disabled', false).'>' . esc_html__('Disabled', 'google-sheet-csv-updater') . '</option>';
        foreach ($valid_schedules as $name => $details) {
            echo '<option value="' . esc_attr($name) . '" ' . selected($current_schedule, $name, false) . '>' . esc_html($details['display']) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('How often the sync should run. Only active if "Enable Automatic Sync" is checked.', 'google-sheet-csv-updater') . '</p>';
    }
    public function render_stale_content_action_field() {
        $current_action = get_option('gscu_stale_content_action', 'do_nothing');
        $actions = array(
            'do_nothing' => __('Do Nothing', 'google-sheet-csv-updater'),
            'draft'      => __('Set to Draft', 'google-sheet-csv-updater'),
            'trash'      => __('Move to Trash', 'google-sheet-csv-updater'),
            'delete'     => __('Delete Permanently', 'google-sheet-csv-updater'),
        );
        echo '<select name="gscu_stale_content_action">';
        foreach ($actions as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($current_action, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Action to take for content in WordPress that is no longer in the CSV file.', 'google-sheet-csv-updater');
        if ($current_action === 'delete') {
             echo ' <strong style="color:red;">' . esc_html__('Warning: "Delete Permanently" cannot be undone.', 'google-sheet-csv-updater') . '</strong>';
        }
        echo '</p>';
    }
    public function render_manual_sync_button() { /* ... (unchanged) ... */
        $sync_url = add_query_arg(array(
            'action' => 'gscu_manual_sync',
            '_wpnonce' => wp_create_nonce('gscu_manual_sync_nonce')
        ), admin_url('admin-post.php'));
        echo '<a href="' . esc_url($sync_url) . '" class="button button-primary">' . esc_html__('Run Manual Sync Now', 'google-sheet-csv-updater') . '</a>';
        echo '<p class="description">' . esc_html__( 'Manually trigger the synchronization process.', 'google-sheet-csv-updater' ) . '</p>';
    }

    public function render_settings_page() { /* ... (unchanged, uses new tab structure) ... */
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Google Sheet CSV Updater Settings', 'google-sheet-csv-updater' ); ?></h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=gscu-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('General', 'google-sheet-csv-updater'); ?></a>
                <a href="?page=gscu-settings&tab=mapping" class="nav-tab <?php echo $active_tab == 'mapping' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Mapping', 'google-sheet-csv-updater'); ?></a>
                <a href="?page=gscu-settings&tab=appearance" class="nav-tab <?php echo $active_tab == 'appearance' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Appearance', 'google-sheet-csv-updater'); ?></a>
                <a href="?page=gscu-settings&tab=sync" class="nav-tab <?php echo $active_tab == 'sync' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Synchronization', 'google-sheet-csv-updater'); ?></a>
                <a href="?page=gscu-settings&tab=logs" class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Sync Logs', 'google-sheet-csv-updater'); ?></a>
            </h2>
            <?php $this->display_admin_notices(); ?>

            <?php if ($active_tab == 'logs'): ?>
                <?php $this->render_logs_tab_content(); ?>
            <?php else: ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'gscu_options_group' );
                    if ($active_tab == 'general') do_settings_sections( 'gscu_settings_general_tab' );
                    elseif ($active_tab == 'mapping') do_settings_sections( 'gscu_settings_mapping_tab' );
                    elseif ($active_tab == 'appearance') do_settings_sections( 'gscu_settings_appearance_tab' );
                    elseif ($active_tab == 'sync') do_settings_sections( 'gscu_settings_sync_tab' );
                    submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    private function display_admin_notices() {
        if ( isset( $_GET['gscu_sync_result'] ) ) {
            $result_code = sanitize_key($_GET['gscu_sync_result']);
            $created = isset($_GET['created']) ? absint($_GET['created']) : 0;
            $updated = isset($_GET['updated']) ? absint($_GET['updated']) : 0;
            $skipped = isset($_GET['skipped']) ? absint($_GET['skipped']) : 0;
            $errors = isset($_GET['errors']) ? absint($_GET['errors']) : 0;
            // Stale counts for notice
            $stale_drafted = isset($_GET['stale_drafted']) ? absint($_GET['stale_drafted']) : 0;
            $stale_trashed = isset($_GET['stale_trashed']) ? absint($_GET['stale_trashed']) : 0;
            $stale_deleted = isset($_GET['stale_deleted']) ? absint($_GET['stale_deleted']) : 0;

            $stale_message_parts = [];
            if ($stale_drafted > 0) $stale_message_parts[] = sprintf(esc_html__('%d drafted', 'google-sheet-csv-updater'), $stale_drafted);
            if ($stale_trashed > 0) $stale_message_parts[] = sprintf(esc_html__('%d trashed', 'google-sheet-csv-updater'), $stale_trashed);
            if ($stale_deleted > 0) $stale_message_parts[] = sprintf(esc_html__('%d deleted', 'google-sheet-csv-updater'), $stale_deleted);

            $stale_notice = '';
            if (!empty($stale_message_parts)) {
                $stale_notice = ' ' . esc_html__('Stale content processed:', 'google-sheet-csv-updater') . ' ' . implode(', ', $stale_message_parts) . '.';
            }


            if ( $result_code === 'success' ) {
                $message = sprintf(
                    esc_html__('Synchronization complete. Created: %d, Updated: %d, Skipped: %d, Errors: %d.', 'google-sheet-csv-updater'),
                    $created, $updated, $skipped, $errors
                ) . $stale_notice;
                echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
            } elseif ( $result_code === 'error' || $result_code === 'config_error' ) {
                $error_message_transient = get_transient('gscu_sync_error_message');
                if ($error_message_transient) {
                    delete_transient('gscu_sync_error_message');
                    $error_message = $error_message_transient;
                } else {
                    $error_message = __('An unspecified error occurred during synchronization or configuration is incomplete.', 'google-sheet-csv-updater');
                }
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . $stale_notice . '</p></div>';
            }
        } elseif (isset($_GET['gscu_logs_cleared']) && $_GET['gscu_logs_cleared'] === 'true') {
             echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Synchronization logs cleared.', 'google-sheet-csv-updater') . '</p></div>';
        }
        settings_errors();
    }
    public function render_logs_tab_content() { /* ... (unchanged) ... */
        ?>
        <div class="gscu-logs-content">
            <h3><?php esc_html_e('Synchronization Log', 'google-sheet-csv-updater'); ?></h3>
            <?php
            $logs = get_option(GSCU_LOGS_OPTION_KEY, array());
            if (empty($logs)) {
                echo '<p>' . esc_html__('No sync logs available.', 'google-sheet-csv-updater') . '</p>';
            } else {
                ?>
                <table class="wp-list-table widefat striped fixed">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 25%;"><?php esc_html_e('Date/Time', 'google-sheet-csv-updater'); ?></th>
                            <th scope="col" style="width: 35%;"><?php esc_html_e('Summary', 'google-sheet-csv-updater'); ?></th>
                            <th scope="col"><?php esc_html_e('Details/Errors', 'google-sheet-csv-updater'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log_entry): ?>
                            <tr>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), $log_entry['timestamp'])); ?></td>
                                <td><?php echo esc_html($log_entry['summary']); ?></td>
                                <td>
                                    <?php
                                    if (!empty($log_entry['details']) && is_array($log_entry['details'])) {
                                        if (count($log_entry['details']) > 5) {
                                            echo '<ul>';
                                            for ($i=0; $i<5; $i++) {
                                                echo '<li>' . esc_html($log_entry['details'][$i]) . '</li>';
                                            }
                                            echo '</ul>';
                                            echo '<p><em>' . sprintf(esc_html__('...and %d more errors/details (check debug log if enabled).', 'google-sheet-csv-updater'), count($log_entry['details']) - 5) . '</em></p>';
                                        } else {
                                            echo '<ul>';
                                            foreach ($log_entry['details'] as $detail) {
                                                echo '<li>' . esc_html($detail) . '</li>';
                                            }
                                            echo '</ul>';
                                        }
                                    } elseif (!empty($log_entry['details'])) {
                                        echo esc_html($log_entry['details']);
                                    } else {
                                        echo '<em>' . esc_html__('No specific details.', 'google-sheet-csv-updater') . '</em>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <br>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="gscu_clear_logs">
                    <?php wp_nonce_field('gscu_clear_logs_nonce'); ?>
                    <?php submit_button(__('Clear Sync Logs', 'google-sheet-csv-updater'), 'delete', 'gscu-clear-logs-submit', false); ?>
                </form>
                <?php
            }
            ?>
        </div>
        <?php
    }
    public function handle_manual_sync() {
        if ( !isset( $_GET['_wpnonce'] ) || !wp_verify_nonce( sanitize_key($_GET['_wpnonce']), 'gscu_manual_sync_nonce' ) ) {
            wp_die(__( 'Nonce verification failed!', 'google-sheet-csv-updater' ));
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die(__( 'You do not have sufficient permissions to perform this action.', 'google-sheet-csv-updater' ));
        }
        $sync_results = class_exists('GSCU_Sync') ? GSCU_Sync::synchronize_content() : ['errors' => 1, 'error_messages' => ['GSCU_Sync class not found.'], 'created'=>0, 'updated'=>0, 'skipped'=>0, 'stale_drafted'=>0, 'stale_trashed'=>0, 'stale_deleted'=>0];

        $redirect_url = admin_url('options-general.php?page=gscu-settings');
        $first_error = !empty($sync_results['error_messages'][0]) ? $sync_results['error_messages'][0] : '';

        if (strpos($first_error, 'Crucial settings') !== false || strpos($first_error, 'Failed to fetch or parse CSV headers') !== false) {
            $redirect_url = add_query_arg('gscu_sync_result', 'config_error', $redirect_url);
            set_transient('gscu_sync_error_message', $first_error, 60);
        } elseif ($sync_results['errors'] > 0) {
            $redirect_url = add_query_arg('gscu_sync_result', 'error', $redirect_url);
            set_transient('gscu_sync_error_message', __('Synchronization encountered errors. Please check the Sync Logs for details.', 'google-sheet-csv-updater'), 60);
        } else {
            $redirect_url = add_query_arg('gscu_sync_result', 'success', $redirect_url);
        }

        $query_args_on_redirect = array(
            'created' => $sync_results['created'],
            'updated' => $sync_results['updated'],
            'skipped' => $sync_results['skipped'],
            'errors'  => $sync_results['errors'],
            'stale_drafted' => isset($sync_results['stale_drafted']) ? $sync_results['stale_drafted'] : 0,
            'stale_trashed' => isset($sync_results['stale_trashed']) ? $sync_results['stale_trashed'] : 0,
            'stale_deleted' => isset($sync_results['stale_deleted']) ? $sync_results['stale_deleted'] : 0,
        );
        $redirect_url = add_query_arg(array_filter($query_args_on_redirect), $redirect_url); // array_filter to remove zero counts

        wp_safe_redirect( $redirect_url );
        exit;
    }
    public function handle_clear_logs() { /* ... (unchanged) ... */
        if ( !isset( $_POST['_wpnonce'] ) || !wp_verify_nonce( sanitize_text_field($_POST['_wpnonce']), 'gscu_clear_logs_nonce' ) ) {
            wp_die(__( 'Nonce verification failed!', 'google-sheet-csv-updater' ));
        }
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die(__( 'You do not have sufficient permissions to perform this action.', 'google-sheet-csv-updater' ));
        }
        delete_option(GSCU_LOGS_OPTION_KEY);
        $redirect_url = add_query_arg(array(
            'page' => 'gscu-settings',
            'tab' => 'logs',
            'gscu_logs_cleared' => 'true'
        ), admin_url('options-general.php'));
        wp_safe_redirect($redirect_url);
        exit;
    }
    public function add_settings_link( $links ) { /* ... (unchanged) ... */
        $settings_link = '<a href="options-general.php?page=gscu-settings">' . __( 'Settings', 'google-sheet-csv-updater' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
    public function clear_caches_on_setting_update( $old_value, $value, $option_name ) { /* ... (unchanged) ... */
        $options_that_affect_headers = array( 'gscu_google_sheet_url', 'gscu_csv_header_row' );
        $options_that_affect_full_data = array( 'gscu_google_sheet_url', 'gscu_cache_interval', 'gscu_csv_header_row' );

        $sheet_url_for_old_cache = ($option_name === 'gscu_google_sheet_url') ? $old_value : get_option('gscu_google_sheet_url');
        $header_row_for_old_cache = ($option_name === 'gscu_csv_header_row') ? $old_value : get_option('gscu_csv_header_row', 1);
        $sheet_url_for_new_cache = ($option_name === 'gscu_google_sheet_url') ? $value : get_option('gscu_google_sheet_url');
        $header_row_for_new_cache = ($option_name === 'gscu_csv_header_row') ? $value : get_option('gscu_csv_header_row', 1);

        if ( $old_value !== $value ) {
            if ( in_array( $option_name, $options_that_affect_headers ) ) {
                if (!empty($sheet_url_for_old_cache)) {
                     delete_transient( 'gscu_csv_headers_' . md5( $sheet_url_for_old_cache . '_' . $header_row_for_old_cache ) );
                }
                 if (!empty($sheet_url_for_new_cache)) {
                     delete_transient( 'gscu_csv_headers_' . md5( $sheet_url_for_new_cache . '_' . $header_row_for_new_cache ) );
                }
            }
            if ( in_array( $option_name, $options_that_affect_full_data ) ) {
                 if (!empty($sheet_url_for_old_cache)) {
                    delete_transient( 'gscu_cached_sheet_data_' . md5( $sheet_url_for_old_cache ) );
                 }
                 if (!empty($sheet_url_for_new_cache)) {
                    delete_transient( 'gscu_cached_sheet_data_' . md5( $sheet_url_for_new_cache ) );
                 }
            }
        }
    }
}
