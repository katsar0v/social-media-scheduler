<?php
/**
 * API Key authentication middleware for REST API.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Service\ApiKeyService;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Key authentication middleware.
 *
 * Validates API keys from X-API-KEY header and checks permissions.
 */
final class ApiKeyAuth {
	/**
	 * @var ApiKeyService
	 */
	private ApiKeyService $service;

	/**
	 * @var array<string,mixed>|null
	 */
	private ?array $authenticated_key = null;

	/**
	 * Header name for API key.
	 */
	public const HEADER_NAME = 'X-API-KEY';
	private const MAX_FAILED_ATTEMPTS = 5;
	private const ATTEMPT_TTL = 15 * MINUTE_IN_SECONDS;
	private const LOCKOUT_TTL = 15 * MINUTE_IN_SECONDS;
	private const FAIL_KEY_PREFIX = 'sms_api_key_auth_fail_';
	private const LOCK_KEY_PREFIX = 'sms_api_key_auth_lock_';

	public function __construct( ?ApiKeyService $service = null ) {
		$this->service = $service ?? new ApiKeyService();
	}

	/**
	 * Authenticate the request using API key.
	 *
	 * @param WP_REST_Request $request
	 * @return bool True if authenticated, false otherwise.
	 */
	public function authenticate( WP_REST_Request $request ): bool {
		$throttle_key = $this->throttle_key();
		if ( $this->is_locked_out( $throttle_key ) ) {
			return false;
		}

		$api_key = $this->get_api_key_from_request( $request );

		if ( empty( $api_key ) ) {
			return false;
		}

		$key_data = $this->service->authenticate( $api_key );

		if ( ! $key_data ) {
			$this->record_failed_attempt( $throttle_key );
			return false;
		}

		if ( ! $key_data['is_active'] ) {
			$this->record_failed_attempt( $throttle_key );
			return false;
		}

		$this->clear_failed_attempts( $throttle_key );
		$this->authenticated_key = $key_data;

		return true;
	}

	/**
	 * Check if the authenticated key has required permissions.
	 *
	 * @param list<string> $required_permissions
	 */
	public function check_permissions( array $required_permissions ): bool {
		if ( empty( $this->authenticated_key ) ) {
			return false;
		}

		return $this->service->validate_permissions( $this->authenticated_key, $required_permissions );
	}

	/**
	 * Get the authenticated key data.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_authenticated_key(): ?array {
		return $this->authenticated_key;
	}

	/**
	 * Extract API key from request.
	 */
	private function get_api_key_from_request( WP_REST_Request $request ): string {
		// Check header first
		$header_key = $request->get_header( self::HEADER_NAME );
		if ( ! empty( $header_key ) ) {
			return trim( $header_key );
		}

		return '';
	}

	private function throttle_key(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		return md5( $ip . '|' . $agent );
	}

	private function is_locked_out( string $throttle_key ): bool {
		return (bool) get_transient( self::LOCK_KEY_PREFIX . $throttle_key );
	}

	private function record_failed_attempt( string $throttle_key ): void {
		$fail_key = self::FAIL_KEY_PREFIX . $throttle_key;
		$attempts = (int) get_transient( $fail_key );
		++$attempts;

		set_transient( $fail_key, $attempts, self::ATTEMPT_TTL );

		if ( $attempts >= self::MAX_FAILED_ATTEMPTS ) {
			set_transient( self::LOCK_KEY_PREFIX . $throttle_key, 1, self::LOCKOUT_TTL );
		}
	}

	private function clear_failed_attempts( string $throttle_key ): void {
		delete_transient( self::FAIL_KEY_PREFIX . $throttle_key );
		delete_transient( self::LOCK_KEY_PREFIX . $throttle_key );
	}

	/**
	 * Create a permission callback for REST routes.
	 *
	 * Usage: 'permission_callback' => [ ApiKeyAuth::class, 'permission_callback' ]
	 *
	 * @param list<string> $required_permissions
	 */
	public static function permission_callback( array $required_permissions = array() ): callable {
		return function ( WP_REST_Request $request ) use ( $required_permissions ) {
			$auth = new self();

			if ( ! $auth->authenticate( $request ) ) {
				return new WP_Error(
					'rest_api_key_invalid',
					__( 'Invalid or inactive API key.', 'social-media-scheduler' ),
					array( 'status' => 401 )
				);
			}

			if ( ! empty( $required_permissions ) && ! $auth->check_permissions( $required_permissions ) ) {
				return new WP_Error(
					'rest_api_key_insufficient_permissions',
					__( 'API key does not have required permissions.', 'social-media-scheduler' ),
					array( 'status' => 403 )
				);
			}

			return true;
		};
	}

	/**
	 * Get permission callback for posts:read.
	 */
	public static function posts_read_permission(): callable {
		return self::permission_callback( array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_READ ) );
	}

	/**
	 * Get permission callback for posts:write.
	 */
	public static function posts_write_permission(): callable {
		return self::permission_callback( array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_WRITE ) );
	}

	/**
	 * Get permission callback for publish:meta.
	 */
	public static function publish_meta_permission(): callable {
		return self::permission_callback( array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_PUBLISH_META ) );
	}

	/**
	 * Get permission callback for publish:tiktok.
	 */
	public static function publish_tiktok_permission(): callable {
		return self::permission_callback( array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_PUBLISH_TIKTOK ) );
	}
}
