<?php
// tests/test-class-gscu-sync.php

/**
 * Unit tests for GSCU_Sync class.
 * @package Google_Sheet_CSV_Updater
 */
class Test_GSCU_Sync extends WP_UnitTestCase {

    private $sync_instance;
    private $sample_csv_data_full = "UID,Name,Description\n1,Item1,Desc1\n2,Item2,Desc2\n3,Item3,Desc3";
    private $sample_csv_data_header_only = "UID,Name,Description";
    private $sample_csv_data_malformed = "UID,Name\n1,Item1,ExtraCol\n2,Item2";


    public function setUp(): void {
        parent::setUp();
        // GSCU_Sync methods are static, so no instance needed for direct calls.
        // However, if it were not static:
        // require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-sync.php';
        // $this->sync_instance = new GSCU_Sync();

        // Clean up options before each test
        delete_option('gscu_google_sheet_url');
        delete_option('gscu_csv_header_row');
        delete_option('gscu_unique_id_column');
        delete_option('gscu_content_type');
        delete_option('gscu_map_title');
        delete_option('gscu_map_content');
        delete_option('gscu_new_post_status');
        delete_option(GSCU_LOGS_OPTION_KEY);

        // Clear transients
        delete_transient('gscu_csv_headers_test_url_1'); // Example, actual keys are dynamic
        delete_transient('gscu_cached_sheet_data_test_url');
    }

    public function tearDown(): void {
        remove_all_filters('pre_http_request');
        parent::tearDown();
    }

    // --- Helper for mocking wp_remote_get ---
    private function mock_wp_remote_get($response_body, $response_code = 200, $response_message = 'OK') {
        add_filter('pre_http_request', function ($preempt, $r, $url) use ($response_body, $response_code, $response_message) {
            if (is_wp_error($response_body)) { // If we want to return a WP_Error
                return $response_body;
            }
            return array(
                'response' => array('code' => $response_code, 'message' => $response_message),
                'body'     => $response_body,
                'headers'  => array(), 'cookies' => array(), 'filename' => null
            );
        }, 10, 3);
    }

    private function get_dynamic_header_cache_key($url, $header_row) {
        return 'gscu_csv_headers_' . md5( $url . '_' . $header_row );
    }

    private function get_dynamic_data_cache_key($url) {
        return 'gscu_cached_sheet_data_' . md5( $url );
    }

    // --- Tests for get_csv_headers() ---
    public function test_get_csv_headers_success() {
        $test_url = 'http://test.com/valid.csv';
        update_option('gscu_google_sheet_url', $test_url);
        update_option('gscu_csv_header_row', 1);
        $this->mock_wp_remote_get($this->sample_csv_data_full);

        $headers = GSCU_Sync::get_csv_headers();
        $this->assertEquals(array('UID', 'Name', 'Description'), $headers);

        // Test transient caching
        $transient_key = $this->get_dynamic_header_cache_key($test_url, 1);
        $this->assertEquals($headers, get_transient($transient_key));
        delete_transient($transient_key);
    }

    public function test_get_csv_headers_different_row() {
        $test_url = 'http://test.com/valid.csv';
        update_option('gscu_google_sheet_url', $test_url);
        update_option('gscu_csv_header_row', 2);
        $csv_with_leading_junk = "Junk1,Junk2\nUID,Name,Description\nVal1,Val2,Val3";
        $this->mock_wp_remote_get($csv_with_leading_junk);

        $headers = GSCU_Sync::get_csv_headers();
        $this->assertEquals(array('UID', 'Name', 'Description'), $headers);
        delete_transient($this->get_dynamic_header_cache_key($test_url, 2));
    }

    public function test_get_csv_headers_wp_error() {
        update_option('gscu_google_sheet_url', 'http://test.com/error.csv');
        $this->mock_wp_remote_get(new WP_Error('http_request_failed', 'Test error'));
        $this->assertEquals(GSCU_ERROR_FETCH_FAILED, GSCU_Sync::get_csv_headers());
    }

    public function test_get_csv_headers_empty_body() {
        $test_url = 'http://test.com/empty.csv';
        update_option('gscu_google_sheet_url', $test_url);
        $this->mock_wp_remote_get('');
        $this->assertEquals(GSCU_ERROR_EMPTY_BODY, GSCU_Sync::get_csv_headers());
    }

    // --- Tests for get_sheet_data() ---
    public function test_get_sheet_data_success() {
        $test_url = 'http://test.com/data.csv';
        update_option('gscu_google_sheet_url', $test_url);
        update_option('gscu_csv_header_row', 1);
        $this->mock_wp_remote_get($this->sample_csv_data_full);

        $data = GSCU_Sync::get_sheet_data();
        $expected_data = array(
            array('1', 'Item1', 'Desc1'),
            array('2', 'Item2', 'Desc2'),
            array('3', 'Item3', 'Desc3'),
        );
        $this->assertEquals($expected_data, $data);

        // Test transient caching
        $transient_key = $this->get_dynamic_data_cache_key($test_url);
        $this->assertEquals($data, get_transient($transient_key));
        delete_transient($transient_key);
    }

    public function test_get_sheet_data_skips_header_row_correctly() {
        $test_url = 'http://test.com/data_header_skip.csv';
        update_option('gscu_google_sheet_url', $test_url);
        update_option('gscu_csv_header_row', 2); // Header is on row 2
        $csv_content = "Junk,Line\nUID,Name,Desc\n1,Data1,Info1";
        $this->mock_wp_remote_get($csv_content);

        $data = GSCU_Sync::get_sheet_data();
        $expected_data = array(
            array('1', 'Data1', 'Info1'),
        );
        $this->assertEquals($expected_data, $data);
        delete_transient($this->get_dynamic_data_cache_key($test_url));
    }


    // --- Tests for synchronize_content() ---
    public function test_synchronize_content_create_posts() {
        // Setup options
        $test_url = 'http://test.com/sync_create.csv';
        update_option('gscu_google_sheet_url', $test_url);
        update_option('gscu_csv_header_row', 1);
        update_option('gscu_unique_id_column', 'UID');
        update_option('gscu_content_type', 'post');
        update_option('gscu_map_title', 'Name');
        update_option('gscu_map_content', 'Description');
        update_option('gscu_new_post_status', 'publish');

        $this->mock_wp_remote_get($this->sample_csv_data_full);

        $result = GSCU_Sync::synchronize_content();

        $this->assertEquals(3, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['errors']);

        // Verify posts
        $args = array('post_type' => 'post', 'meta_key' => GSCU_UNIQUE_ID_META_KEY, 'posts_per_page' => -1);
        $created_posts = get_posts($args);
        $this->assertCount(3, $created_posts);

        foreach ($created_posts as $post) {
            if (get_post_meta($post->ID, GSCU_UNIQUE_ID_META_KEY, true) == '1') {
                $this->assertEquals('Item1', $post->post_title);
                $this->assertEquals('Desc1', $post->post_content);
            }
        }
        // Clean up created posts
        foreach ($created_posts as $post) wp_delete_post($post->ID, true);
    }

    public function test_synchronize_content_update_posts() {
        // Create initial posts
        $post_ids = $this->factory()->post->create_many(2, array('post_type' => 'post', 'post_status' => 'publish'));
        update_post_meta($post_ids[0], GSCU_UNIQUE_ID_META_KEY, '1');
        update_post_meta($post_ids[1], GSCU_UNIQUE_ID_META_KEY, '2');
        wp_update_post(array('ID' => $post_ids[0], 'post_title' => 'Old Item1', 'post_content' => 'Old Desc1'));
        wp_update_post(array('ID' => $post_ids[1], 'post_title' => 'Old Item2', 'post_content' => 'Old Desc2'));


        $test_url = 'http://test.com/sync_update.csv';
        update_option('gscu_google_sheet_url', $test_url);
        update_option('gscu_csv_header_row', 1);
        update_option('gscu_unique_id_column', 'UID');
        update_option('gscu_content_type', 'post');
        update_option('gscu_map_title', 'Name');
        update_option('gscu_map_content', 'Description');

        // CSV data that will update existing posts and create one new one
        $update_csv_data = "UID,Name,Description\n1,Updated Item1,Updated Desc1\n2,Item2,Updated Desc2\n4,New Item4,New Desc4";
        $this->mock_wp_remote_get($update_csv_data);

        $result = GSCU_Sync::synchronize_content();

        $this->assertEquals(1, $result['created']); // Item 4
        $this->assertEquals(2, $result['updated']); // Item 1 and 2
        $this->assertEquals(0, $result['errors']);

        $this->assertEquals('Updated Item1', get_post($post_ids[0])->post_title);
        $this->assertEquals('Updated Desc1', get_post($post_ids[0])->post_content);
        $this->assertEquals('Updated Desc2', get_post($post_ids[1])->post_content); // Title for Item2 was same

        // Clean up
        wp_delete_post($post_ids[0], true);
        wp_delete_post($post_ids[1], true);
        $new_post = get_posts(array('meta_value' => '4', 'meta_key' => GSCU_UNIQUE_ID_META_KEY, 'post_type' => 'post'));
        if ($new_post) wp_delete_post($new_post[0]->ID, true);
    }

    public function test_synchronize_content_missing_crucial_settings() {
        // Missing gscu_unique_id_column
        $result = GSCU_Sync::synchronize_content();
        $this->assertEquals(1, $result['errors']);
        $this->assertStringContainsString('Crucial settings', $result['error_messages'][0]);
    }


    // --- Tests for save_sync_log_entry() ---
    public function test_save_sync_log_entry() {
        $initial_logs_count = count(get_option(GSCU_LOGS_OPTION_KEY, array()));

        $sync_results1 = array('created' => 1, 'updated' => 2, 'skipped' => 0, 'errors' => 0, 'error_messages' => array());
        GSCU_Sync::save_sync_log_entry($sync_results1);

        $logs = get_option(GSCU_LOGS_OPTION_KEY);
        $this->assertCount($initial_logs_count + 1, $logs);
        $this->assertStringContainsString('Created: 1, Updated: 2', $logs[0]['summary']);
        $this->assertIsArray($logs[0]['details']);

        $sync_results2 = array('created' => 0, 'updated' => 0, 'skipped' => 1, 'errors' => 1, 'error_messages' => array("Test error detail"));
        GSCU_Sync::save_sync_log_entry($sync_results2);

        $logs = get_option(GSCU_LOGS_OPTION_KEY);
        $this->assertCount($initial_logs_count + 2 > GSCU_MAX_LOG_ENTRIES ? GSCU_MAX_LOG_ENTRIES : $initial_logs_count + 2, $logs);
        $this->assertStringContainsString('Skipped: 1, Errors: 1', $logs[0]['summary']);
        $this->assertEquals(array("Test error detail"), $logs[0]['details']);
    }

    public function test_save_sync_log_entry_rotation() {
        // Fill up the logs to max
        for ($i = 0; $i < GSCU_MAX_LOG_ENTRIES + 5; $i++) {
            GSCU_Sync::save_sync_log_entry(array('created' => $i, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'error_messages' => array()));
        }
        $logs = get_option(GSCU_LOGS_OPTION_KEY);
        $this->assertCount(GSCU_MAX_LOG_ENTRIES, $logs);
        // Check that the last entry added (i.e., highest 'created' count) is now the first one
        $this->assertStringContainsString('Created: ' . (GSCU_MAX_LOG_ENTRIES + 4), $logs[0]['summary']);
    }
}
