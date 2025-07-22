<?php
/**
 * Plugin Name: Get Us Listed Keyword Linker
 * Description: A plugin to manage internal and external links for SEO.
 * Version: 1.0
 * Author: Get Us Listed LLC, Heath Harris
 * Author URI: https://www.getuslisted.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

function gulkl_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gulkl_actions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        action varchar(255) NOT NULL,
        post_id mediumint(9) NOT NULL,
        opportunity_id mediumint(9) NOT NULL,
        keyword varchar(255) NOT NULL,
        link varchar(255) NOT NULL,
        status varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'gulkl_activate' );

function gulkl_admin_menu() {
    add_menu_page(
        'Get Us Listed Keyword Linker',
        'Keyword Linker',
        'manage_options',
        'get-us-listed-keyword-linker',
        'gulkl_admin_page',
        'dashicons-admin-links'
    );
    add_submenu_page(
        'get-us-listed-keyword-linker',
        'Settings',
        'Settings',
        'manage_options',
        'gulkl-settings',
        'gulkl_settings_page'
    );
}
add_action( 'admin_menu', 'gulkl_admin_menu' );

function gulkl_enqueue_scripts( $hook ) {
    if ( 'toplevel_page_get-us-listed-keyword-linker' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'gulkl-css', plugins_url( 'seo-links.css', __FILE__ ), array(), '1.0' );
    wp_enqueue_script( 'gulkl-js', plugins_url( 'seo-links.js', __FILE__ ), array( 'jquery' ), '1.0', true );
    wp_localize_script( 'gulkl-js', 'gulkl_ajax', array( 'nonce' => wp_create_nonce( 'gulkl-ajax-nonce' ) ) );
}
add_action( 'admin_enqueue_scripts', 'gulkl_enqueue_scripts' );

add_action( 'wp_ajax_gulkl_create_link', 'gulkl_create_link_callback' );
add_action( 'wp_ajax_gulkl_dismiss_link', 'gulkl_dismiss_link_callback' );
add_action( 'wp_ajax_gulkl_bulk_create_links', 'gulkl_bulk_create_links_callback' );
add_action( 'wp_ajax_gulkl_bulk_create_external_links', 'gulkl_bulk_create_external_links_callback' );
add_action( 'wp_ajax_gulkl_save_keyword', 'gulkl_save_keyword_callback' );
add_action( 'wp_ajax_gulkl_scan_for_broken_links', 'gulkl_scan_for_broken_links_callback' );
add_action( 'wp_ajax_gulkl_undo_action', 'gulkl_undo_action_callback' );

function gulkl_clear_cache() {
    delete_transient( 'gulkl_posts_post' );
    delete_transient( 'gulkl_posts_page' );
}
add_action( 'save_post', 'gulkl_clear_cache' );

function gulkl_get_focus_keyword( $post_id ) {
    $keyword = '';
    if ( class_exists( 'WPSEO_Meta' ) ) {
        $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
    } elseif ( class_exists( 'RankMath' ) ) {
        $keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
    }
    return $keyword;
}

function gulkl_find_link_opportunities( $post_id, $keyword ) {
    $opportunities = array();
    if ( empty( $keyword ) ) {
        return $opportunities;
    }

    $upload_dir = wp_upload_dir();
    $json_file = $upload_dir['basedir'] . '/seo-links-data.json';
    $data = json_decode( file_get_contents( $json_file ), true );
    $dismissed = isset( $data['dismissed'][ $post_id ] ) ? $data['dismissed'][ $post_id ] : array();

    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1, 'exclude' => array_merge( array( $post_id ), $dismissed ) ) );
    foreach ( $posts as $post ) {
        if ( stripos( $post->post_content, $keyword ) !== false ) {
            $opportunities[] = $post;
        }
    }
    return $opportunities;
}

function gulkl_admin_page() {
    ?>
    <div class="wrap gulkl-wrap">
        <h1>Get Us Listed Keyword Linker</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=get-us-listed-keyword-linker&tab=pages" class="nav-tab <?php echo ( ! isset( $_GET['tab'] ) || $_GET['tab'] === 'pages' ) ? 'nav-tab-active' : ''; ?>">Pages</a>
            <a href="?page=get-us-listed-keyword-linker&tab=posts" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'posts' ) ? 'nav-tab-active' : ''; ?>">Posts</a>
            <a href="?page=get-us-listed-keyword-linker&tab=same_keyword" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'same_keyword' ) ? 'nav-tab-active' : ''; ?>">Same Keyword</a>
            <a href="?page=get-us-listed-keyword-linker&tab=no_keyword" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'no_keyword' ) ? 'nav-tab-active' : ''; ?>">No Keyword</a>
            <a href="?page=get-us-listed-keyword-linker&tab=external_links" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'external_links' ) ? 'nav-tab-active' : ''; ?>">External Links</a>
            <a href="?page=get-us-listed-keyword-linker&tab=broken_links" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'broken_links' ) ? 'nav-tab-active' : ''; ?>">Broken Links</a>
            <a href="?page=get-us-listed-keyword-linker&tab=404_errors" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === '404_errors' ) ? 'nav-tab-active' : ''; ?>">404 Errors</a>
            <a href="?page=get-us-listed-keyword-linker&tab=action_log" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'action_log' ) ? 'nav-tab-active' : ''; ?>">Action Log</a>
            <a href="?page=get-us-listed-keyword-linker&tab=real_time_log" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'real_time_log' ) ? 'nav-tab-active' : ''; ?>">Real-Time Log</a>
        </h2>

        <?php
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'pages';
        if ( $tab === 'pages' ) {
            gulkl_display_post_type_table( 'page' );
        } elseif ( $tab === 'posts' ) {
            gulkl_display_post_type_table( 'post' );
        } elseif ( $tab === 'same_keyword' ) {
            gulkl_display_same_keyword_table();
        } elseif ( $tab === 'no_keyword' ) {
            gulkl_display_no_keyword_table();
        } elseif ( $tab === 'external_links' ) {
            gulkl_display_external_links_page();
        } elseif ( $tab === 'broken_links' ) {
            gulkl_display_broken_links_page();
        } elseif ( $tab === '404_errors' ) {
            gulkl_display_404_errors_page();
        } elseif ( $tab === 'action_log' ) {
            gulkl_display_action_log_page();
        } elseif ( $tab === 'real_time_log' ) {
            gulkl_display_real_time_log_page();
        }
        ?>
    </div>
    <?php
}

function gulkl_display_post_type_table( $post_type ) {
    $posts = get_posts( array( 'post_type' => $post_type, 'numberposts' => -1 ) );
    usort( $posts, function( $a, $b ) {
        $a_opportunities = count( gulkl_find_link_opportunities( $a->ID, gulkl_get_focus_keyword( $a->ID ) ) );
        $b_opportunities = count( gulkl_find_link_opportunities( $b->ID, gulkl_get_focus_keyword( $b->ID ) ) );
        return $b_opportunities - $a_opportunities;
    } );
    if ( $posts ) {
        ?>
        <div>
            <button class="button-primary" id="add-to-first-5">Add to first 5</button>
            <button class="button-primary" id="add-to-first-10">Add to first 10</button>
            <form method="post" action="" style="display:inline-block; float:right;">
                <input type="hidden" name="gulkl_export_csv" value="<?php echo esc_attr( $post_type ); ?>">
                <input type="submit" class="button-primary" value="Export to CSV">
            </form>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Keyword</th>
                    <th>Keyword Density</th>
                    <th>Backlinks</th>
                    <th>Opportunities</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $posts as $post ) {
                    $keyword = gulkl_get_focus_keyword( $post->ID );
                    $opportunities = gulkl_find_link_opportunities( $post->ID, $keyword );
                    $backlinks = 0;
                    if ( ! empty( $post->post_content ) ) {
                        $backlinks = substr_count( $post->post_content, get_permalink( $post->ID ) );
                    }
                    $keyword_density = gulkl_calculate_keyword_density( $post->post_content, $keyword );
                    ?>
                    <div class="gulkl-row">
                        <div class="gulkl-main-row">
                            <div class="gulkl-title-col">
                                <?php echo esc_html( $post->post_title ); ?>
                                <div class="row-actions">
                                    <a href="<?php echo get_edit_post_link( $post->ID ); ?>">Edit</a> |
                                    <a href="<?php echo get_permalink( $post->ID ); ?>">View</a>
                                </div>
                            </div>
                            <div class="gulkl-keyword-col"><?php echo esc_html( $keyword ); ?></div>
                            <div class="gulkl-density-col">
                                <?php echo esc_html( $keyword_density ); ?>%
                                <span class="dashicons dashicons-editor-help" title="Keyword density is the percentage of times a keyword or phrase appears on a web page compared to the total number of words on the page."></span>
                            </div>
                            <div class="gulkl-backlinks-col"><?php echo esc_html( $backlinks ); ?></div>
                            <div class="gulkl-opportunities-col">
                                <?php if ( ! empty( $opportunities ) ) : ?>
                                    <a href="#" class="gulkl-toggle-opportunities"><span class="dashicons dashicons-plus"></span> Backlink Opportunities</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ( ! empty( $opportunities ) ) : ?>
                            <div class="gulkl-opportunities" style="display:none;">
                                <ul>
                                    <?php foreach ( $opportunities as $opportunity ) : ?>
                                        <li data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-opportunity-id="<?php echo esc_attr( $opportunity->ID ); ?>" class="<?php echo ( strpos( $opportunity->post_content, get_permalink( $post->ID ) ) !== false ) ? 'active' : 'inactive'; ?>">
                                            <?php echo esc_html( $opportunity->post_title ); ?>
                                            <div class="opportunity-actions">
                                                <button class="button-primary">Yes</button>
                                                <button class="button-secondary">No</button>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
    } else {
        echo '<p>No ' . esc_html( $post_type ) . 's found.</p>';
    }
}

function gulkl_calculate_keyword_density( $content, $keyword ) {
    if ( empty( $content ) || empty( $keyword ) ) {
        return 0;
    }

    $word_count = str_word_count( strip_tags( $content ) );
    $keyword_count = substr_count( strtolower( strip_tags( $content ) ), strtolower( $keyword ) );

    if ( $word_count === 0 ) {
        return 0;
    }

    return round( ( $keyword_count / $word_count ) * 100, 2 );
}

function gulkl_display_same_keyword_table() {
    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
    $keywords = array();
    foreach ( $posts as $post ) {
        $keyword = gulkl_get_focus_keyword( $post->ID );
        if ( ! empty( $keyword ) ) {
            if ( ! isset( $keywords[ $keyword ] ) ) {
                $keywords[ $keyword ] = array();
            }
            $keywords[ $keyword ][] = $post;
        }
    }

    foreach ( $keywords as $keyword => $posts ) {
        if ( count( $posts ) > 1 ) {
            ?>
            <h3><?php echo esc_html( $keyword ); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ( $posts as $post ) {
                        ?>
                        <tr>
                            <td><?php echo esc_html( $post->post_title ); ?></td>
                            <td>
                                <input type="text" value="<?php echo esc_attr( $keyword ); ?>" />
                                <button class="button-primary save-keyword" data-post-id="<?php echo esc_attr( $post->ID ); ?>">Save</button>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
            <?php
        }
    }
}

function gulkl_display_no_keyword_table() {
    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
    $no_keyword_posts = array();
    foreach ( $posts as $post ) {
        $keyword = gulkl_get_focus_keyword( $post->ID );
        if ( empty( $keyword ) ) {
            $no_keyword_posts[] = $post;
        }
    }

    if ( ! empty( $no_keyword_posts ) ) {
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Title</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ( $no_keyword_posts as $post ) {
                    ?>
                    <tr>
                        <td><?php echo esc_html( $post->post_title ); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
    } else {
        echo '<p>No posts or pages with no keyword found.</p>';
    }
}

function gulkl_log_404_errors() {
    if ( is_404() ) {
        $errors = get_option( 'gulkl_404_errors', array() );
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        if ( ! isset( $errors[ $url ] ) ) {
            $errors[ $url ] = 0;
        }
        $errors[ $url ]++;
        update_option( 'gulkl_404_errors', $errors );
    }
}
add_action( 'template_redirect', 'gulkl_log_404_errors' );

function gulkl_display_404_errors_page() {
    $errors = get_option( 'gulkl_404_errors', array() );
    ?>
    <div class="wrap">
        <h2>404 Errors</h2>
        <?php if ( ! empty( $errors ) ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>URL</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $errors as $url => $count ) : ?>
                        <tr>
                            <td><?php echo esc_html( $url ); ?></td>
                            <td><?php echo esc_html( $count ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No 404 errors found.</p>
        <?php endif; ?>
    </div>
    <?php
}

function gulkl_display_external_links_page() {
    ?>
    <div class="wrap">
        <h2>External Links</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'gulkl_external_link' ); ?>
            <input type="text" name="keyword" placeholder="Keyword">
            <input type="text" name="url" placeholder="URL">
            <input type="submit" name="find_opportunities" class="button-primary" value="Find Opportunities">
        </form>
    </div>
    <?php
}

function gulkl_handle_external_link_form() {
    if ( isset( $_POST['find_opportunities'] ) ) {
        check_admin_referer( 'gulkl_external_link' );
        $keyword = sanitize_text_field( $_POST['keyword'] );
        $url = esc_url_raw( $_POST['url'] );

        if ( ! empty( $keyword ) && ! empty( $url ) ) {
            $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
            $opportunities = array();
            foreach ( $posts as $post ) {
                if ( stripos( $post->post_content, $keyword ) !== false ) {
                    $opportunities[] = $post;
                }
            }

            if ( ! empty( $opportunities ) ) {
                ?>
                <h3>Opportunities for "<?php echo esc_html( $keyword ); ?>"</h3>
                <div>
                    <button class="button-primary" id="add-all-external">Add All</button>
                    <button class="button-primary" id="add-5-external">Add 5</button>
                    <button class="button-primary" id="add-10-external">Add 10</button>
                </div>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ( $opportunities as $opportunity ) {
                            ?>
                            <tr data-opportunity-id="<?php echo esc_attr( $opportunity->ID ); ?>">
                                <td><?php echo esc_html( $opportunity->post_title ); ?></td>
                                <td>
                                    <button class="button-primary">Yes</button>
                                    <button class="button-secondary">No</button>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
                <?php
            } else {
                echo '<p>No opportunities found for "' . esc_html( $keyword ) . '".</p>';
            }
        }
    }
}
add_action( 'admin_init', 'gulkl_handle_external_link_form' );

function gulkl_export_csv() {
    if ( isset( $_POST['gulkl_export_csv'] ) ) {
        $post_type = sanitize_text_field( $_POST['gulkl_export_csv'] );
        $posts = get_posts( array( 'post_type' => $post_type, 'numberposts' => -1 ) );

        if ( $posts ) {
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=' . $post_type . '.csv' );

            $output = fopen( 'php://output', 'w' );
            fputcsv( $output, array( 'Title', 'Keyword', 'Keyword Density', 'Backlinks' ) );

            foreach ( $posts as $post ) {
                $keyword = gulkl_get_focus_keyword( $post->ID );
                $keyword_density = gulkl_calculate_keyword_density( $post->post_content, $keyword );
                $backlinks = 0;
                if ( ! empty( $post->post_content ) ) {
                    $backlinks = substr_count( $post->post_content, get_permalink( $post->ID ) );
                }
                fputcsv( $output, array( $post->post_title, $keyword, $keyword_density, $backlinks ) );
            }
            fclose( $output );
            exit;
        }
    }
}
add_action( 'admin_init', 'gulkl_export_csv' );

function gulkl_update_json_on_new_post( $post_id, $post ) {
    if ( $post->post_status === 'publish' ) {
        // Add admin notification.
        add_action( 'admin_notices', function() use ( $post ) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>New post with backlink opportunities: <?php echo esc_html( $post->post_title ); ?></p>
            </div>
            <?php
        } );
    }
}
add_action( 'wp_insert_post', 'gulkl_update_json_on_new_post', 10, 2 );

function gulkl_create_link_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $opportunity_id = isset( $_POST['opportunity_id'] ) ? intval( $_POST['opportunity_id'] ) : 0;

    if ( ! current_user_can( 'edit_post', $opportunity_id ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to edit this post.' ) );
    }

    if ( $post_id && $opportunity_id ) {
        $keyword = gulkl_get_focus_keyword( $post_id );
        $link = get_permalink( $post_id );
        global $wpdb;
        $table_name = $wpdb->prefix . 'gulkl_actions';
        $wpdb->insert(
            $table_name,
            array(
                'action' => 'create_link',
                'post_id' => $post_id,
                'opportunity_id' => $opportunity_id,
                'keyword' => $keyword,
                'link' => $link,
                'status' => 'pending',
            )
        );
        wp_send_json_success( array( 'message' => 'Action added to queue.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}

function gulkl_process_action_queue() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gulkl_actions';
    $actions = $wpdb->get_results( "SELECT * FROM $table_name WHERE status = 'pending' LIMIT 1" );

    if ( ! empty( $actions ) ) {
        $action = $actions[0];
        if ( $action->action === 'create_link' ) {
            $post = get_post( $action->post_id );
            $opportunity = get_post( $action->opportunity_id );
            $keyword = gulkl_get_focus_keyword( $action->post_id );
            $link = get_permalink( $action->post_id );

            if ( strpos( $opportunity->post_content, $link ) === false ) {
                $new_content = preg_replace( '/' . preg_quote( $keyword, '/' ) . '/', '<a href="' . esc_url( $link ) . '">' . esc_html( $keyword ) . '</a>', $opportunity->post_content, 1 );
                wp_update_post( array(
                    'ID' => $action->opportunity_id,
                    'post_content' => $new_content,
                ) );
            }
        }

        $wpdb->update(
            $table_name,
            array( 'status' => 'completed' ),
            array( 'id' => $action->id )
        );

        wp_send_json_success( array( 'message' => 'Processed action ' . $action->id ) );
    } else {
        wp_send_json_success( array( 'message' => '' ) );
    }
}
add_action( 'wp_ajax_gulkl_process_action_queue', 'gulkl_process_action_queue' );

if ( ! wp_next_scheduled( 'gulkl_process_action_queue_event' ) ) {
    wp_schedule_event( time(), 'hourly', 'gulkl_process_action_queue_event' );
}

function gulkl_dismiss_link_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $opportunity_id = isset( $_POST['opportunity_id'] ) ? intval( $_POST['opportunity_id'] ) : 0;

    if ( $post_id && $opportunity_id ) {
        $upload_dir = wp_upload_dir();
        $json_file = $upload_dir['basedir'] . '/seo-links-data.json';
        $data = json_decode( file_get_contents( $json_file ), true );

        if ( ! isset( $data['dismissed'] ) ) {
            $data['dismissed'] = array();
        }

        if ( ! isset( $data['dismissed'][ $post_id ] ) ) {
            $data['dismissed'][ $post_id ] = array();
        }

        $data['dismissed'][ $post_id ][] = $opportunity_id;
        if ( file_put_contents( $json_file, json_encode( $data ) ) ) {
            wp_send_json_success( array( 'message' => 'Link dismissed.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Error saving data.' ) );
        }
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}

function gulkl_bulk_create_links_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 0;
    if ( $limit > 0 ) {
        $exempted_pages = get_option( 'gulkl_exempted_pages', array() );
        $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1, 'exclude' => $exempted_pages ) );
        foreach ( $posts as $post ) {
            $keyword = gulkl_get_focus_keyword( $post->ID );
            $opportunities = gulkl_find_link_opportunities( $post->ID, $keyword );
            $opportunities = array_slice( $opportunities, 0, $limit );
            foreach ( $opportunities as $opportunity ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'gulkl_actions';
                $wpdb->insert(
                    $table_name,
                    array(
                        'action' => 'create_link',
                        'post_id' => $post->ID,
                        'opportunity_id' => $opportunity->ID,
                        'status' => 'pending',
                    )
                );
            }
        }
        wp_send_json_success( array( 'redirect_url' => admin_url( 'admin.php?page=get-us-listed-keyword-linker&tab=real_time_log' ) ) );
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}

function gulkl_save_keyword_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to edit this post.' ) );
    }

    if ( $post_id && ! empty( $keyword ) ) {
        if ( class_exists( 'WPSEO_Meta' ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', $keyword );
        } elseif ( class_exists( 'RankMath' ) ) {
            update_post_meta( $post_id, 'rank_math_focus_keyword', $keyword );
        }
        wp_send_json_success( array( 'message' => 'Keyword saved.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}

function gulkl_bulk_create_external_links_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';
    $url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
    $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 0;
    $opportunity_ids = isset( $_POST['opportunity_ids'] ) ? array_map( 'intval', $_POST['opportunity_ids'] ) : array();

    if ( ! empty( $keyword ) && ! empty( $url ) && ! empty( $opportunity_ids ) ) {
        if ( $limit > 0 ) {
            $opportunity_ids = array_slice( $opportunity_ids, 0, $limit );
        }

        foreach ( $opportunity_ids as $opportunity_id ) {
            $opportunity = get_post( $opportunity_id );
            $new_content = preg_replace( '/' . preg_quote( $keyword, '/' ) . '/', '<a href="' . esc_url( $url ) . '">' . esc_html( $keyword ) . '</a>', $opportunity->post_content, 1 );
            wp_update_post( array(
                'ID' => $opportunity_id,
                'post_content' => $new_content,
            ) );
        }
        wp_send_json_success( array( 'message' => 'Bulk links created.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}

function gulkl_undo_action_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $action_id = isset( $_POST['action_id'] ) ? intval( $_POST['action_id'] ) : 0;
    if ( ! $action_id ) {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'gulkl_actions';
    $action = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $action_id ) );

    if ( $action ) {
        if ( $action->action === 'create_link' ) {
            $post = get_post( $action->post_id );
            $opportunity = get_post( $action->opportunity_id );
            $keyword = gulkl_get_focus_keyword( $action->post_id );
            $link = get_permalink( $action->post_id );

            $new_content = str_replace( '<a href="' . esc_url( $link ) . '">' . esc_html( $keyword ) . '</a>', esc_html( $keyword ), $opportunity->post_content );
            wp_update_post( array(
                'ID' => $action->opportunity_id,
                'post_content' => $new_content,
            ) );
        }

        $wpdb->update(
            $table_name,
            array( 'status' => 'undone' ),
            array( 'id' => $action_id )
        );

        wp_send_json_success( array( 'message' => 'Action undone.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Action not found.' ) );
    }
}

function gulkl_settings_page() {
    if ( isset( $_POST['gulkl_save_settings'] ) ) {
        check_admin_referer( 'gulkl_settings' );
        $exempted_pages = isset( $_POST['exempted_pages'] ) ? array_map( 'intval', $_POST['exempted_pages'] ) : array();
        update_option( 'gulkl_exempted_pages', $exempted_pages );
        ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings saved.</p>
        </div>
        <?php
    }
    $exempted_pages = get_option( 'gulkl_exempted_pages', array() );
    ?>
    <div class="wrap">
        <h2>Settings</h2>
        <form method="post" action="">
            <h3>Exempted Pages</h3>
            <p>Select the pages you want to exempt from the bulk add functionality.</p>
            <select name="exempted_pages[]" multiple style="width:100%;height:200px;">
                <?php
                $pages = get_pages();
                foreach ( $pages as $page ) {
                    ?>
                    <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( in_array( $page->ID, $exempted_pages ) ); ?>><?php echo esc_html( $page->post_title ); ?></option>
                    <?php
                }
                ?>
            </select>
            <?php wp_nonce_field( 'gulkl_settings' ); ?>
            <p class="submit">
                <input type="submit" name="gulkl_save_settings" class="button-primary" value="Save Changes">
            </p>
        </form>
    </div>
    <?php
}

function gulkl_display_real_time_log_page() {
    ?>
    <div class="wrap">
        <h2>Real-Time Action Log</h2>
        <div id="real-time-log"></div>
    </div>
    <?php
}

function gulkl_display_action_log_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gulkl_actions';
    $actions = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY id DESC" );
    ?>
    <div class="wrap">
        <h2>Action Log</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Action</th>
                    <th>Post</th>
                    <th>Opportunity</th>
                    <th>Keyword</th>
                    <th>Link</th>
                    <th>Status</th>
                    <th>Undo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $actions as $action ) : ?>
                    <tr>
                        <td><?php echo esc_html( $action->id ); ?></td>
                        <td><?php echo esc_html( $action->action ); ?></td>
                        <td><a href="<?php echo get_edit_post_link( $action->post_id ); ?>"><?php echo esc_html( get_the_title( $action->post_id ) ); ?></a></td>
                        <td><a href="<?php echo get_edit_post_link( $action->opportunity_id ); ?>"><?php echo esc_html( get_the_title( $action->opportunity_id ) ); ?></a></td>
                        <td><?php echo esc_html( $action->keyword ); ?></td>
                        <td><?php echo esc_html( $action->link ); ?></td>
                        <td><?php echo esc_html( $action->status ); ?></td>
                        <td>
                            <?php if ( $action->status === 'completed' ) : ?>
                                <button class="button-secondary gulkl-undo-action" data-action-id="<?php echo esc_attr( $action->id ); ?>">Undo</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function gulkl_scan_for_broken_links_callback() {
    check_ajax_referer( 'gulkl-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }

    $post = get_post( $post_id );
    $broken_links = array();

    $content = $post->post_content;
    preg_match_all( '/<a\s[^>]*href=([\"\']??)([^\" >]*?)\\1[^>]*>(.*)<\/a>/siU', $content, $matches );

    if ( ! empty( $matches[2] ) ) {
        foreach ( $matches[2] as $link ) {
            $response = wp_remote_head( $link, array( 'timeout' => 5 ) );
            if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
                $broken_links[] = array(
                    'post_id' => $post->ID,
                    'post_title' => $post->post_title,
                    'link' => $link,
                );
            }
        }
    }

    wp_send_json_success( array( 'broken_links' => $broken_links ) );
}

function gulkl_display_broken_links_page() {
    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1, 'fields' => 'ids' ) );
    ?>
    <div class="wrap">
        <h2>Broken Links</h2>
        <button class="button-primary" id="scan-for-broken-links" data-posts="<?php echo esc_attr( json_encode( $posts ) ); ?>">Scan for Broken Links</button>
        <div id="broken-links-results"></div>
    </div>
    <?php
}
