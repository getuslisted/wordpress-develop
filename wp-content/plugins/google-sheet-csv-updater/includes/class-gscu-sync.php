<?php
// wp-content/plugins/google-sheet-csv-updater/includes/class-gscu-sync.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class GSCU_Sync {

    public static function get_csv_headers() {
        // ... (unchanged from previous version)
        $sheet_url = get_option( 'gscu_google_sheet_url' );
        $header_row_number = get_option( 'gscu_csv_header_row', 1 );
        $header_row_number = max(1, absint($header_row_number));
        if ( empty( $sheet_url ) ) { return GSCU_ERROR_NO_URL; }
        $header_cache_key = 'gscu_csv_headers_' . md5( $sheet_url . '_' . $header_row_number );
        $cached_headers = get_transient( $header_cache_key );
        if ( false !== $cached_headers ) { return $cached_headers; }
        $response = wp_remote_get( $sheet_url, array('timeout' => 15) );
        if ( is_wp_error( $response ) ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Header Fetch WP_Error: ' . $response->get_error_message() ); } return GSCU_ERROR_FETCH_FAILED; }
        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Header Fetch HTTP Error: Code ' . $http_code ); } return GSCU_ERROR_INVALID_RESPONSE; }
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Header Fetch Empty Body' ); } return GSCU_ERROR_EMPTY_BODY; }
        $lines = explode( "\n", $body );
        if ( count( $lines ) < $header_row_number ) { set_transient( $header_cache_key, GSCU_ERROR_NO_HEADERS, HOUR_IN_SECONDS ); return GSCU_ERROR_NO_HEADERS; }
        $header_line = trim( $lines[ $header_row_number - 1 ] );
        if ( empty( $header_line ) ) { set_transient( $header_cache_key, GSCU_ERROR_NO_HEADERS, HOUR_IN_SECONDS ); return GSCU_ERROR_NO_HEADERS; }
        $headers = str_getcsv( $header_line );
        if ( empty( $headers ) || (count($headers) === 1 && empty(trim($headers[0]))) ) { set_transient( $header_cache_key, GSCU_ERROR_NO_HEADERS, HOUR_IN_SECONDS ); return GSCU_ERROR_NO_HEADERS; }
        $headers = array_map('trim', $headers);
        $headers = array_filter($headers, function($header) { return !empty($header); });
        if (empty($headers)) { set_transient( $header_cache_key, GSCU_ERROR_NO_HEADERS, HOUR_IN_SECONDS ); return GSCU_ERROR_NO_HEADERS; }
        set_transient( $header_cache_key, $headers, HOUR_IN_SECONDS );
        return $headers;
    }

    public static function get_sheet_data() {
        // ... (unchanged from previous version)
        $sheet_url = get_option( 'gscu_google_sheet_url' );
        $cache_interval = get_option( 'gscu_cache_interval', 3600 );
        if ( empty( $sheet_url ) ) { return GSCU_ERROR_NO_URL; }
        $cache_key = 'gscu_cached_sheet_data_' . md5( $sheet_url );
        $cached_data = get_transient( $cache_key );
        if ( false !== $cached_data ) { if (is_string($cached_data) && (strpos($cached_data, 'gscu_error_') === 0 || $cached_data === GSCU_SUCCESS_NO_DATA) ) { return $cached_data; } if (is_array($cached_data)) { return $cached_data; } }
        $response = wp_remote_get( $sheet_url );
        if ( is_wp_error( $response ) ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Data Fetch WP_Error: ' . $response->get_error_message() . ' for URL: ' . $sheet_url ); } set_transient( $cache_key, GSCU_ERROR_FETCH_FAILED, $cache_interval ); return GSCU_ERROR_FETCH_FAILED; }
        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Data Fetch HTTP Error: Code ' . $http_code . ' for URL: ' . $sheet_url ); } set_transient( $cache_key, GSCU_ERROR_INVALID_RESPONSE, $cache_interval ); return GSCU_ERROR_INVALID_RESPONSE; }
        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Data Fetch Empty response body for URL: ' . $sheet_url ); } set_transient( $cache_key, GSCU_ERROR_EMPTY_BODY, $cache_interval ); return GSCU_ERROR_EMPTY_BODY; }
        $lines = explode( "\n", trim( $body ) );
        if (count($lines) === 1 && trim($lines[0]) === '') { set_transient( $cache_key, GSCU_SUCCESS_NO_DATA, $cache_interval ); return GSCU_SUCCESS_NO_DATA; }
        $data_rows = array();
        $header_row_number = max(1, absint(get_option( 'gscu_csv_header_row', 1 )));
        $start_index = $header_row_number;
        if (count($lines) <= $start_index) { set_transient( $cache_key, GSCU_SUCCESS_NO_DATA, $cache_interval ); return GSCU_SUCCESS_NO_DATA; }
        for ($i = $start_index; $i < count($lines); $i++) { $line = trim($lines[$i]); if (empty($line)) { continue; } $parsed_line = str_getcsv( $line ); if (count($parsed_line) === 1 && $parsed_line[0] === null) { if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) { error_log( '[GSCU] Data Parse error on data line: ' . $line ); } } $data_rows[] = $parsed_line; }
        if ( empty( $data_rows ) ) { set_transient( $cache_key, GSCU_SUCCESS_NO_DATA, $cache_interval ); return GSCU_SUCCESS_NO_DATA; }
        set_transient( $cache_key, $data_rows, absint( $cache_interval ) );
        return $data_rows;
    }

    public static function synchronize_content() {
        $sync_run_log = array(
            'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0,
            'stale_drafted' => 0, 'stale_trashed' => 0, 'stale_deleted' => 0,
            'error_messages' => array()
        );
        $synced_post_ids_this_run = array();

        // Retrieve settings
        $sheet_url = get_option('gscu_google_sheet_url');
        $header_row_num = max(1, absint(get_option('gscu_csv_header_row', 1)));
        $unique_id_col_name = sanitize_text_field(get_option('gscu_unique_id_column'));
        $content_type = sanitize_key(get_option('gscu_content_type', 'post'));
        $map_title_col_name = sanitize_text_field(get_option('gscu_map_title'));
        $map_content_col_name = sanitize_text_field(get_option('gscu_map_content'));
        $new_post_status = sanitize_key(get_option('gscu_new_post_status', 'draft'));
        $stale_content_action = get_option('gscu_stale_content_action', 'do_nothing');

        if (empty($sheet_url) || empty($unique_id_col_name) || empty($content_type) || empty($map_title_col_name)) {
            $sync_run_log['error_messages'][] = __('Crucial settings (Sheet URL, Unique ID Column, Content Type, or Title Mapping) are missing.', 'google-sheet-csv-updater');
            $sync_run_log['errors']++;
            self::save_sync_log_entry($sync_run_log);
            return $sync_run_log;
        }

        // Get all currently managed post IDs before sync
        $all_plugin_managed_post_ids_query_args = array(
            'post_type' => $content_type,
            'meta_key' => GSCU_UNIQUE_ID_META_KEY,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'), // All except trash, auto-draft
        );
        $all_plugin_managed_post_ids = get_posts($all_plugin_managed_post_ids_query_args);


        $csv_headers = self::get_csv_headers();
        if (is_string($csv_headers) || empty($csv_headers)) {
            $sync_run_log['error_messages'][] = __('Failed to fetch or parse CSV headers. Check URL and header row setting.', 'google-sheet-csv-updater');
            $sync_run_log['errors']++;
            self::save_sync_log_entry($sync_run_log);
            return $sync_run_log;
        }

        $unique_id_col_idx = array_search($unique_id_col_name, $csv_headers);
        $map_title_col_idx = array_search($map_title_col_name, $csv_headers);
        $map_content_col_idx = array_search($map_content_col_name, $csv_headers);

        if ($unique_id_col_idx === false || $map_title_col_idx === false) {
            $missing_cols = array();
            if ($unique_id_col_idx === false) $missing_cols[] = $unique_id_col_name . ' (Unique ID)';
            if ($map_title_col_idx === false) $missing_cols[] = $map_title_col_name . ' (Title)';
            $sync_run_log['error_messages'][] = sprintf(__('Mapped CSV columns not found in sheet headers: %s.', 'google-sheet-csv-updater'), implode(', ', $missing_cols));
            $sync_run_log['errors']++;
            self::save_sync_log_entry($sync_run_log);
            return $sync_run_log;
        }

        $csv_data_rows = self::get_sheet_data();
        if (is_string($csv_data_rows) || empty($csv_data_rows)) {
            $sync_run_log['error_messages'][] = __('Failed to fetch or parse CSV data rows, or sheet is empty after header.', 'google-sheet-csv-updater');
            if ($csv_data_rows !== GSCU_SUCCESS_NO_DATA) $sync_run_log['errors']++;
            // No data to process, proceed to stale content handling
        } else {
            // Process CSV rows
            foreach ($csv_data_rows as $row_num => $row_data) {
                if (count($row_data) <= max($unique_id_col_idx, $map_title_col_idx) || ( $map_content_col_idx !== false && isset($row_data[$map_content_col_idx]) && count($row_data) <= $map_content_col_idx) ) {
                    $sync_run_log['skipped']++;
                    $sync_run_log['error_messages'][] = sprintf(__('Row %d: Not enough columns.', 'google-sheet-csv-updater'), $row_num + $header_row_num + 1);
                    continue;
                }

                $unique_id_value = trim($row_data[$unique_id_col_idx]);
                if (empty($unique_id_value)) {
                    $sync_run_log['skipped']++;
                    $sync_run_log['error_messages'][] = sprintf(__('Row %d: Empty Unique ID.', 'google-sheet-csv-updater'), $row_num + $header_row_num + 1);
                    continue;
                }

                $post_title = sanitize_text_field($row_data[$map_title_col_idx]);
                $post_content = ($map_content_col_idx !== false && isset($row_data[$map_content_col_idx])) ? $row_data[$map_content_col_idx] : '';

                $post_args = array( 'post_type' => $content_type, 'meta_key' => GSCU_UNIQUE_ID_META_KEY, 'meta_value' => $unique_id_value, 'posts_per_page' => 1, 'post_status' => 'any', 'suppress_filters' => true );
                $existing_posts = get_posts($post_args);
                $post_data_array = array( 'post_title' => $post_title, 'post_content' => $post_content );

                if (!empty($existing_posts)) {
                    $post_to_update = $existing_posts[0];
                    $post_data_array['ID'] = $post_to_update->ID;
                    $updated_post_id = wp_update_post($post_data_array, true);
                    if (is_wp_error($updated_post_id)) {
                        $sync_run_log['errors']++;
                        $sync_run_log['error_messages'][] = sprintf(__('Error updating post for ID %s: %s', 'google-sheet-csv-updater'), $unique_id_value, $updated_post_id->get_error_message());
                    } else {
                        $sync_run_log['updated']++;
                        $synced_post_ids_this_run[] = $post_to_update->ID;
                    }
                } else {
                    $post_data_array['post_status'] = $new_post_status;
                    $post_data_array['post_type'] = $content_type;
                    $inserted_post_id = wp_insert_post($post_data_array, true);
                    if (is_wp_error($inserted_post_id)) {
                        $sync_run_log['errors']++;
                        $sync_run_log['error_messages'][] = sprintf(__('Error creating post for ID %s: %s', 'google-sheet-csv-updater'), $unique_id_value, $inserted_post_id->get_error_message());
                    } else {
                        update_post_meta($inserted_post_id, GSCU_UNIQUE_ID_META_KEY, $unique_id_value);
                        $sync_run_log['created']++;
                        $synced_post_ids_this_run[] = $inserted_post_id;
                    }
                }
            }
        } // End of CSV processing

        // Handle stale content
        if ($stale_content_action !== 'do_nothing') {
            $stale_post_ids = array_diff($all_plugin_managed_post_ids, $synced_post_ids_this_run);
            if (!empty($stale_post_ids)) {
                foreach ($stale_post_ids as $stale_post_id) {
                    switch ($stale_content_action) {
                        case 'draft':
                            if (wp_update_post(array('ID' => $stale_post_id, 'post_status' => 'draft'))) {
                                $sync_run_log['stale_drafted']++;
                            } else {
                                $sync_run_log['errors']++;
                                $sync_run_log['error_messages'][] = sprintf(__('Error setting post ID %d to draft.', 'google-sheet-csv-updater'), $stale_post_id);
                            }
                            break;
                        case 'trash':
                            if (wp_trash_post($stale_post_id)) {
                                $sync_run_log['stale_trashed']++;
                            } else {
                                $sync_run_log['errors']++;
                                $sync_run_log['error_messages'][] = sprintf(__('Error trashing post ID %d.', 'google-sheet-csv-updater'), $stale_post_id);
                            }
                            break;
                        case 'delete':
                            if (wp_delete_post($stale_post_id, true)) { // true for force delete
                                $sync_run_log['stale_deleted']++;
                            } else {
                                $sync_run_log['errors']++;
                                $sync_run_log['error_messages'][] = sprintf(__('Error deleting post ID %d permanently.', 'google-sheet-csv-updater'), $stale_post_id);
                            }
                            break;
                    }
                }
            }
        }

        self::save_sync_log_entry($sync_run_log);

        if (defined( 'WP_DEBUG' ) && WP_DEBUG && !empty($sync_run_log['error_messages'])) {
            foreach($sync_run_log['error_messages'] as $msg) {
                error_log('[GSCU Sync] ' . $msg);
            }
        }
        return $sync_run_log;
    }

    public static function save_sync_log_entry($sync_results) {
        $logs = get_option(GSCU_LOGS_OPTION_KEY, array());

        $summary_parts = array();
        $summary_parts[] = sprintf(__('Created: %d', 'google-sheet-csv-updater'), $sync_results['created']);
        $summary_parts[] = sprintf(__('Updated: %d', 'google-sheet-csv-updater'), $sync_results['updated']);
        $summary_parts[] = sprintf(__('Skipped: %d', 'google-sheet-csv-updater'), $sync_results['skipped']);
        $summary_parts[] = sprintf(__('Errors: %d', 'google-sheet-csv-updater'), $sync_results['errors']);

        $stale_actions_summary = array();
        if (isset($sync_results['stale_drafted']) && $sync_results['stale_drafted'] > 0) {
            $stale_actions_summary[] = sprintf(__('Stale Drafted: %d', 'google-sheet-csv-updater'), $sync_results['stale_drafted']);
        }
        if (isset($sync_results['stale_trashed']) && $sync_results['stale_trashed'] > 0) {
            $stale_actions_summary[] = sprintf(__('Stale Trashed: %d', 'google-sheet-csv-updater'), $sync_results['stale_trashed']);
        }
        if (isset($sync_results['stale_deleted']) && $sync_results['stale_deleted'] > 0) {
            $stale_actions_summary[] = sprintf(__('Stale Deleted: %d', 'google-sheet-csv-updater'), $sync_results['stale_deleted']);
        }

        $summary_string = __('Sync: ', 'google-sheet-csv-updater') . implode(', ', $summary_parts) . '.';
        if (!empty($stale_actions_summary)) {
            $summary_string .= ' ' . implode(', ', $stale_actions_summary) . '.';
        }

        if ($sync_results['errors'] > 0 && empty($sync_results['created']) && empty($sync_results['updated']) && empty($sync_results['skipped']) && empty($stale_actions_summary)) {
             $summary_string = sprintf(
                __('Sync failed with %d error(s). First error: %s', 'google-sheet-csv-updater'),
                $sync_results['errors'],
                !empty($sync_results['error_messages'][0]) ? $sync_results['error_messages'][0] : 'Unknown error'
            );
        }

        $new_log_entry = array(
            'timestamp' => time(),
            'summary'   => $summary_string,
            'details'   => $sync_results['error_messages']
        );

        array_unshift($logs, $new_log_entry);
        $logs = array_slice($logs, 0, GSCU_MAX_LOG_ENTRIES);

        update_option(GSCU_LOGS_OPTION_KEY, $logs);
    }
}
