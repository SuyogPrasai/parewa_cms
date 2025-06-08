<?php
/**
 * Plugin Name: Post Sync Plugin
 * Description: Syncs WordPress posts with a Next.js server on publish, update, or delete events.
 * Version: 1.5
 * Author: Suyog Prasai
 * License: GPLv2 or later
 */

// Define constants securely.
define('POST_SYNC_NEXTJS_URL', value: 'http://host.docker.internal:3000/api/post'); // Update this URL as needed.
define('POST_SYNC_API_KEY', '9SGnap5OiEdeGPdxa0BHwnFLqRfZy4YlMAbtGYGGvrcV1VQMHzHzCicMLoYbMfg2YDXskSsYHjqRfoTyUxtWbdlaejNKEhVQWNPZEuxaaNnN5HzWUj5qoO7JQU4GzAS5'); // Store securely, consider using environment variables.

add_action('wp_after_insert_post', 'post_sync_handle_post', 10, 2);
add_action('wp_trash_post', 'post_sync_handle_deletion', 10, 1);
add_action( 'untrash_post', 'post_sync_handle_restore', 10, 1);

/**
 * Handles post publish/update events.
 */
function post_sync_handle_post($post_id, $post): void
{
    error_log(message: "[Post Sync Plugin] post_sync_handle_post triggered for post ID: $post_id with filter: " . current_filter());

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (get_post_type($post_id) == 'news'){

        if ( get_field( 'notice_category', $post_id ) == null ) {
            return;
        }
        $data = [
            'wp_id' => $post_id,
            'title' => get_the_title($post_id),
            'content' => apply_filters('the_content', $post->post_content),
            'publishedIn' => get_the_date('c', $post_id),
            'featuredImage' => get_the_post_thumbnail_url($post_id, 'full') ?: '',
            'publisher_name' => get_the_author_meta('display_name', $post->post_author),
            'postTags' => wp_get_post_tags($post_id, ['fields' => 'names']),
            'category' => get_field( 'notice_category', $post_id ),
            'event' => ($post->post_date_gmt === $post->post_modified_gmt) ? 'published' : 'modified',
            'type' => get_post_type($post_id),
        ];

    } elseif ( get_post_type($post_id) == 'article'){
        
        if ( get_field( 'article_category', $post_id ) == null ) {
            return;
        }
        $data = [
            'wp_id' => $post_id,
            'title' => get_the_title($post_id),
            'oneLiner' => get_field( 'one_liner', $post_id ),
            'content' => apply_filters('the_content', $post->post_content),
            'publishedIn' => get_the_date('c', $post_id),
            'featuredImage' => get_the_post_thumbnail_url($post_id, 'full') ?: '',
            'publisher_name' => get_the_author_meta('display_name', $post->post_author),
            'postTags' => wp_get_post_tags($post_id, ['fields' => 'names']),
            'category' => get_field( 'article_category', $post_id ),
            'author_name' => get_field( 'author', $post_id ),
            'event' => ($post->post_date_gmt === $post->post_modified_gmt) ? 'published' : 'modified',
            'type' => get_post_type($post_id),
        ];

    } else {
        error_log(message: "[Post Sync Plugin] Invalid post type: " . get_post_type($post_id));
        return;
    }


    post_sync_send_request($data);
}

function post_sync_handle_restore($post_id): void
{
    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return;
    }
    error_log(message: "[Post Sync Plugin] post restore triggered for post ID: $post_id with filter: " . current_filter());

    $data = [
        'wp_id' => $post_id,
        'event' => 'post_restore',
        'type' => get_post_type($post_id),
        'publisher' => get_the_author_meta('display_name', $post->post_author),
    ];
    error_log("[Post Sync Plugin] Sending restoration request: " . json_encode($data));

    post_sync_send_request($data);
}

/**
 * Handles post deletion (soft and hard delete).
 */
function post_sync_handle_deletion($post_id): void
{
    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return;
    }
    error_log(message: "[Post Sync Plugin] post_sync_handle_deletion triggered for post ID: $post_id with filter: " . current_filter());

    $data = [
        'wp_id' => $post_id,
        'event' => (current_filter() === 'wp_trash_post') ? 'trashed' : 'deleted',
        'type' => get_post_type($post_id),
        'publisher' => get_the_author_meta('display_name', $post->post_author),
    ];
    error_log("[Post Sync Plugin] Sending deletion request: " . json_encode($data));

    post_sync_send_request($data);
}

/**
 * Sends the data to the Next.js server.
 */
function post_sync_send_request($data)
{
    $response = wp_remote_post(POST_SYNC_NEXTJS_URL, [
        'method' => 'POST',
        'timeout' => 10,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => POST_SYNC_API_KEY,
        ],
        'body' => wp_json_encode($data),
    ]);

    if (is_wp_error($response)) {
        error_log('[Post Sync Plugin] Request Error: ' . $response->get_error_message());
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log('[Post Sync Plugin] HTTP Error: ' . $status_code . ' - ' . wp_remote_retrieve_body($response));
    }
}
