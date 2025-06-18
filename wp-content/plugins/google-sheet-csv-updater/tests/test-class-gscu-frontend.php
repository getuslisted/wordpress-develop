<?php
// tests/test-class-gscu-frontend.php

/**
 * Unit tests for GSCU_Frontend class.
 * @package Google_Sheet_CSV_Updater
 */
class Test_GSCU_Frontend extends WP_UnitTestCase {

    private $frontend_instance;
    private static $post_ids = [];

    public static function wpSetUpBeforeClass( $factory ) {
        // Create some posts that the shortcode can display
        self::$post_ids[] = $factory->post->create(array(
            'post_title' => 'Synced Post 1',
            'post_content' => 'Content for post 1.',
            'post_type' => 'post',
            'post_status' => 'publish'
        ));
        update_post_meta(self::$post_ids[0], GSCU_UNIQUE_ID_META_KEY, 'uid1');
        update_post_meta(self::$post_ids[0], 'custom_field_A', 'ValueA1');

        self::$post_ids[] = $factory->post->create(array(
            'post_title' => 'Synced Post 2',
            'post_content' => 'Content for post 2. <b>Bold</b> text.',
            'post_type' => 'post',
            'post_status' => 'publish'
        ));
        update_post_meta(self::$post_ids[1], GSCU_UNIQUE_ID_META_KEY, 'uid2');
        update_post_meta(self::$post_ids[1], 'custom_field_A', 'ValueA2');
        update_post_meta(self::$post_ids[1], 'custom_field_B', 'ValueB2');

         self::$post_ids[] = $factory->post->create(array(
            'post_title' => 'Unsynced Post', // No GSCU_UNIQUE_ID_META_KEY
            'post_content' => 'This post should not appear.',
            'post_type' => 'post',
            'post_status' => 'publish'
        ));
    }

    public static function wpTearDownAfterClass() {
        foreach (self::$post_ids as $post_id) {
            wp_delete_post($post_id, true);
        }
        self::$post_ids = [];
    }


    public function setUp(): void {
        parent::setUp();
        require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-frontend.php';
        $this->frontend_instance = new GSCU_Frontend();

        // Set default plugin options needed by the shortcode
        update_option('gscu_content_type', 'post');
        update_option('gscu_default_posts_per_page', 5);
        update_option('gscu_default_table_classes', 'default-global-class');
        update_option('gscu_predefined_style', 'basic');
    }

    public function tearDown(): void {
        delete_option('gscu_content_type');
        delete_option('gscu_default_posts_per_page');
        delete_option('gscu_default_table_classes');
        delete_option('gscu_predefined_style');
        // Reset query vars for pagination tests
        set_query_var('paged', 0);
        parent::tearDown();
    }

    public function test_shortcode_render_default_attributes() {
        $output = $this->frontend_instance->render_shortcode(array());

        $this->assertStringContainsString('<table class="gscu-table default-global-class">', $output);
        $this->assertStringContainsString('<th>Title</th>', $output);
        $this->assertStringContainsString('<th>Unique ID</th>', $output); // From GSCU_UNIQUE_ID_META_KEY
        $this->assertStringContainsString('Synced Post 1', $output);
        $this->assertStringContainsString('uid1', $output);
        $this->assertStringContainsString('Synced Post 2', $output);
        $this->assertStringContainsString('uid2', $output);
        $this->assertStringNotContainsString('Unsynced Post', $output); // Should not be displayed
    }

    public function test_shortcode_render_custom_columns() {
        $atts = array('columns' => 'title,custom_field_A,post_content');
        $output = $this->frontend_instance->render_shortcode($atts);

        $this->assertStringContainsString('<th>Title</th>', $output);
        $this->assertStringContainsString('<th>Custom Field A</th>', $output);
        $this->assertStringContainsString('<th>Post Content</th>', $output);
        $this->assertStringContainsString('ValueA1', $output);
        $this->assertStringContainsString('Content for post 1.', $output); // wp_trim_words will apply
        $this->assertStringContainsString('ValueA2', $output);
        $this->assertStringContainsString('Content for post 2. Bold text.', $output); // Check if HTML is stripped
    }

    public function test_shortcode_styling_attributes() {
        update_option('gscu_predefined_style', 'striped');
        $atts = array('table_class' => 'my-custom-class another-class');
        $output = $this->frontend_instance->render_shortcode($atts);

        $this->assertStringContainsString('class="gscu-table default-global-class my-custom-class another-class gscu-table-striped"', $output);
    }

    public function test_shortcode_no_items_found() {
        // Change content type to one that has no synced posts
        update_option('gscu_content_type', 'page');
        $output = $this->frontend_instance->render_shortcode(array());
        $this->assertEquals('<p>' . esc_html__( 'No synced items to display.', 'google-sheet-csv-updater' ) . '</p>', $output);
    }

    public function test_shortcode_pagination() {
        update_option('gscu_default_posts_per_page', 1); // 1 post per page to force pagination

        // Simulate being on page 1
        set_query_var('paged', 1);
        $output_page1 = $this->frontend_instance->render_shortcode(array());
        $this->assertStringContainsString('Synced Post 1', $output_page1); // Or whichever is first based on default order
        $this->assertStringNotContainsString('Synced Post 2', $output_page1);
        $this->assertStringContainsString('class="gscu-pagination"', $output_page1);
        $this->assertStringContainsString('page-numbers current">1</span>', $output_page1);
        $this->assertStringContainsString('page/2/', $output_page1); // Link to page 2

        // Simulate being on page 2
        set_query_var('paged', 2);
        $output_page2 = $this->frontend_instance->render_shortcode(array());
        $this->assertStringNotContainsString('Synced Post 1', $output_page2);
        $this->assertStringContainsString('Synced Post 2', $output_page2);
        $this->assertStringContainsString('class="gscu-pagination"', $output_page2);
        $this->assertStringContainsString('page-numbers current">2</span>', $output_page2);
    }

    public function test_enqueue_styles_called_by_shortcode() {
        // Check if style is not enqueued initially
        $this->assertFalse(wp_style_is('gscu-styles', 'enqueued'));

        $this->frontend_instance->render_shortcode(array());

        // Check if style is enqueued after shortcode rendering
        $this->assertTrue(wp_style_is('gscu-styles', 'enqueued'));

        // Clean up for other tests
        wp_dequeue_style('gscu-styles');
    }
}
