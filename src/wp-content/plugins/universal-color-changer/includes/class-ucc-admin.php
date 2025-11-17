<?php
/**
 * Admin UI handler.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Admin class.
 */
class Admin {
/**
 * Page slug.
 *
 * @var string
 */
const PAGE_SLUG = 'universal-color-changer';

/**
 * Initialize hooks.
 */
public static function init() {
add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
}

/**
 * Register admin menu.
 */
public static function register_menu() {
add_management_page(
esc_html__( 'Universal Color Changer', 'universal-color-changer' ),
esc_html__( 'Universal Color Changer', 'universal-color-changer' ),
'manage_options',
self::PAGE_SLUG,
array( __CLASS__, 'render_page' )
);
}

/**
 * Enqueue admin assets.
 *
 * @param string $hook Hook suffix.
 */
public static function enqueue_assets( $hook ) {
if ( 'tools_page_' . self::PAGE_SLUG !== $hook ) {
return;
}

wp_enqueue_style(
'ucc-admin',
UCC_PLUGIN_URL . 'assets/admin.css',
array(),
UCC_PLUGIN_VERSION
);

wp_enqueue_script(
'ucc-admin',
UCC_PLUGIN_URL . 'assets/admin.js',
array( 'wp-element', 'wp-api-fetch' ),
UCC_PLUGIN_VERSION,
true
);

wp_localize_script(
'ucc-admin',
'uccAdmin',
array(
'root'      => esc_url_raw( rest_url( REST_Controller::REST_NAMESPACE ) ),
'nonce'     => wp_create_nonce( REST_Controller::REST_NAMESPACE ),
'i18n'      => array(
'heading'              => esc_html__( 'Universal Color Changer', 'universal-color-changer' ),
'description'          => esc_html__( 'Scan your site for literal color values and replace them safely.', 'universal-color-changer' ),
'indexButton'          => esc_html__( 'Re-index Content', 'universal-color-changer' ),
'byColor'              => esc_html__( 'By Color', 'universal-color-changer' ),
'byPost'               => esc_html__( 'By Page/Post', 'universal-color-changer' ),
'history'              => esc_html__( 'History', 'universal-color-changer' ),
'dryRun'               => esc_html__( 'Preview Changes', 'universal-color-changer' ),
'apply'                => esc_html__( 'Apply Changes', 'universal-color-changer' ),
'undo'                 => esc_html__( 'Undo', 'universal-color-changer' ),
'undoAll'              => esc_html__( 'Undo All', 'universal-color-changer' ),
'noData'               => esc_html__( 'No data available yet. Run an index.', 'universal-color-changer' ),
'noMatches'            => esc_html__( 'No matches to apply. Run a preview first.', 'universal-color-changer' ),
'dashboard'            => esc_html__( 'Dashboard', 'universal-color-changer' ),
'occurrencesLabel'     => esc_html__( 'Occurrences', 'universal-color-changer' ),
'colorsLabel'          => esc_html__( 'Colors', 'universal-color-changer' ),
'lastIndexedLabel'     => esc_html__( 'Last Indexed', 'universal-color-changer' ),
'scanTitles'           => esc_html__( 'Scan Titles', 'universal-color-changer' ),
'scanExcerpts'         => esc_html__( 'Scan Excerpts', 'universal-color-changer' ),
'postMetaMode'         => esc_html__( 'Post Meta Mode', 'universal-color-changer' ),
'postMetaAllowlist'    => esc_html__( 'Allowlist', 'universal-color-changer' ),
'postMetaAll'          => esc_html__( 'All', 'universal-color-changer' ),
'indexing'             => esc_html__( 'Indexing…', 'universal-color-changer' ),
'indexingProgress'     => esc_html__( 'Indexing in progress…', 'universal-color-changer' ),
'postIdPlaceholder'    => esc_html__( 'Post ID', 'universal-color-changer' ),
'fetch'                => esc_html__( 'Fetch', 'universal-color-changer' ),
'historyId'            => esc_html__( 'ID', 'universal-color-changer' ),
'historySource'        => esc_html__( 'Source', 'universal-color-changer' ),
'historyTarget'        => esc_html__( 'Target', 'universal-color-changer' ),
'historyItems'         => esc_html__( 'Items', 'universal-color-changer' ),
'historyStatus'        => esc_html__( 'Status', 'universal-color-changer' ),
'historyActions'       => esc_html__( 'Actions', 'universal-color-changer' ),
'replacement'          => esc_html__( 'Replacement', 'universal-color-changer' ),
'replacementMatches'   => esc_html__( 'Matches:', 'universal-color-changer' ),
'replacementRemaining' => esc_html__( 'Remaining fields:', 'universal-color-changer' ),
'dryRunFailed'         => esc_html__( 'Dry run failed', 'universal-color-changer' ),
'sourcePlaceholder'    => esc_html__( 'Source color (#000000 or rgb(...))', 'universal-color-changer' ),
'targetPlaceholder'    => esc_html__( 'Target color (#ffffff or rgba(...))', 'universal-color-changer' ),
),
)
);
}

/**
 * Render admin page.
 */
public static function render_page() {
if ( ! current_user_can( 'manage_options' ) ) {
return;
}

echo '<div class="wrap" id="ucc-admin-app">';
echo '<h1>' . esc_html__( 'Universal Color Changer', 'universal-color-changer' ) . '</h1>';
echo '<div class="ucc-app"></div>';
echo '</div>';
}
}
