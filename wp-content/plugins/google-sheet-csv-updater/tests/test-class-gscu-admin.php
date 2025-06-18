<?php
// tests/test-class-gscu-admin.php

/**
 * Unit tests for GSCU_Admin class.
 * @package Google_Sheet_CSV_Updater
 */
class Test_GSCU_Admin extends WP_UnitTestCase {

    private $admin_instance;
    private static $admin_user_id;

    public static function wpSetUpBeforeClass( $factory ) {
        self::$admin_user_id = $factory->user->create( array( 'role' => 'administrator' ) );
    }

    public static function wpTearDownAfterClass() {
        // self::delete_user( self::$admin_user_id ); // WP_UnitTestcase usually handles this
    }

    public function setUp(): void {
        parent::setUp();
        // Set current user to admin for testing admin functionalities
        wp_set_current_user( self::$admin_user_id );

        require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-admin.php';
        $this->admin_instance = new GSCU_Admin();

        // Clear options that might be set by other tests
        delete_option('gscu_google_sheet_url');
        // ... any other relevant options
    }

    public function tearDown(): void {
        // Clear specific options set during tests
        delete_option('gscu_google_sheet_url');
        delete_option('gscu_default_table_classes');
        // ...
        parent::tearDown();
    }

    // Test sanitization functions
    public function test_sanitize_validate_sheet_url() {
        // Valid URL
        $valid_url = 'https://docs.google.com/spreadsheets/d/test-id/pub?output=csv';
        $this->assertEquals($valid_url, $this->admin_instance->sanitize_validate_sheet_url($valid_url));

        // Invalid host
        $invalid_host = 'https://example.com/sheet.csv';
        update_option('gscu_google_sheet_url', ''); // Set current db value
        $this->assertEquals('', $this->admin_instance->sanitize_validate_sheet_url($invalid_host));
        // In a real scenario, add_settings_error would be called. Testing that directly is complex.

        // Missing output=csv
        $missing_param = 'https://docs.google.com/spreadsheets/d/test-id/pub';
        update_option('gscu_google_sheet_url', '');
        $this->assertEquals('', $this->admin_instance->sanitize_validate_sheet_url($missing_param));

        // Malformed URL
        $malformed = 'htp:/totally.broken';
         update_option('gscu_google_sheet_url', '');
        $this->assertEquals('', $this->admin_instance->sanitize_validate_sheet_url($malformed));

        // Empty URL should be allowed (clears the setting)
        $this->assertEquals('', $this->admin_instance->sanitize_validate_sheet_url(''));
    }

    public function test_sanitize_csv_header_row() {
        $this->assertEquals(1, $this->admin_instance->sanitize_csv_header_row('1'));
        $this->assertEquals(5, $this->admin_instance->sanitize_csv_header_row('5'));
        $this->assertEquals(1, $this->admin_instance->sanitize_csv_header_row('0')); // Min 1
        $this->assertEquals(1, $this->admin_instance->sanitize_csv_header_row('-5')); // Min 1
        $this->assertEquals(1, $this->admin_instance->sanitize_csv_header_row('abc')); // absint makes it 0, then min 1
    }

    public function test_sanitize_post_status() {
        $this->assertEquals('draft', $this->admin_instance->sanitize_post_status('draft'));
        $this->assertEquals('publish', $this->admin_instance->sanitize_post_status('publish'));
        $this->assertEquals('pending', $this->admin_instance->sanitize_post_status('pending'));
        $this->assertEquals('draft', $this->admin_instance->sanitize_post_status('invalid_status')); // Default
    }

    public function test_sanitize_table_classes() {
        $this->assertEquals('class-one class-two', $this->admin_instance->sanitize_table_classes('class-one class-two'));
        $this->assertEquals('class-one_special class-two', $this->admin_instance->sanitize_table_classes('class-one_special class-two'));
        $this->assertEquals('class1 class2', $this->admin_instance->sanitize_table_classes(' class1  class2 ')); // Trim and reduce spaces
        $this->assertEquals('class-one', $this->admin_instance->sanitize_table_classes('class-one!@#$%^&*()')); // Invalid chars removed
        $this->assertEquals('', $this->admin_instance->sanitize_table_classes('!@#$%'));
    }

    // Test hook registrations (conceptual - checks if actions are added)
    public function test_admin_hooks_are_registered() {
        // Check if admin_menu action is hooked
        $this->assertGreaterThan(0, has_action('admin_menu', array($this->admin_instance, 'admin_menu')));
        // Check if admin_init action is hooked for settings
        $this->assertGreaterThan(0, has_action('admin_init', array($this->admin_instance, 'register_settings')));
        // Check admin_post actions
        $this->assertGreaterThan(0, has_action('admin_post_gscu_manual_sync', array($this->admin_instance, 'handle_manual_sync')));
        $this->assertGreaterThan(0, has_action('admin_post_gscu_clear_logs', array($this->admin_instance, 'handle_clear_logs')));

        $plugin_basename = plugin_basename( GSCU_PLUGIN_FILE );
        $this->assertGreaterThan(0, has_filter("plugin_action_links_{$plugin_basename}", array($this->admin_instance, 'add_settings_link')));
    }

    // Test settings registration (more of an integration test, but we can check if sections/fields are added)
    public function test_settings_are_registered() {
        // This requires WordPress's admin settings API to be fully loaded and processed.
        // We call register_settings directly to ensure it runs.
        $this->admin_instance->register_settings();

        global $wp_settings_sections, $wp_settings_fields;

        // Check if sections are added to the 'gscu-settings' page
        $this->assertArrayHasKey('gscu-settings', $wp_settings_sections);
        $this->assertArrayHasKey('gscu_general_settings_section', $wp_settings_sections['gscu-settings']);
        $this->assertArrayHasKey('gscu_mapping_settings_section', $wp_settings_sections['gscu-settings']);
        $this->assertArrayHasKey('gscu_appearance_settings_section', $wp_settings_sections['gscu-settings']);
        $this->assertArrayHasKey('gscu_sync_section', $wp_settings_sections['gscu-settings']);

        // Check if a few specific fields are added to their respective sections
        $this->assertArrayHasKey('gscu_google_sheet_url_field', $wp_settings_fields['gscu-settings']['gscu_general_settings_section']);
        $this->assertArrayHasKey('gscu_unique_id_column_field', $wp_settings_fields['gscu-settings']['gscu_mapping_settings_section']);
        $this->assertArrayHasKey('gscu_default_table_classes_field', $wp_settings_fields['gscu-settings']['gscu_appearance_settings_section']);

        // This doesn't test if register_setting itself worked for option saving,
        // but that's a core WP function. We test that our setup for it is present.
    }

    // Note: Testing methods like render_settings_page, handle_manual_sync, handle_clear_logs
    // directly is more complex as they involve HTML output, redirects, nonce checks, and user capabilities.
    // These are often better covered by integration or end-to-end tests.
    // However, their sub-components (like sanitization, calls to GSCU_Sync methods) can be unit tested.
}
