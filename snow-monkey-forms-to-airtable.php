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
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\register_mapping_meta_box' );
	add_action( 'save_post_airtable_mapping', __NAMESPACE__ . '\save_mapping_meta_box' );
	add_action( 'snow_monkey_forms/administrator_mailer/after_send', __NAMESPACE__ . '\handle_administrator_mailer_after_send', 10, 3 );
	add_filter( 'snow_monkey_forms/administrator_mailer/is_sended', __NAMESPACE__ . '\handle_administrator_mailer_is_sended', 10, 3 );
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
 * Register the dedicated Airtable mapping meta box.
 */
function register_mapping_meta_box() {
	add_meta_box(
		'smf-to-airtable-mapping-fields',
		__( 'Airtable 連携設定', 'smf-to-airtable' ),
		__NAMESPACE__ . '\render_mapping_meta_box',
		'airtable_mapping',
		'normal',
		'default',
		[
			'__block_editor_compatible_meta_box' => true,
		]
	);
}

/**
 * Render the dedicated Airtable mapping meta box.
 *
 * @param \WP_Post $post The current post object.
 */
function render_mapping_meta_box( $post ) {
	$form_id     = get_post_meta( $post->ID, 'form_id', true );
	$webhook_url = get_post_meta( $post->ID, 'webhook_url', true );

	wp_nonce_field( 'smf_to_airtable_save_mapping', 'smf_to_airtable_mapping_nonce' );
	?>
	<p>
		<label for="smf-to-airtable-form-id"><strong><?php esc_html_e( 'フォームID', 'smf-to-airtable' ); ?></strong></label>
	</p>
	<p>
		<input
			type="text"
			id="smf-to-airtable-form-id"
			name="smf_to_airtable_form_id"
			value="<?php echo esc_attr( $form_id ); ?>"
			class="widefat"
		/>
	</p>
	<p class="description"><?php esc_html_e( 'Snow Monkey Forms のフォーム投稿ID（post_id）を入力してください。', 'smf-to-airtable' ); ?></p>

	<hr />

	<p>
		<label for="smf-to-airtable-webhook-url"><strong><?php esc_html_e( 'Webhook URL', 'smf-to-airtable' ); ?></strong></label>
	</p>
	<p>
		<input
			type="url"
			id="smf-to-airtable-webhook-url"
			name="smf_to_airtable_webhook_url"
			value="<?php echo esc_attr( $webhook_url ); ?>"
			class="widefat"
			placeholder="https://"
		/>
	</p>
	<p class="description"><?php esc_html_e( 'Airtable Automation の webhook URL を入力してください。', 'smf-to-airtable' ); ?></p>
	<?php
}

/**
 * Save the dedicated Airtable mapping meta box values.
 *
 * @param int $post_id The current post ID.
 */
function save_mapping_meta_box( $post_id ) {
	if ( ! isset( $_POST['smf_to_airtable_mapping_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smf_to_airtable_mapping_nonce'] ) ), 'smf_to_airtable_save_mapping' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$form_id = isset( $_POST['smf_to_airtable_form_id'] )
		? sanitize_text_field( wp_unslash( $_POST['smf_to_airtable_form_id'] ) )
		: '';

	$webhook_url = isset( $_POST['smf_to_airtable_webhook_url'] )
		? esc_url_raw( wp_unslash( $_POST['smf_to_airtable_webhook_url'] ) )
		: '';

	if ( '' === $form_id ) {
		delete_post_meta( $post_id, 'form_id' );
	} else {
		update_post_meta( $post_id, 'form_id', $form_id );
	}

	if ( '' === $webhook_url ) {
		delete_post_meta( $post_id, 'webhook_url' );
	} else {
		update_post_meta( $post_id, 'webhook_url', $webhook_url );
	}
}

/**
 * Bridge Snow Monkey Forms after_send hook args to send_to_airtable().
 *
 * @param bool   $is_sended Whether administrator mail was sent successfully.
 * @param object $responser Form responser object.
 * @param object $setting   Form setting object.
 */
function handle_administrator_mailer_after_send( $is_sended, $responser, $setting ) {
	handle_submission_for_airtable( $is_sended, $responser, $setting, 'after_send' );
}

/**
 * Fallback hook handler to support environments where after_send is not firing.
 *
 * @param bool   $is_sended Whether administrator mail was sent successfully.
 * @param object $responser Form responser object.
 * @param object $setting   Form setting object.
 * @return bool
 */
function handle_administrator_mailer_is_sended( $is_sended, $responser, $setting ) {
	handle_submission_for_airtable( $is_sended, $responser, $setting, 'is_sended' );

	return $is_sended;
}

/**
 * Shared submission dispatcher from Snow Monkey Forms hooks.
 *
 * @param bool   $is_sended Whether administrator mail was sent successfully.
 * @param object $responser Form responser object.
 * @param object $setting   Form setting object.
 * @param string $source    Hook source.
 */
function handle_submission_for_airtable( $is_sended, $responser, $setting, $source ) {
	static $already_processed = false;

	if ( $already_processed ) {
		return;
	}

	$form_id = '';
	$values  = [];

	// Prefer the form post ID from setting to avoid requiring a hidden form field.
	if ( is_object( $setting ) ) {
		if ( isset( $setting->id ) && is_scalar( $setting->id ) ) {
			$form_id = sanitize_text_field( (string) $setting->id );
		} elseif ( isset( $setting->post_id ) && is_scalar( $setting->post_id ) ) {
			$form_id = sanitize_text_field( (string) $setting->post_id );
		} elseif ( isset( $setting->form_id ) && is_scalar( $setting->form_id ) ) {
			$form_id = sanitize_text_field( (string) $setting->form_id );
		} elseif ( isset( $setting->post ) && is_object( $setting->post ) && isset( $setting->post->ID ) && is_scalar( $setting->post->ID ) ) {
			$form_id = sanitize_text_field( (string) $setting->post->ID );
		} elseif ( isset( $setting->name ) && is_scalar( $setting->name ) ) {
			$form_id = sanitize_text_field( (string) $setting->name );
		}

		if ( '' === $form_id && method_exists( $setting, 'get' ) ) {
			foreach ( [ 'id', 'post_id', 'form_id', 'name' ] as $key ) {
				$value = $setting->get( $key );
				if ( is_scalar( $value ) && '' !== (string) $value ) {
					$form_id = sanitize_text_field( (string) $value );
					break;
				}
			}
		}
	}

	// Debug: Responser object structure
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && is_object( $responser ) ) {
		error_log( '[SMF to Airtable DEBUG] Responser class: ' . get_class( $responser ) );
		error_log( '[SMF to Airtable DEBUG] Responser methods: ' . implode( ', ', get_class_methods( $responser ) ) );
	}

	if ( is_object( $responser ) ) {
		// Primary: Use get_all() when available (supported in current SMF versions, including v12.x)
		if ( method_exists( $responser, 'get_all' ) ) {
			$values = (array) $responser->get_all();
		}
		// Fallback 1: Try get_values() for potential older versions
		elseif ( method_exists( $responser, 'get_values' ) ) {
			$values = (array) $responser->get_values();
		}
		// Fallback 2: Try direct property access
		elseif ( isset( $responser->values ) && is_array( $responser->values ) ) {
			$values = $responser->values;
		}
	}

	// Debug: Values extraction result
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		$value_keys = array_map( 'strval', array_keys( $values ) );
		error_log( '[SMF to Airtable DEBUG] Values count: ' . count( $values ) );
		error_log( '[SMF to Airtable DEBUG] Value keys: ' . implode( ', ', $value_keys ) );
	}

	if ( true !== $is_sended ) {
		$webhook_url = is_string( $form_id ) && '' !== $form_id ? (string) get_webhook_url_for_form( $form_id ) : '';
		log_webhook_result(
			$form_id,
			$webhook_url,
			new \WP_Error( 'smf_admin_mail_send_failed', 'Administrator mail send failed.' )
		);
		$already_processed = true;
		return;
	}

	if ( '' === $form_id && is_object( $responser ) && method_exists( $responser, 'get' ) ) {
		foreach ( [ 'form_id', 'post_id', 'id' ] as $key ) {
			$form_id_value = $responser->get( $key );
			if ( is_scalar( $form_id_value ) && '' !== (string) $form_id_value ) {
				$form_id = sanitize_text_field( (string) $form_id_value );
				break;
			}
		}
	}

	if ( '' === $form_id ) {
		log_webhook_result(
			'(unknown)',
			'',
			new \WP_Error( 'smf_form_id_not_found', sprintf( 'form_id could not be resolved (source=%s).', $source ) )
		);
		// Let the other hook try again because it may provide richer context.
		return;
	}

	// Validate values before sending
	if ( empty( $values ) ) {
		log_webhook_result(
			$form_id,
			'',
			new \WP_Error( 'smf_empty_values', sprintf( 'Form values are empty (source=%s).', $source ) )
		);
		$already_processed = true;
		return;
	}

	$already_processed = true;
	send_to_airtable( $form_id, $values );
}

/**
 * Send form data to Airtable via webhook.
 *
 * @param string $form_id The form ID.
 * @param array  $values  The form submission values.
 */
function send_to_airtable( $form_id, $values ) {
	$debug_mode = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

	if ( ! $form_id || ! is_array( $values ) ) {
		log_webhook_result(
			! empty( $form_id ) ? (string) $form_id : '(unknown)',
			'',
			new \WP_Error( 'smf_invalid_payload', 'form_id or values are invalid.' )
		);
		return;
	}

	$webhook_url = get_webhook_url_for_form( $form_id );

	if ( empty( $webhook_url ) || ! is_string( $webhook_url ) ) {
		log_webhook_result(
			(string) $form_id,
			'',
			new \WP_Error( 'smf_webhook_mapping_not_found', 'Webhook URL mapping not found for form_id.' )
		);
		return;
	}

	$json_payload = wp_json_encode( $values );

	if ( false === $json_payload ) {
		log_webhook_result(
			(string) $form_id,
			$webhook_url,
			new \WP_Error( 'smf_payload_encode_failed', 'Failed to encode payload to JSON.' )
		);
		return;
	}

	// Debug: JSON payload before sending
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( '[SMF to Airtable DEBUG] JSON payload: ' . substr( $json_payload, 0, 500 ) );
	}

	$response = wp_remote_post(
		$webhook_url,
		[
			'headers'  => [ 'Content-Type' => 'application/json' ],
			'body'     => $json_payload,
			// Keep submissions fast in production; debug mode can wait for response details.
			'blocking' => $debug_mode,
			'timeout'  => $debug_mode ? 10 : 1,
		]
	);

	if ( $debug_mode || is_wp_error( $response ) ) {
		log_webhook_result( $form_id, $webhook_url, $response );
	}
}

/**
 * Log the webhook result to error_log when WP_DEBUG_LOG is enabled.
 *
 * @param string                $form_id     The form ID.
 * @param string                $webhook_url The webhook URL.
 * @param array|\WP_Error       $response    The wp_remote_post response.
 */
function log_webhook_result( $form_id, $webhook_url, $response ) {
	$form_id = '' !== (string) $form_id ? (string) $form_id : '(unknown)';

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
	}
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
