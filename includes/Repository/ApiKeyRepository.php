<?php
/**
 * API Key repository - database operations.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Repository;

use KatsarovDesign\SocialMediaScheduler\Domain\ApiKey;
use KatsarovDesign\SocialMediaScheduler\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for API key CRUD operations.
 */
final class ApiKeyRepository {
	/**
	 * Create a new API key.
	 *
	 * @param array{name:string,permissions:list<string>,status?:string} $data API key input.
	 * @return array<string,mixed> Created API key data with plaintext key returned once.
	 */
	public function create( array $data ): array {
		global $wpdb;

		$name        = trim( (string) ( $data['name'] ?? '' ) );
		$status      = (string) ( $data['status'] ?? ApiKey::STATUS_ACTIVE );
		$permissions = is_array( $data['permissions'] ?? null ) ? $data['permissions'] : array();

		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'API key name is required.' );
		}

		$plaintext_key = $this->generate_key();
		$key_hash      = $this->hash_key( $plaintext_key );
		$api_key       = new ApiKey(
			array(
				'name'        => $name,
				'api_key'     => $key_hash,
				'status'      => $status,
				'permissions' => $permissions,
			)
		);

		$inserted = $wpdb->insert(
			Installer::table_name( 'sms_api_key' ),
			array(
				'name'         => $api_key->getName(),
				'api_key'      => $api_key->getApiKeyHash(),
				'status'       => $api_key->getStatus(),
				'permissions'  => wp_json_encode( $api_key->getPermissions() ),
				'created_at'   => $this->now_mysql(),
				'updated_at'   => $this->now_mysql(),
				'last_used_at' => null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			throw new \RuntimeException( 'Failed to create API key.' );
		}

		$created = $this->find_by_id( (int) $wpdb->insert_id );
		if ( null === $created ) {
			throw new \RuntimeException( 'Created API key could not be loaded.' );
		}

		return array_merge(
			$created,
			array(
				'plaintext_key' => $plaintext_key,
			)
		);
	}

	/**
	 * Find API key by ID.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_by_id( int $id ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_api_key' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, name, api_key as api_key_hash, status, permissions, last_used_at, created_at, updated_at FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		return $row ? $this->normalize_row( $row ) : null;
	}

	/**
	 * Find API key by its plaintext key.
	 *
	 * @return array<string,mixed>|null
	 */
	public function find_by_api_key( string $plaintext_key ): ?array {
		global $wpdb;

		$table = Installer::table_name( 'sms_api_key' );
		$rows  = $wpdb->get_results(
			"SELECT id, name, api_key as api_key_hash, status, permissions, last_used_at, created_at, updated_at FROM {$table} WHERE status = 'active'",
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			if ( $this->verify_key( $plaintext_key, (string) $row['api_key_hash'] ) ) {
				return $this->normalize_row( $row );
			}
		}

		return null;
	}

	/**
	 * List API keys with optional filters.
	 *
	 * @param array{status?:string,page?:int,per_page?:int} $filters Query filters.
	 * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function list( array $filters = array() ): array {
		global $wpdb;

		$table  = Installer::table_name( 'sms_api_key' );
		$where  = array();
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $filters['status'];
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$total_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $total_sql, $params ) : $total_sql );
		$page      = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page  = min( max( 1, (int) ( $filters['per_page'] ?? 20 ) ), 100 );
		$offset    = ( $page - 1 ) * $per_page;

		$items_sql = $wpdb->prepare(
			"SELECT id, name, api_key as api_key_hash, status, permissions, last_used_at, created_at, updated_at FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			array_merge( $params, array( $per_page, $offset ) )
		);
		$rows      = $wpdb->get_results( $items_sql, ARRAY_A );
		$items     = array();

		foreach ( $rows as $row ) {
			$items[] = $this->normalize_row( $row );
		}

		return array(
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Update an API key.
	 *
	 * @param array{name?:string,status?:string,permissions?:list<string>} $data API key fields.
	 * @return array<string,mixed>|null
	 */
	public function update( int $id, array $data ): ?array {
		global $wpdb;

		$existing = $this->find_by_id( $id );
		if ( ! $existing ) {
			return null;
		}

		$update_data = array(
			'updated_at' => $this->now_mysql(),
		);
		$formats     = array( '%s' );

		if ( array_key_exists( 'name', $data ) ) {
			$update_data['name'] = trim( (string) $data['name'] );
			$formats[]           = '%s';
		}

		if ( array_key_exists( 'status', $data ) ) {
			$update_data['status'] = (string) $data['status'];
			$formats[]             = '%s';
		}

		if ( array_key_exists( 'permissions', $data ) ) {
			$update_data['permissions'] = wp_json_encode( $data['permissions'] );
			$formats[]                  = '%s';
		}

		new ApiKey(
			array(
				'name'        => $update_data['name'] ?? $existing['name'],
				'status'      => $update_data['status'] ?? $existing['status'],
				'permissions' => array_key_exists( 'permissions', $data ) ? $data['permissions'] : $existing['permissions'],
			)
		);

		$updated = $wpdb->update(
			Installer::table_name( 'sms_api_key' ),
			$update_data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false === $updated ? null : $this->find_by_id( $id );
	}

	/**
	 * Delete an API key.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		$deleted = $wpdb->delete(
			Installer::table_name( 'sms_api_key' ),
			array( 'id' => $id ),
			array( '%d' )
		);

		return $deleted > 0;
	}

	/**
	 * Update last used timestamp for an API key.
	 */
	public function update_last_used( int $id ): void {
		global $wpdb;

		$wpdb->update(
			Installer::table_name( 'sms_api_key' ),
			array( 'last_used_at' => $this->now_mysql() ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Generate a cryptographically secure API key.
	 */
	public function generate_key(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hash an API key for storage.
	 */
	public function hash_key( string $key ): string {
		return password_hash( $key, PASSWORD_DEFAULT );
	}

	/**
	 * Verify a plaintext key against a hash.
	 */
	public function verify_key( string $plaintext, string $hash ): bool {
		return password_verify( $plaintext, $hash );
	}

	/**
	 * Normalize a database row to consistent format.
	 *
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private function normalize_row( array $row ): array {
		return array(
			'id'           => (int) $row['id'],
			'name'         => (string) $row['name'],
			'status'       => (string) $row['status'],
			'permissions'  => json_decode( (string) $row['permissions'], true ) ?: array(),
			'last_used_at' => $row['last_used_at'],
			'created_at'   => (string) $row['created_at'],
			'updated_at'   => (string) $row['updated_at'],
			'is_active'    => ApiKey::STATUS_ACTIVE === $row['status'],
		);
	}

	private function now_mysql(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
