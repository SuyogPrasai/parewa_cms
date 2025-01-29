<?php
/**
 * Plugin Name: Post Sync Plugin
 * Description: Sends a POST request to a secure Next.js server whenever a WordPress post is published, modified, or deleted.
 * Version: 1.3
 * Author: Suyog Prasai
 * License: GPLv2 or later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for the Next.js server and API key.
define( 'POST_SYNC_NEXTJS_URL', 'http://host.docker.internal:3000/api/post' ); // Update with your Next.js server URL.
define( 'POST_SYNC_API_KEY', '9SGnap5OiEdeGPdxa0BHwnFLqRfZy4YlMAbtGYGGvrcV1VQMHzHzCicMLoYbMfg2YDXskSsYHjqRfoTyUxtWbdlaejNKEhVQWNPZEuxaaNnN5HzWUj5qoO7JQU4GzAS5' ); // Replace with your actual API key.

// Hooks for post events.
add_action( 'save_post', 'post_sync_handle_post', 10, 2 );
add_action( 'wp_trash_post', 'post_sync_handle_deletion', 10, 1 );
add_action( 'delete_post', 'post_sync_handle_deletion', 10, 1 );

/**
 * Handles publishing or updating posts.
 */
function post_sync_handle_post( $post_id, $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }

    $data = array(
        'id'            => $post_id,
        'title'         => get_the_title( $post_id ),
        'content'       => apply_filters( 'the_content', $post->post_content ),
        'type'          => get_post_type( $post_id ),
        'author'        => get_the_author_meta( 'display_name', $post->post_author ),
        'date'          => get_the_date( 'c', $post_id ),
        'modified'      => get_the_modified_date( 'c', $post_id ),
        'permalink'     => get_permalink( $post_id ),
        'categories'    => wp_get_post_categories( $post_id, array( 'fields' => 'names' ) ),
        'tags'          => wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ),
        'featured_image'=> get_the_post_thumbnail_url( $post_id, 'full' ) ?: '',
        'event'         => ( get_post_status( $post_id ) === 'publish' ) ? 'published' : 'modified',
    );

    post_sync_send_request( $data );
}

/**
 * Handles post deletion (soft and hard delete).
 */
function post_sync_handle_deletion( $post_id ) {
    $data = array(
        'id'    => $post_id,
        'type'  => get_post_type( $post_id ),
        'event' => ( current_filter() === 'wp_trash_post' ) ? 'trashed' : 'deleted',
    );

    post_sync_send_request( $data );
}

/**
 * Sends the data to the Next.js server.
 */
function post_sync_send_request( $data ) {
    $response = wp_remote_post( POST_SYNC_NEXTJS_URL, array(
        'method'    => 'POST',
        'timeout'   => 10,
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . POST_SYNC_API_KEY,
        ),
        'body'      => wp_json_encode( $data ),
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( '[Post Sync Plugin] Error: ' . $response->get_error_message() );
    } elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
        error_log( '[Post Sync Plugin] Error: HTTP ' . wp_remote_retrieve_response_code( $response ) );
    }
}