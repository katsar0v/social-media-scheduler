<?php
/**
 * API Key service - business logic.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Service;

use KatsarovDesign\SocialMediaScheduler\Domain\ApiKey;
use KatsarovDesign\SocialMediaScheduler\Repository\ApiKeyRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service for API key operations.
 */
final class ApiKeyService {
	private ApiKeyRepository $repository;

	public function __construct( ?ApiKeyRepository $repository = null ) {
		$this->repository = $repository ?? new ApiKeyRepository();
	}

	/**
	 * Create a new API key.
	 *
	 * @param array{name:string,permissions:list<string>,status?:string} $data
	 * @return array<string,mixed> Created key data including plaintext key (once only).
	 */
	public function create_key( array $data ): array {
		return $this->repository->create( $data );
	}

	/**
	 * Get API key by ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_key( int $id ): ?array {
		return $this->repository->find_by_id( $id );
	}

	/**
	 * List API keys.
	 *
	 * @param array{status?:string,page?:int,per_page?:int} $filters
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list_keys( array $filters = array() ): array {
		return $this->repository->list( $filters );
	}

	/**
	 * Update an API key.
	 *
	 * @param int $id
	 * @param array{name?:string,status?:string,permissions?:list<string>} $data
	 * @return array<string,mixed>|null Updated key data, or null if not found.
	 */
	public function update_key( int $id, array $data ): ?array {
		return $this->repository->update( $id, $data );
	}

	/**
	 * Delete an API key.
	 */
	public function delete_key( int $id ): bool {
		return $this->repository->delete( $id );
	}

	/**
	 * Authenticate an API key.
	 *
	 * @return array<string,mixed>|null Key data if valid and active, null otherwise.
	 */
	public function authenticate( string $api_key ): ?array {
		$key_data = $this->repository->find_by_api_key( $api_key );

		if ( ! $key_data ) {
			return null;
		}

		// Update last used timestamp
		$this->repository->update_last_used( $key_data['id'] );

		return $key_data;
	}

	/**
	 * Validate that a key has required permissions.
	 *
	 * @param array<string,mixed> $key_data Key data from authenticate().
	 * @param list<string> $required_permissions Required permissions.
	 */
	public function validate_permissions( array $key_data, array $required_permissions ): bool {
		$key = new ApiKey( $key_data );
		return $key->hasPermissions( $required_permissions );
	}

	/**
	 * Get all valid permission scopes.
	 *
	 * @return list<string>
	 */
	public function get_valid_permissions(): array {
		return ApiKey::getValidPermissions();
	}

	/**
	 * Get all valid statuses.
	 *
	 * @return list<string>
	 */
	public function get_valid_statuses(): array {
		return ApiKey::getValidStatuses();
	}

	/**
	 * Generate a new API key string (for display/creation).
	 */
	public function generate_key(): string {
		return $this->repository->generate_key();
	}

	/**
	 * Check if a key has a specific permission.
	 */
	public function has_permission( array $key_data, string $permission ): bool {
		$key = new ApiKey( $key_data );
		return $key->hasPermissions( array( $permission ) );
	}

	/**
	 * Get permission groups for UI display.
	 *
	 * @return array<string,array{label:string,permissions:list<string>}>
	 */
	public function get_permission_groups(): array {
		return array(
			'posts' => array(
				'label'       => __( 'Posts', 'social-media-scheduler' ),
				'permissions' => array(
					ApiKey::PERMISSION_POSTS_READ,
					ApiKey::PERMISSION_POSTS_WRITE,
					ApiKey::PERMISSION_POSTS_DELETE,
				),
			),
			'publish' => array(
				'label'       => __( 'Publishing', 'social-media-scheduler' ),
				'permissions' => array(
					ApiKey::PERMISSION_PUBLISH_META,
					ApiKey::PERMISSION_PUBLISH_TIKTOK,
				),
			),
			'accounts' => array(
				'label'       => __( 'Social Accounts', 'social-media-scheduler' ),
				'permissions' => array(
					ApiKey::PERMISSION_ACCOUNTS_READ,
					ApiKey::PERMISSION_ACCOUNTS_WRITE,
				),
			),
			'api_keys' => array(
				'label'       => __( 'API Keys', 'social-media-scheduler' ),
				'permissions' => array(
					ApiKey::PERMISSION_API_KEYS_READ,
					ApiKey::PERMISSION_API_KEYS_WRITE,
				),
			),
			'full_access' => array(
				'label'       => __( 'Full Access', 'social-media-scheduler' ),
				'permissions' => array(
					ApiKey::PERMISSION_ALL,
				),
			),
		);
	}
}
