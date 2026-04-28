<?php
/**
 * Plugin Name:       Snow Monkey Forms to Airtable
 * Description:       Snow Monkey Forms のフォーム送信データを Airtable Automation へ転送する
 * Version:           1.0.0
 * Author:            Your Name
 * License:           MIT
 * Text Domain:       smf-to-airtable
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 */

namespace SMFToAirtable;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMF_TO_AIRTABLE_PLUGIN_FILE', __FILE__ );
define( 'SMF_TO_AIRTABLE_PLUGIN_DIR', dirname( __FILE__ ) );

/**
 * Initialize the plugin.
 */
function init() {
	add_action( 'init', __NAMESPACE__ . '\register_mapping_post_type' );
	add_action( 'init', __NAMESPACE__ . '\register_meta_fields' );
	add_action( 'snow_monkey_forms_after_send_mail', __NAMESPACE__ . '\send_to_airtable', 10, 2 );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Register custom post type for Airtable mapping.
 */
function register_mapping_post_type() {
	$args = [
		'label'              => __( 'Airtable マッピング', 'smf-to-airtable' ),
		'description'        => __( 'Snow Monkey Forms フォーム ID と Airtable Webhook URL のマッピング管理', 'smf-to-airtable' ),
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => false,
		'show_in_admin_bar'  => true,
		'menu_position'      => 25,
		'menu_icon'          => 'dashicons-cloud',
		'hierarchical'       => false,
		'supports'           => [ 'title' ],
		'has_archive'        => false,
		'can_export'         => true,
		'capability_type'    => 'post',
		'show_in_rest'       => true,
	];

	register_post_type( 'airtable_mapping', $args );
}

/**
 * Register custom meta fields for Airtable mapping.
 */
function register_meta_fields() {
	register_post_meta(
		'airtable_mapping',
		'form_id',
		[
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'sanitize_text_field',
			'show_in_rest'      => true,
		]
	);

	register_post_meta(
		'airtable_mapping',
		'webhook_url',
		[
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,
		]
	);
}

/**
 * Send form data to Airtable via webhook.
 *
 * @param string $form_id The form ID (post slug or post ID).
 * @param array  $values  The form submission values.
 */
function send_to_airtable( $form_id, $values ) {
	if ( ! $form_id || ! is_array( $values ) ) {
		return;
	}

	// Get webhook URL from airtable_mapping post type.
	$webhook_url = get_webhook_url_for_form( $form_id );

	if ( empty( $webhook_url ) || ! is_string( $webhook_url ) ) {
		return;
	}

	// Prepare JSON payload.
	$json_payload = wp_json_encode( $values );

	if ( false === $json_payload ) {
		return;
	}

	// Send to Airtable webhook (non-blocking).
	wp_remote_post(
		$webhook_url,
		[
			'headers'  => [ 'Content-Type' => 'application/json' ],
			'body'     => $json_payload,
			'blocking' => false,
		]
	);
}

/**
 * Get webhook URL for a given form ID.
 *
 * @param string $form_id The form ID.
 * @return string|null The webhook URL, or null if not found.
 */
function get_webhook_url_for_form( $form_id ) {
	$args = [
		'post_type'      => 'airtable_mapping',
		'posts_per_page' => 1,
		'meta_query'     => [
			[
				'key'   => 'form_id',
				'value' => $form_id,
				'compare' => '=',
			],
		],
	];

	$posts = new \WP_Query( $args );

	if ( ! $posts->have_posts() ) {
		return null;
	}

	$webhook_url = get_post_meta( $posts->posts[0]->ID, 'webhook_url', true );

	return ! empty( $webhook_url ) ? $webhook_url : null;
}
