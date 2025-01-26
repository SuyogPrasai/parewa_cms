<?php
/**
 * Plugin Name: Post Sync Plugin
 * Description: Sends a POST request to a secure Next.js server whenever a WordPress post is published, modified, or deleted.
 * Version: 1.0
 * Author: Suyog Prasai
 * License: GPLv2 or later
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for the Next.js server and API key.
define( 'NEXTJS_SERVER_URL', 'http://host.docker.internal:3000/post' ); // Replace with your Next.js server URL.
define( 'NEXTJS_API_KEY', '9SGnap5OiEdeGPdxa0BHwnFLqRfZy4YlMAbtGYGGvrcV1VQMHzHzCicMLoYbMfg2YDXskSsYHjqRfoTyUxtWbdlaejNKEhVQWNPZEuxaaNnN5HzWUj5qoO7JQU4GzAS5' ); // Replace with your API key.

add_action( 'publish_post', 'post_sync_handle_post', 10, 2 );
add_action( 'edit_post', 'post_sync_handle_post', 10, 2 );

// Function to handle post publishing, editing, or deleting
function post_sync_handle_post( $post_id, $post ) {
    // Check if the post type is 'post' and it's not an auto-save.
    if ( 'post' !== get_post_type( $post_id ) || wp_is_post_autosave( $post_id ) ) {
        return;
    }

    // Log the current filter name to ensure correct event is triggered.
    error_log("Current Filter: " . current_filter());

    // Ensure content is not empty, and sanitize input data.
    $data = array(
        'id'      => $post_id,
        'title'   => sanitize_text_field( $post->post_title ),
        'content' => sanitize_textarea_field( $post->post_content ),
    );

    // Determine the event type (published, modified, or deleted).
    if ( current_filter() === 'publish_post' ) {
        $data['event'] = 'published';
    } elseif ( current_filter() === 'edit_post' ) {
        $data['event'] = 'modified';
    }

    // Log the data to ensure it's correct.
    error_log("Post Sync Data: " . print_r($data, true));

    // Mark the post as synced to avoid duplicate requests.
    update_post_meta( $post_id, '_post_sync_sent', true );
}

// Function to send the data to the Next.js server
function post_sync_send_to_nextjs( $data ) {
    $url = NEXTJS_SERVER_URL
    
    // Send POST request to the Next.js server with the data.
    $response = wp_remote_post( $url, array(
        'method'      => 'POST',
        'timeout'     => 10,
        'headers'     => array(
            'Content-Type' => 'application/json',
            'Authorization' => NEXTJS_API_KEY,
        ),
        'body'        => wp_json_encode($data), // Ensure this matches expected format on Next.js server.
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( 'Post Sync Plugin: ' . $response->get_error_message() );
    } elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
        error_log( 'Post Sync Plugin: Failed with response code ' . wp_remote_retrieve_response_code( $response ) );
    }
}