<?php
/**
 * Plugin Name: Post Sync Plugin
 * Description: Syncs WordPress posts with a Next.js server on publish, update, or delete events.
 * Version: 1.3
 * Author: Suyog Prasai
 * License: GPLv2 or later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants securely.
define( 'POST_SYNC_NEXTJS_URL', 'http://host.docker.internal:3000/api/post' ); // Update this URL as needed.
define( 'POST_SYNC_API_KEY', 'your-secure-api-key-here' ); // Store securely, consider using environment variables.

// Hook into post lifecycle events.
add_action( 'wp_after_insert_post', 'post_sync_handle_post', 99, 2 );
add_action( 'wp_trash_post', 'post_sync_handle_deletion', 99 );
add_action( 'delete_post', 'post_sync_handle_deletion', 99 );

/**
 * Handles post publish/update events.
 */
function post_sync_handle_post( $post_id, $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    $data = [
        'id'            => $post_id,
        'title'         => get_the_title( $post_id ),
        'content'       => apply_filters( 'the_content', $post->post_content ),
        'type'          => get_post_type( $post_id ),
        'author'        => get_the_author_meta( 'display_name', $post->post_author ),
        'date'          => get_the_date( 'c', $post_id ),
        'modified'      => get_the_modified_date( 'c', $post_id ),
        'permalink'     => get_permalink( $post_id ),
        'tags'          => wp_get_post_tags( $post_id, [ 'fields' => 'names' ] ),
        'featured_image'=> get_the_post_thumbnail_url( $post_id, 'full' ) ?: '',
        'event'         => ( get_post_status( $post_id ) === 'publish' ) ? 'published' : 'modified',
    ];

    post_sync_send_request( $data );
}

/**
 * Handles post deletion (soft and hard delete).
 */
function post_sync_handle_deletion( $post_id ) {
    $post_type = get_post_type( $post_id );
    if ( ! $post_type ) {
        return;
    }

    $data = [
        'id'    => $post_id,
        'type'  => $post_type,
        'event' => ( current_filter() === 'wp_trash_post' ) ? 'trashed' : 'deleted',
    ];

    post_sync_send_request( $data );
}

/**
 * Sends the data to the Next.js server.
 */
function post_sync_send_request( $data ) {
    $response = wp_remote_post( POST_SYNC_NEXTJS_URL, [
        'method'    => 'POST',
        'timeout'   => 10,
        'headers'   => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . POST_SYNC_API_KEY,
        ],
        'body'      => wp_json_encode( $data ),
    ]);

    if ( is_wp_error( $response ) ) {
        error_log( '[Post Sync Plugin] Request Error: ' . $response->get_error_message() );
        return;
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    if ( $status_code !== 200 ) {
        error_log( '[Post Sync Plugin] HTTP Error: ' . $status_code . ' - ' . wp_remote_retrieve_body( $response ) );
    }
}
