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


register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Plugin activation: create the webhook log table.
 */
function activate() {
	create_log_table();
}

/**
 * Create the webhook log table if it doesn't exist.
 */
function create_log_table() {
	global $wpdb;

	$table   = $wpdb->prefix . 'smf_airtable_logs';
	$charset = $wpdb->get_charset_collate();

	// dbDelta requires CREATE TABLE without IF NOT EXISTS to detect schema changes correctly.
	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		form_id varchar(255) NOT NULL,
		webhook_url varchar(2083) NOT NULL,
		success tinyint(1) NOT NULL DEFAULT 0,
		status_code smallint(6) NOT NULL DEFAULT 0,
		error_message text,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY form_id (form_id),
		KEY created_at (created_at)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Initialize the plugin.
 */
function init() {
	// Run dbDelta on version bump so the table exists even when the plugin was already active.
	$installed_ver = get_option( 'smf_airtable_db_version', '0' );
	if ( '1.0' !== $installed_ver ) {
		create_log_table();
		update_option( 'smf_airtable_db_version', '1.0' );
	}

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

	$webhook_url = get_webhook_url_for_form( $form_id );

	if ( empty( $webhook_url ) || ! is_string( $webhook_url ) ) {
		return;
	}

	$json_payload = wp_json_encode( $values );

	if ( false === $json_payload ) {
		return;
	}

	$response = wp_remote_post(
		$webhook_url,
		[
			'headers'  => [ 'Content-Type' => 'application/json' ],
			'body'     => $json_payload,
			'blocking' => true,
			'timeout'  => 10,
		]
	);

	log_webhook_result( $form_id, $webhook_url, $response );
}

/**
 * Log the webhook result to error_log (debug) or the database (production).
 *
 * @param string                $form_id     The form ID.
 * @param string                $webhook_url The webhook URL.
 * @param array|\WP_Error       $response    The wp_remote_post response.
 */
function log_webhook_result( $form_id, $webhook_url, $response ) {
	$is_error    = is_wp_error( $response );
	$status_code = $is_error ? 0 : (int) wp_remote_retrieve_response_code( $response );
	$success     = ! $is_error && $status_code >= 200 && $status_code < 300;
	$error_msg   = $is_error ? $response->get_error_message() : '';

	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		$status_label = $is_error ? 'error' : 'status';
		$status_value = $is_error ? $error_msg : $status_code;
		error_log( sprintf(
			'[SMF to Airtable] form_id=%s success=%s %s=%s',
			$form_id,
			$success ? 'true' : 'false',
			$status_label,
			$status_value
		) );
		return;
	}

	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'smf_airtable_logs',
		[
			'form_id'       => $form_id,
			'webhook_url'   => $webhook_url,
			'success'       => $success ? 1 : 0,
			'status_code'   => $status_code,
			'error_message' => $error_msg,
			'created_at'    => current_time( 'mysql' ),
		],
		[ '%s', '%s', '%d', '%d', '%s', '%s' ]
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
				'key'     => 'form_id',
				'value'   => $form_id,
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
