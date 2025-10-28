<?php
require_once 'src/wp-load.php';
require_once 'src/wp-content/plugins/seo-links/seo-links.php';

echo "Plugin loaded.\n";

$posts = get_posts( array( 'post_type' => array( 'post', 'page' ), 'numberposts' => -1 ) );
if ( $posts ) {
    $post = $posts[0];
    $keyword = seolinks_get_focus_keyword( $post->ID );
    echo "Keyword for post " . $post->ID . ": " . $keyword . "\n";
}
