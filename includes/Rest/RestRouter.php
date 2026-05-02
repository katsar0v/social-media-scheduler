<?php
/**
 * REST route registration.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Plugin;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestRouter {
	public const NAMESPACE = 'sms/v1';

	public static function register_routes(): void {
		$posts          = new PostsController();
		$settings       = new SettingsController();
		$media          = new MediaController();
		$publish        = new PublishController();
		$external_posts = new ExternalPostsController();
		$auth           = new AuthController();
		$api_keys       = new ApiKeyController();

		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $posts, 'list' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $posts, 'create' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $posts, 'get' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $posts, 'update' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $posts, 'delete' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/media',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $posts, 'attach_media' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<id>\d+)/media/reorder',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $posts, 'reorder_media' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/posts/(?P<postId>\d+)/media/(?P<mediaId>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $posts, 'remove_media' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $settings, 'get' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $settings, 'update' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/media/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $media, 'delete' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/meta',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $publish, 'publish_meta' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/tiktok',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $publish, 'publish_tiktok' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/(?P<postId>\d+)/results',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $publish, 'results' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/external-posts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $external_posts, 'list' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/external-posts/refresh',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $external_posts, 'refresh' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/accounts',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $auth, 'accounts' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/accounts/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $auth, 'delete_account' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/meta/callback',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $auth, 'meta_callback' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/auth/tiktok/callback',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $auth, 'tiktok_callback' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/api-keys',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $api_keys, 'list_items' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $api_keys, 'create_item' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/api-keys/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $api_keys, 'get_item' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'PUT,PATCH',
					'callback'            => array( $api_keys, 'update_item' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $api_keys, 'delete_item' ),
					'permission_callback' => array( self::class, 'permission_callback' ),
				),
			)
		);
	}

	public static function register_admin_post_actions(): void {
		$auth = new AuthController();
		$api_keys = new ApiKeyController();
		add_action( 'admin_post_sms_oauth_meta_init', array( $auth, 'meta_init' ) );
		add_action( 'admin_post_sms_oauth_tiktok_init', array( $auth, 'tiktok_init' ) );
		add_action( 'wp_ajax_sms_save_api_key', array( $api_keys, 'ajax_save_api_key' ) );
		add_action( 'wp_ajax_sms_delete_api_key', array( $api_keys, 'ajax_delete_api_key' ) );
	}

	public static function permission_callback( WP_REST_Request $request ): true|WP_Error {
		// First, try API key authentication
		$api_key_header = $request->get_header( 'X-API-KEY' );
		if ( ! empty( $api_key_header ) ) {
			$auth = new ApiKeyAuth();
			if ( $auth->authenticate( $request ) ) {
				// API key authenticated, check if it has required permissions based on endpoint
				$route = $request->get_route();
				$method = $request->get_method();

				$required_permissions = self::get_required_permissions_for_route( $route, $method );
				if ( ! $auth->check_permissions( $required_permissions ) ) {
					return new WP_Error(
						'sms_api_key_insufficient_permissions',
						__( 'API key does not have required permissions.', 'social-media-scheduler' ),
						array( 'status' => 403 )
					);
				}
				return true;
			}
			return new WP_Error(
				'sms_api_key_invalid',
				__( 'Invalid or inactive API key.', 'social-media-scheduler' ),
				array( 'status' => 401 )
			);
		}

		// Fall back to nonce/capability check
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'sms_rest_invalid_nonce',
				__( 'A valid REST nonce is required.', 'social-media-scheduler' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			return new WP_Error(
				'sms_rest_forbidden',
				__( 'You are not allowed to manage the social scheduler.', 'social-media-scheduler' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Determine required permissions for a route.
	 */
	private static function get_required_permissions_for_route( string $route, string $method ): array {
		// Posts routes
		if ( preg_match( '#^/sms/v1/posts$#', $route ) ) {
			if ( 'GET' === $method ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_READ );
			}
			if ( 'POST' === $method ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_WRITE );
			}
		}
		if ( preg_match( '#^/sms/v1/posts/\d+$#', $route ) ) {
			if ( 'GET' === $method ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_READ );
			}
			if ( in_array( $method, array( 'PUT', 'PATCH' ), true ) ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_WRITE );
			}
			if ( 'DELETE' === $method ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_DELETE );
			}
		}
		if ( preg_match( '#^/sms/v1/posts/\d+/media#', $route ) ) {
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_WRITE );
		}

		// Publish routes
		if ( preg_match( '#^/sms/v1/publish/\d+/results$#', $route ) ) {
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_READ );
		}

		if ( preg_match( '#^/sms/v1/publish/#', $route ) ) {
			if ( strpos( $route, '/meta' ) !== false ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_PUBLISH_META );
			}
			if ( strpos( $route, '/tiktok' ) !== false ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_PUBLISH_TIKTOK );
			}
		}

		// Settings routes
		if ( preg_match( '#^/sms/v1/settings$#', $route ) ) {
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_ALL );
		}

		// Media routes
		if ( preg_match( '#^/sms/v1/media/#', $route ) ) {
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_WRITE );
		}

		// External posts routes
		if ( preg_match( '#^/sms/v1/external-posts/refresh$#', $route ) ) {
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_ACCOUNTS_WRITE );
		}

		if ( preg_match( '#^/sms/v1/external-posts$#', $route ) ) {
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_POSTS_READ );
		}

		// Auth routes (accounts)
		if ( preg_match( '#^/sms/v1/auth/#', $route ) ) {
			if ( 'DELETE' === $method ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_ACCOUNTS_WRITE );
			}
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_ACCOUNTS_READ );
		}

		// API keys routes
		if ( preg_match( '#^/sms/v1/api-keys#', $route ) ) {
			if ( 'GET' === $method ) {
				return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_API_KEYS_READ );
			}
			return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_API_KEYS_WRITE );
		}

		// Unknown routes require full access instead of silently allowing any valid key.
		return array( \KatsarovDesign\SocialMediaScheduler\Domain\ApiKey::PERMISSION_ALL );
	}
}
