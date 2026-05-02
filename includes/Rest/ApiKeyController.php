<?php
/**
 * API Key REST API controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Service\ApiKeyService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for API key management.
 */
final class ApiKeyController extends Controller {
	private const NAMESPACE = RestRouter::NAMESPACE;

	/**
	 * @var ApiKeyService
	 */
	private ApiKeyService $service;

	/**
	 * Constructor.
	 */
	public function __construct( ?ApiKeyService $service = null ) {
		$this->service = $service ?? new ApiKeyService();
	}

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/api-keys',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_item_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/api-keys/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => __( 'Unique identifier for the API key.', 'social-media-scheduler' ),
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_item_args( false ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Get the API key schema.
	 *
	 * @return array<string,mixed>
	 */
	public function get_public_item_schema(): array {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'sms_api_key',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'description' => __( 'Unique identifier for the API key.', 'social-media-scheduler' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'        => array(
					'description' => __( 'Human-readable name for the API key.', 'social-media-scheduler' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
					'required'    => true,
					'arg_options' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'status'      => array(
					'description' => __( 'Status of the API key.', 'social-media-scheduler' ),
					'type'        => 'string',
					'enum'        => $this->service->get_valid_statuses(),
					'context'     => array( 'view', 'edit' ),
					'default'     => 'active',
				),
				'permissions' => array(
					'description' => __( 'List of permissions granted to this API key.', 'social-media-scheduler' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => $this->service->get_valid_permissions(),
					),
					'context'     => array( 'view', 'edit' ),
					'default'     => array(),
				),
				'is_active'   => array(
					'description' => __( 'Whether the API key is active.', 'social-media-scheduler' ),
					'type'        => 'boolean',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'last_used_at' => array(
					'description' => __( 'Last time the API key was used.', 'social-media-scheduler' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'created_at'  => array(
					'description' => __( 'Creation date of the API key.', 'social-media-scheduler' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'updated_at'  => array(
					'description' => __( 'Last update date of the API key.', 'social-media-scheduler' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		return $schema;
	}

	/**
	 * Get collection parameters.
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array(
				'description'       => __( 'Current page of the collection.', 'social-media-scheduler' ),
				'type'              => 'integer',
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $param ) => $param >= 1,
			),
			'per_page' => array(
				'description'       => __( 'Maximum number of items to be returned in result set.', 'social-media-scheduler' ),
				'type'              => 'integer',
				'default'           => 20,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn( $param ) => $param >= 1 && $param <= 100,
			),
			'status'   => array(
				'description' => __( 'Limit results to keys with specific status.', 'social-media-scheduler' ),
				'type'        => 'string',
				'enum'        => $this->service->get_valid_statuses(),
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function get_item_args( bool $require_name = true ): array {
		return array(
			'name'        => array(
				'type'              => 'string',
				'required'          => $require_name,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'      => array(
				'type' => 'string',
				'enum' => $this->service->get_valid_statuses(),
			),
			'permissions' => array(
				'type'  => 'array',
				'items' => array(
					'type' => 'string',
					'enum' => $this->service->get_valid_permissions(),
				),
			),
		);
	}

	/**
	 * GET /sms/v1/api-keys
	 */
	public function list_items( WP_REST_Request $request ): mixed {
		$filters = array(
			'status'   => $request->get_param( 'status' ),
			'page'     => $request->get_param( 'page' ),
			'per_page' => $request->get_param( 'per_page' ),
		);

			$result = $this->service->list_keys( array_filter( $filters ) );

			return $this->response( $result );
	}

	/**
	 * GET /sms/v1/api-keys/{id}
	 */
	public function get_item( WP_REST_Request $request ): mixed {
		$id = (int) $request->get_param( 'id' );
		$key = $this->service->get_key( $id );

		if ( ! $key ) {
			return $this->rest_error(
				'api_key_not_found',
				__( 'API key not found.', 'social-media-scheduler' ),
				array(),
				404
			);
		}

		return $this->response( $key );
	}

	/**
	 * POST /sms/v1/api-keys
	 */
	public function create_item( WP_REST_Request $request ): mixed {
		$params      = $this->request_data( $request );
		$name        = sanitize_text_field( (string) ( $params['name'] ?? '' ) );
		if ( '' === $name ) {
			return $this->rest_error(
				'missing_name',
				__( 'API key name is required.', 'social-media-scheduler' ),
				array(),
				400
			);
		}

		try {
			$status      = $this->normalize_status_param( $params['status'] ?? 'active' );
			$permissions = $this->normalize_permissions_param( $params['permissions'] ?? array() );
			$result      = $this->service->create_key(
				array(
					'name'        => $name,
					'status'      => $status,
					'permissions' => $permissions,
				)
			);

			$response = $this->response( $result, 201 );
			$response->header( 'X-API-Key-Plaintext', $result['plaintext_key'] ?? '' );

			return $response;
		} catch ( \InvalidArgumentException $e ) {
			return $this->rest_error(
				'api_key_validation_failed',
				$e->getMessage(),
				array(),
				400
			);
		} catch ( \Exception $e ) {
			return $this->rest_error(
				'api_key_creation_failed',
				$e->getMessage(),
				array(),
				500
			);
		}
	}

	/**
	 * PUT/PATCH /sms/v1/api-keys/{id}
	 */
	public function update_item( WP_REST_Request $request ): mixed {
		$id      = (int) $request->get_param( 'id' );
		$params  = $this->request_data( $request );

		$data = array();

		try {
			if ( array_key_exists( 'name', $params ) ) {
				$data['name'] = sanitize_text_field( (string) $params['name'] );
			}
			if ( array_key_exists( 'status', $params ) ) {
				$data['status'] = $this->normalize_status_param( $params['status'] );
			}
			if ( array_key_exists( 'permissions', $params ) ) {
				$data['permissions'] = $this->normalize_permissions_param( $params['permissions'] );
			}

			$updated = $this->service->update_key( $id, $data );
		} catch ( \InvalidArgumentException $e ) {
			return $this->rest_error(
				'api_key_validation_failed',
				$e->getMessage(),
				array(),
				400
			);
		}

		if ( ! $updated ) {
			return $this->rest_error(
				'api_key_not_found',
				__( 'API key not found.', 'social-media-scheduler' ),
				array(),
				404
			);
		}

		return $this->response( $updated );
	}

	/**
	 * DELETE /sms/v1/api-keys/{id}
	 */
	public function delete_item( WP_REST_Request $request ): mixed {
		$id = (int) $request->get_param( 'id' );

		$deleted = $this->service->delete_key( $id );

		if ( ! $deleted ) {
			return $this->rest_error(
				'api_key_not_found',
				__( 'API key not found.', 'social-media-scheduler' ),
				array(),
				404
			);
		}

		return $this->empty_response();
	}

	/**
	 * Permission check: list items.
	 */
	public function get_items_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( Plugin::CAPABILITY );
	}

	/**
	 * Permission check: create item.
	 */
	public function create_item_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( Plugin::CAPABILITY );
	}

	/**
	 * Permission check: get single item.
	 */
	public function get_item_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( Plugin::CAPABILITY );
	}

	/**
	 * Permission check: update item.
	 */
	public function update_item_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( Plugin::CAPABILITY );
	}

	/**
	 * Permission check: delete item.
	 */
	public function delete_item_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( Plugin::CAPABILITY );
	}

	/**
	 * AJAX handler for saving API key.
	 */
	public function ajax_save_api_key(): void {
		check_ajax_referer( 'sms_api_key_action', '_ajax_nonce' );

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'social-media-scheduler' ), 403 );
		}

		$key_id = isset( $_POST['key_id'] ) ? (int) $_POST['key_id'] : 0;
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		if ( '' === $name ) {
			wp_send_json_error( __( 'API key name is required.', 'social-media-scheduler' ), 400 );
		}

		try {
			$status = $this->normalize_status_param( isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'active' );
			$perms  = $this->normalize_permissions_param( isset( $_POST['permissions'] ) ? wp_unslash( $_POST['permissions'] ) : array() );

			if ( $key_id > 0 ) {
				$result = $this->service->update_key(
					$key_id,
					array(
						'name'        => $name,
						'status'      => $status,
						'permissions' => $perms,
					)
				);
				if ( ! $result ) {
					wp_send_json_error( __( 'API key not found.', 'social-media-scheduler' ), 404 );
				}
				wp_send_json_success( $result );
			} else {
				$result = $this->service->create_key(
					array(
						'name'        => $name,
						'status'      => $status,
						'permissions' => $perms,
					)
				);
				wp_send_json_success( $result, 201 );
			}
		} catch ( \InvalidArgumentException $e ) {
			wp_send_json_error( $e->getMessage(), 400 );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage(), 500 );
		}
	}

	/**
	 * AJAX handler for deleting API key.
	 */
	public function ajax_delete_api_key(): void {
		check_ajax_referer( 'sms_api_key_action', '_ajax_nonce' );

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'social-media-scheduler' ), 403 );
		}

		$key_id = isset( $_POST['key_id'] ) ? (int) $_POST['key_id'] : 0;

		if ( $key_id <= 0 ) {
			wp_send_json_error( __( 'Invalid API key ID.', 'social-media-scheduler' ), 400 );
		}

		$deleted = $this->service->delete_key( $key_id );

		if ( ! $deleted ) {
			wp_send_json_error( __( 'API key not found.', 'social-media-scheduler' ), 404 );
		}

		wp_send_json_success( null, 204 );
	}

	/**
	 * @param mixed $permissions Raw permission payload.
	 * @return list<string>
	 */
	private function normalize_permissions_param( mixed $permissions ): array {
		if ( ! is_array( $permissions ) ) {
			return array();
		}

		$permissions = array_map(
			static fn( mixed $permission ): string => sanitize_text_field( (string) $permission ),
			$permissions
		);
		$permissions = array_values( array_filter( $permissions ) );

		$invalid_permissions = array_diff( $permissions, $this->service->get_valid_permissions() );
		if ( ! empty( $invalid_permissions ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s: comma-separated list of invalid API key permissions. */
					__( 'Invalid API key permissions: %s', 'social-media-scheduler' ),
					implode( ', ', $invalid_permissions )
				)
			);
		}

		return $permissions;
	}

	private function normalize_status_param( mixed $status ): string {
		$status = sanitize_text_field( (string) $status );
		if ( '' === $status ) {
			return 'active';
		}

		return $status;
	}

	/**
	 * @param array<string,mixed> $data Additional error data.
	 */
	private function rest_error( string $code, string $message, array $data, int $status ): WP_Error {
		$data['status'] = $status;

		return new WP_Error( $code, $message, $data );
	}
}
