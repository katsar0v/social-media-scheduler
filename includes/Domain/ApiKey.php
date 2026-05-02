<?php
/**
 * API Key domain model.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Domain;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Key value object.
 */
final class ApiKey {
	public const STATUS_ACTIVE = 'active';
	public const STATUS_INACTIVE = 'inactive';
	public const STATUS_REVOKED = 'revoked';

	public const PERMISSION_POSTS_READ = 'posts:read';
	public const PERMISSION_POSTS_WRITE = 'posts:write';
	public const PERMISSION_POSTS_DELETE = 'posts:delete';
	public const PERMISSION_PUBLISH_META = 'publish:meta';
	public const PERMISSION_PUBLISH_TIKTOK = 'publish:tiktok';
	public const PERMISSION_ACCOUNTS_READ = 'accounts:read';
	public const PERMISSION_ACCOUNTS_WRITE = 'accounts:write';
	public const PERMISSION_API_KEYS_READ = 'api_keys:read';
	public const PERMISSION_API_KEYS_WRITE = 'api_keys:write';
	public const PERMISSION_ALL = 'all';

	/** @var list<string> */
	private static array $valid_statuses = array(
		self::STATUS_ACTIVE,
		self::STATUS_INACTIVE,
		self::STATUS_REVOKED,
	);

	/** @var list<string> */
	private static array $valid_permissions = array(
		self::PERMISSION_POSTS_READ,
		self::PERMISSION_POSTS_WRITE,
		self::PERMISSION_POSTS_DELETE,
		self::PERMISSION_PUBLISH_META,
		self::PERMISSION_PUBLISH_TIKTOK,
		self::PERMISSION_ACCOUNTS_READ,
		self::PERMISSION_ACCOUNTS_WRITE,
		self::PERMISSION_API_KEYS_READ,
		self::PERMISSION_API_KEYS_WRITE,
		self::PERMISSION_ALL,
	);

	private ?int $id;
	private string $name;
	private string $api_key_hash;
	private string $status;
	/** @var list<string> */
	private array $permissions;
	private ?string $last_used_at;
	private string $created_at;
	private string $updated_at;

	/**
	 * @param array<string,mixed> $data
	 */
	public function __construct( array $data = array() ) {
		$this->id            = isset( $data['id'] ) ? (int) $data['id'] : null;
		$this->name          = trim( (string) ( $data['name'] ?? '' ) );
		$this->api_key_hash  = (string) ( $data['api_key'] ?? $data['api_key_hash'] ?? '' );
		$this->status        = (string) ( $data['status'] ?? self::STATUS_ACTIVE );
		$this->permissions   = $this->normalize_permissions( $data['permissions'] ?? array() );
		$this->last_used_at  = isset( $data['last_used_at'] ) ? (string) $data['last_used_at'] : null;
		$this->created_at    = (string) ( $data['created_at'] ?? gmdate( DATE_ATOM ) );
		$this->updated_at    = (string) ( $data['updated_at'] ?? gmdate( DATE_ATOM ) );

		$this->validate();
	}

	public function getId(): ?int {
		return $this->id;
	}

	public function getName(): string {
		return $this->name;
	}

	public function setName( string $name ): void {
		$this->name = trim( $name );
		$this->validateName();
	}

	public function getApiKeyHash(): string {
		return $this->api_key_hash;
	}

	public function setApiKeyHash( string $hash ): void {
		$this->api_key_hash = $hash;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function setStatus( string $status ): void {
		if ( ! in_array( $status, self::$valid_statuses, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid status: %s. Must be one of: %s', $status, implode( ', ', self::$valid_statuses ) )
			);
		}
		$this->status = $status;
	}

	/**
	 * @return list<string>
	 */
	public function getPermissions(): array {
		return $this->permissions;
	}

	/**
	 * @param list<string>|string[] $permissions
	 */
	public function setPermissions( array $permissions ): void {
		$this->permissions = $this->normalize_permissions( $permissions );
	}

	public function getLastUsedAt(): ?string {
		return $this->last_used_at;
	}

	public function setLastUsedAt( ?string $lastUsedAt ): void {
		$this->last_used_at = $lastUsedAt;
	}

	public function getCreatedAt(): string {
		return $this->created_at;
	}

	public function getUpdatedAt(): string {
		return $this->updated_at;
	}

	public function setUpdatedAt( string $updatedAt ): void {
		$this->updated_at = $updatedAt;
	}

	public function isActive(): bool {
		return self::STATUS_ACTIVE === $this->status;
	}

	/**
	 * @param list<string> $requiredPermissions
	 */
	public function hasPermissions( array $requiredPermissions ): bool {
		if ( in_array( self::PERMISSION_ALL, $this->permissions, true ) ) {
			return true;
		}

		foreach ( $requiredPermissions as $required ) {
			if ( ! in_array( $required, $this->permissions, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function toArray(): array {
		return array(
			'id'            => $this->id,
			'name'          => $this->name,
			'status'        => $this->status,
			'permissions'   => $this->permissions,
			'last_used_at'  => $this->last_used_at,
			'created_at'    => $this->created_at,
			'updated_at'    => $this->updated_at,
			'is_active'     => $this->isActive(),
		);
	}

	public static function getValidStatuses(): array {
		return self::$valid_statuses;
	}

	public static function getValidPermissions(): array {
		return self::$valid_permissions;
	}

	private function validate(): void {
		$this->validateName();
		$this->validateStatus();
		$this->validatePermissions();
	}

	private function validateName(): void {
		if ( '' === $this->name ) {
			throw new InvalidArgumentException( 'API key name cannot be empty.' );
		}
		if ( strlen( $this->name ) > 255 ) {
			throw new InvalidArgumentException( 'API key name cannot exceed 255 characters.' );
		}
	}

	private function validateStatus(): void {
		if ( ! in_array( $this->status, self::$valid_statuses, true ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid status: %s', $this->status )
			);
		}
	}

	private function validatePermissions(): void {
		foreach ( $this->permissions as $permission ) {
			if ( ! in_array( $permission, self::$valid_permissions, true ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Invalid permission: %s', $permission )
				);
			}
		}
	}

	private function normalize_permissions( mixed $permissions ): array {
		if ( is_string( $permissions ) ) {
			$permissions = json_decode( $permissions, true );
		}
		if ( ! is_array( $permissions ) ) {
			$permissions = array();
		}

		$permissions = array_map(
			static fn( mixed $permission ): string => trim( (string) $permission ),
			$permissions
		);

		return array_values( array_unique( array_filter( $permissions ) ) );
	}
}
