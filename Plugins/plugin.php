<?php
/**
 * Plugin Name: Post and User Sync Plugin
 * Description: Syncs WordPress posts and users with a Next.js server on create, update, or delete events.
 * Version: 1.62
 * Author: Suyog Prasai
 * License: GPLv2 or later
 */

// Define constants securely.
define('POST_SYNC_NEXTJS_URL', 'http://parewa_nextjs/api/post'); // Update for posts
define('USER_SYNC_NEXTJS_URL', 'http://parewa_nextjs/api/user'); // New endpoint for users
define('POST_SYNC_API_KEY', 'YOUR_API_KEY'); // Store securely

// Post-related hooks
add_action('wp_after_insert_post', 'post_sync_handle_post', 10, 2);
add_action('wp_trash_post', 'post_sync_handle_deletion', 10, 1);
add_action('untrash_post', 'post_sync_handle_restore', 10, 1);

// User-related hooks
add_action('user_register', 'user_sync_handle_creation', 10, 1);
add_action('profile_update', 'user_sync_handle_update', 10, 2);
add_action('delete_user', 'user_sync_handle_deletion', 10, 1);

/**
 * Handles post publish/update events.
 */
function post_sync_handle_post($post_id, $post): void
{
    error_log("[Post Sync Plugin] post_sync_handle_post triggered for post ID: $post_id with filter: " . current_filter());

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (get_post_type($post_id) == 'news') {
        if (get_field('notice_category', $post_id) == null) {
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
            'category' => get_field('notice_category', $post_id),
            'event' => ($post->post_date_gmt === $post->post_modified_gmt) ? 'published' : 'modified',
            'type' => get_post_type($post_id),
        ];
    } elseif (get_post_type($post_id) == 'article') {
        if (get_field('article_category', $post_id) == null) {
            return;
        }
        $data = [
            'wp_id' => $post_id,
            'title' => get_the_title($post_id),
            'oneLiner' => get_field('one_liner', $post_id),
            'content' => apply_filters('the_content', $post->post_content),
            'publishedIn' => get_the_date('c', $post_id),
            'featuredImage' => get_the_post_thumbnail_url($post_id, 'full') ?: '',
            'publisher_name' => get_the_author_meta('display_name', $post->post_author),
            'postTags' => wp_get_post_tags($post_id, ['fields' => 'names']),
            'category' => get_field('article_category', $post_id),
            'author_name' => get_field('author', $post_id),
            'event' => ($post->post_date_gmt === $post->post_modified_gmt) ? 'published' : 'modified',
            'type' => get_post_type($post_id),
        ];
    } elseif (get_post_type($post_id) == 'announcement') {
        if (get_field('announcement_category', $post_id) == null) {
            return;
        }
        $data = [
            'wp_id' => $post_id,
            'title' => get_the_title($post_id),
            'content' => apply_filters('the_content', $post->post_content),
            'publishedIn' => get_the_date('c', $post_id),
            'category' => get_field('announcement_category', $post_id),
            'publisher_name' => get_the_author_meta('display_name', $post->post_author),
            'event' => ($post->post_date_gmt === $post->post_modified_gmt) ? 'published' : 'modified',
            'type' => get_post_type($post_id),
            'link' => get_field('link', $post_id),
            'author_name' => get_field('author', $post_id),
            'show' => get_field('show', $post_id),
        ];
    } else {
        error_log("[Post Sync Plugin] Invalid post type: " . get_post_type($post_id));
        return;
    }

    post_sync_send_request($data, POST_SYNC_NEXTJS_URL);
}

function post_sync_handle_restore($post_id): void
{
    $post_type = get_post_type($post_id);
    if (!$post_type) {
        return;
    }
    error_log("[Post Sync Plugin] post restore triggered for post ID: $post_id with filter: " . current_filter());

    $data = [
        'wp_id' => $post_id,
        'event' => 'post_restore',
        'type' => get_post_type($post_id),
        'publisher_name' => get_the_author_meta('display_name', get_post_field('post_author', $post_id)),
    ];
    error_log("[Post Sync Plugin] Sending restoration request: " . json_encode($data));

    post_sync_send_request($data, POST_SYNC_NEXTJS_URL);
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
    error_log("[Post Sync Plugin] post_sync_handle_deletion triggered for post ID: $post_id with filter: " . current_filter());

    $data = [
        'wp_id' => $post_id,
        'event' => (current_filter() === 'wp_trash_post') ? 'trashed' : 'deleted',
        'type' => get_post_type($post_id),
        'publisher_name' => get_the_author_meta('display_name', get_post_field('post_author', $post_id)),
    ];
    error_log("[Post Sync Plugin] Sending deletion request: " . json_encode($data));

    post_sync_send_request($data, POST_SYNC_NEXTJS_URL);
}

/**
 * Handles user creation.
 */
function user_sync_handle_creation($user_id): void
{
    error_log("[User Sync Plugin] user_sync_handle_creation triggered for user ID: $user_id with filter: " . current_filter());

    $user = get_userdata($user_id);
    if (!$user) {
        error_log("[User Sync Plugin] User not found for ID: $user_id");
        return;
    }

    $data = [
        'wp_id' => $user_id,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'name' => $user->display_name,
        'roles' => $user->roles,
        'event' => 'created',
        'type' => 'user',
        'position' => get_field('position', 'user_' . $user_id),
    ];

    error_log("[User Sync Plugin] Sending creation request: " . json_encode($data));
    post_sync_send_request($data, USER_SYNC_NEXTJS_URL);
}

/**
 * Handles user profile updates.
 */
function user_sync_handle_update($user_id, $old_user_data): void
{
    error_log("[User Sync Plugin] user_sync_handle_update triggered for user ID: $user_id with filter: " . current_filter());

    $user = get_userdata($user_id);
    if (!$user) {
        error_log("[User Sync Plugin] User not found for ID: $user_id");
        return;
    }

    $data = [
        'wp_id' => $user_id,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'name' => $user->display_name,
        'roles' => $user->roles,
        'position' => get_field('position', 'user_' . $user_id),
        'event' => 'modified',
        'type' => 'user',
    ];

    error_log("[User Sync Plugin] Sending update request: " . json_encode($data));
    post_sync_send_request($data, USER_SYNC_NEXTJS_URL);
}

/**
 * Handles user deletion.
 */
function user_sync_handle_deletion($user_id): void
{
    error_log("[User Sync Plugin] user_sync_handle_deletion triggered for user ID: $user_id with filter: " . current_filter());

    $user = get_userdata($user_id);
    if (!$user) {
        error_log("[User Sync Plugin] User not found for ID: $user_id");
        return;
    }

    $data = [
        'wp_id' => $user_id,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'event' => 'deleted',
        'type' => 'user',
    ];

    error_log("[User Sync Plugin] Sending deletion request: " . json_encode($data));
    post_sync_send_request($data, USER_SYNC_NEXTJS_URL);
}

/**
 * Sends the data to the Next.js server.
 */
function post_sync_send_request($data, $url): void
{
    $response = wp_remote_post($url, [
        'method' => 'POST',
        'timeout' => 10,
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => POST_SYNC_API_KEY,
        ],
        'body' => wp_json_encode($data),
    ]);

    if (is_wp_error($response)) {
        error_log('[Sync Plugin] Request Error: ' . $response->get_error_message());
        return;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log('[Sync Plugin] HTTP Error: ' . $status_code . ' - ' . wp_remote_retrieve_body($response));
    }
}