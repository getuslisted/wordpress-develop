<?php
require_once 'src/wp-load.php';

$post_data = array(
    'post_title'    => 'Test Post',
    'post_content'  => 'This is a test post with the keyword "contact us".',
    'post_status'   => 'publish',
    'post_author'   => 1,
    'post_type'     => 'post',
);

$post_id = wp_insert_post( $post_data );

if ( $post_id ) {
    echo "Test post created with ID: $post_id";
} else {
    echo "Error creating test post.";
}
