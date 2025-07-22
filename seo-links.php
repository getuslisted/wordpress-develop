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

function seolinks_activate() {
    $upload_dir = wp_upload_dir();
    $json_file = $upload_dir['basedir'] . '/seo-links-data.json';

    if ( ! file_exists( $json_file ) ) {
        file_put_contents( $json_file, '[]' );
    }
}
register_activation_hook( __FILE__, 'seolinks_activate' );

function seolinks_admin_menu() {
    add_menu_page(
        'SEO Links',
        'SEO Links',
        'manage_options',
        'seo-links',
        'seolinks_admin_page',
        'dashicons-admin-links'
    );
}
add_action( 'admin_menu', 'seolinks_admin_menu' );

function seolinks_enqueue_scripts( $hook ) {
    if ( 'toplevel_page_seo-links' !== $hook ) {
        return;
    }
    wp_enqueue_style( 'seo-links-css', plugins_url( 'seo-links.css', __FILE__ ), array(), '1.0' );
    wp_enqueue_script( 'seo-links-js', plugins_url( 'seo-links.js', __FILE__ ), array( 'jquery' ), '1.0', true );
    wp_localize_script( 'seo-links-js', 'seolinks_ajax', array( 'nonce' => wp_create_nonce( 'seolinks-ajax-nonce' ) ) );
}
add_action( 'admin_enqueue_scripts', 'seolinks_enqueue_scripts' );

add_action( 'wp_ajax_seolinks_create_link', 'seolinks_create_link_callback' );
add_action( 'wp_ajax_seolinks_dismiss_link', 'seolinks_dismiss_link_callback' );
add_action( 'wp_ajax_seolinks_bulk_create_links', 'seolinks_bulk_create_links_callback' );
add_action( 'wp_ajax_seolinks_bulk_create_external_links', 'seolinks_bulk_create_external_links_callback' );
add_action( 'wp_ajax_seolinks_save_keyword', 'seolinks_save_keyword_callback' );
add_action( 'wp_ajax_seolinks_scan_for_broken_links', 'seolinks_scan_for_broken_links_callback' );

function seolinks_get_focus_keyword( $post_id ) {
    $keyword = '';
    if ( class_exists( 'WPSEO_Meta' ) ) {
        $keyword = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
    } elseif ( class_exists( 'RankMath' ) ) {
        $keyword = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
    }
    return $keyword;
}

function seolinks_find_link_opportunities( $post_id, $keyword ) {
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

function seolinks_admin_page() {
    ?>
    <div class="wrap">
        <h1>Get Us Listed Keyword Linker</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=seo-links&tab=pages" class="nav-tab <?php echo ( ! isset( $_GET['tab'] ) || $_GET['tab'] === 'pages' ) ? 'nav-tab-active' : ''; ?>">Pages</a>
            <a href="?page=seo-links&tab=posts" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'posts' ) ? 'nav-tab-active' : ''; ?>">Posts</a>
            <a href="?page=seo-links&tab=same_keyword" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'same_keyword' ) ? 'nav-tab-active' : ''; ?>">Same Keyword</a>
            <a href="?page=seo-links&tab=no_keyword" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'no_keyword' ) ? 'nav-tab-active' : ''; ?>">No Keyword</a>
            <a href="?page=seo-links&tab=external_links" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'external_links' ) ? 'nav-tab-active' : ''; ?>">External Links</a>
            <a href="?page=seo-links&tab=broken_links" class="nav-tab <?php echo ( isset( $_GET['tab'] ) && $_GET['tab'] === 'broken_links' ) ? 'nav-tab-active' : ''; ?>">Broken Links</a>
        </h2>

        <?php
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'pages';
        if ( $tab === 'pages' ) {
            seolinks_display_post_type_table( 'page' );
        } elseif ( $tab === 'posts' ) {
            seolinks_display_post_type_table( 'post' );
        } elseif ( $tab === 'same_keyword' ) {
            seolinks_display_same_keyword_table();
        } elseif ( $tab === 'no_keyword' ) {
            seolinks_display_no_keyword_table();
        } elseif ( $tab === 'external_links' ) {
            seolinks_display_external_links_page();
        } elseif ( $tab === 'broken_links' ) {
            seolinks_display_broken_links_page();
        }
        ?>
    </div>
    <?php
}

function seolinks_display_post_type_table( $post_type ) {
    $posts = get_posts( array( 'post_type' => $post_type, 'numberposts' => -1 ) );
    if ( $posts ) {
        ?>
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
                    $keyword = seolinks_get_focus_keyword( $post->ID );
                    $opportunities = seolinks_find_link_opportunities( $post->ID, $keyword );
                    $backlinks = 0;
                    if ( ! empty( $post->post_content ) ) {
                        $backlinks = substr_count( $post->post_content, get_permalink( $post->ID ) );
                    }
                    $keyword_density = seolinks_calculate_keyword_density( $post->post_content, $keyword );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $post->post_title ); ?></td>
                        <td><?php echo esc_html( $keyword ); ?></td>
                        <td><?php echo esc_html( $keyword_density ); ?>%</td>
                        <td><?php echo esc_html( $backlinks ); ?></td>
                        <td>
                            <?php if ( ! empty( $opportunities ) ) : ?>
                                <span class="dashicons dashicons-plus"></span>
                                <div class="opportunities" style="display:none;">
                                    <ul>
                                        <?php foreach ( $opportunities as $opportunity ) : ?>
                                            <li data-post-id="<?php echo esc_attr( $post->ID ); ?>" data-opportunity-id="<?php echo esc_attr( $opportunity->ID ); ?>">
                                                <?php echo esc_html( $opportunity->post_title ); ?>
                                                <button class="button-primary">Yes</button>
                                                <button class="button-secondary">No</button>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
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

function seolinks_calculate_keyword_density( $content, $keyword ) {
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

function seolinks_display_same_keyword_table() {
    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
    $keywords = array();
    foreach ( $posts as $post ) {
        $keyword = seolinks_get_focus_keyword( $post->ID );
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
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ( $posts as $post ) {
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
        }
    }
}

function seolinks_display_no_keyword_table() {
    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
    $no_keyword_posts = array();
    foreach ( $posts as $post ) {
        $keyword = seolinks_get_focus_keyword( $post->ID );
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

function seolinks_display_external_links_page() {
    ?>
    <div class="wrap">
        <h2>External Links</h2>
        <form method="post" action="">
            <?php wp_nonce_field( 'seolinks_external_link' ); ?>
            <input type="text" name="keyword" placeholder="Keyword">
            <input type="text" name="url" placeholder="URL">
            <input type="submit" name="find_opportunities" class="button-primary" value="Find Opportunities">
        </form>
    </div>
    <?php
}

function seolinks_handle_external_link_form() {
    if ( isset( $_POST['find_opportunities'] ) ) {
        check_admin_referer( 'seolinks_external_link' );
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
add_action( 'admin_init', 'seolinks_handle_external_link_form' );

function seolinks_update_json_on_new_post( $post_id, $post ) {
    if ( $post->post_status === 'publish' ) {
        $upload_dir = wp_upload_dir();
        $json_file = $upload_dir['basedir'] . '/seo-links-data.json';
        $data = json_decode( file_get_contents( $json_file ), true );

        $new_post_data = array(
            'id' => $post_id,
            'title' => $post->post_title,
            'keyword' => seolinks_get_focus_keyword( $post_id ),
        );

        $data[] = $new_post_data;
        file_put_contents( $json_file, json_encode( $data ) );

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
add_action( 'wp_insert_post', 'seolinks_update_json_on_new_post', 10, 2 );

function seolinks_create_link_callback() {
    check_ajax_referer( 'seolinks-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $opportunity_id = isset( $_POST['opportunity_id'] ) ? intval( $_POST['opportunity_id'] ) : 0;

    if ( $post_id && $opportunity_id ) {
        $post = get_post( $post_id );
        $opportunity = get_post( $opportunity_id );
        $keyword = seolinks_get_focus_keyword( $post_id );
        $link = get_permalink( $post_id );

        $new_content = preg_replace( '/' . preg_quote( $keyword, '/' ) . '/', '<a href="' . esc_url( $link ) . '">' . esc_html( $keyword ) . '</a>', $opportunity->post_content, 1 );
        $result = wp_update_post( array(
            'ID' => $opportunity_id,
            'post_content' => $new_content,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array( 'message' => 'Link created.' ) );
        }
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}

function seolinks_scan_for_broken_links_callback() {
    check_ajax_referer( 'seolinks-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
    $broken_links = array();

    foreach ( $posts as $post ) {
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
    }

    wp_send_json_success( array( 'broken_links' => $broken_links ) );
}

function seolinks_display_broken_links_page() {
    ?>
    <div class="wrap">
        <h2>Broken Links</h2>
        <button class="button-primary" id="scan-for-broken-links">Scan for Broken Links</button>
        <div id="broken-links-results"></div>
    </div>
    <?php
}

function seolinks_save_keyword_callback() {
    check_ajax_referer( 'seolinks-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';

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

function seolinks_bulk_create_external_links_callback() {
    check_ajax_referer( 'seolinks-ajax-nonce', 'nonce' );

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

function seolinks_dismiss_link_callback() {
    check_ajax_referer( 'seolinks-ajax-nonce', 'nonce' );

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

function seolinks_bulk_create_links_callback() {
    check_ajax_referer( 'seolinks-ajax-nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'You do not have permission to perform this action.' ) );
    }

    $limit = isset( $_POST['limit'] ) ? intval( $_POST['limit'] ) : 0;
    if ( $limit > 0 ) {
        $posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
        foreach ( $posts as $post ) {
            $keyword = seolinks_get_focus_keyword( $post->ID );
            $opportunities = seolinks_find_link_opportunities( $post->ID, $keyword );
            $opportunities = array_slice( $opportunities, 0, $limit );
            foreach ( $opportunities as $opportunity ) {
                $link = get_permalink( $post->ID );
                $new_content = preg_replace( '/' . preg_quote( $keyword, '/' ) . '/', '<a href="' . esc_url( $link ) . '">' . esc_html( $keyword ) . '</a>', $opportunity->post_content, 1 );
                wp_update_post( array(
                    'ID' => $opportunity->ID,
                    'post_content' => $new_content,
                ) );
            }
        }
        wp_send_json_success( array( 'message' => 'Bulk links created.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Invalid request.' ) );
    }
}
