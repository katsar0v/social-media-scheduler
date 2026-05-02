<?php
/**
 * API key REST authentication tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Domain\ApiKey;
use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\ApiKeyService;
use KatsarovDesign\SocialMediaScheduler\Service\PostService;

final class ApiKeyRestTest extends SmsTestCase {
	private int $admin_id;
	private int $account_id;

	public function set_up(): void {
		parent::set_up();

		$this->admin_id = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		get_user_by( 'id', $this->admin_id )->add_cap( Plugin::CAPABILITY );
		wp_set_current_user( $this->admin_id );

		$this->account_id = (int) $this->create_meta_account()['id'];

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	public function test_admin_can_create_key_and_key_can_read_posts_only(): void {
		( new PostService() )->create(
			array(
				'caption'         => 'API key readable post',
				'platform'        => 'instagram',
				'socialAccountId' => $this->account_id,
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			)
		);

		$response = $this->rest_request(
			'POST',
			'/sms/v1/api-keys',
			array(
				'name'        => 'Reader',
				'permissions' => array( ApiKey::PERMISSION_POSTS_READ ),
			),
			wp_create_nonce( 'wp_rest' )
		);

		$this->assertSame( 201, $this->response_status( $response ) );
		$data = $response->get_data();
		$this->assertSame( array( ApiKey::PERMISSION_POSTS_READ ), $data['permissions'] );
		$this->assertNotEmpty( $data['plaintext_key'] );

		wp_set_current_user( 0 );

		$list = $this->api_key_request( 'GET', '/sms/v1/posts', (string) $data['plaintext_key'] );
		$this->assertSame( 200, $this->response_status( $list ) );

		$create = $this->api_key_request(
			'POST',
			'/sms/v1/posts',
			(string) $data['plaintext_key'],
			array(
				'caption'         => 'Should fail',
				'platform'        => 'instagram',
				'socialAccountId' => $this->account_id,
			)
		);
		$this->assertSame( 403, $this->response_status( $create ) );
	}

	public function test_api_key_scopes_distinguish_reads_from_writes(): void {
		$key = ( new ApiKeyService() )->create_key(
			array(
				'name'        => 'API key reader',
				'permissions' => array( ApiKey::PERMISSION_API_KEYS_READ ),
			)
		);

		wp_set_current_user( 0 );

		$list = $this->api_key_request( 'GET', '/sms/v1/api-keys', (string) $key['plaintext_key'] );
		$this->assertSame( 200, $this->response_status( $list ) );

		$create = $this->api_key_request(
			'POST',
			'/sms/v1/api-keys',
			(string) $key['plaintext_key'],
			array(
				'name'        => 'Should fail',
				'permissions' => array( ApiKey::PERMISSION_POSTS_READ ),
			)
		);
		$this->assertSame( 403, $this->response_status( $create ) );
	}

	public function test_delete_post_requires_delete_scope(): void {
		$post = ( new PostService() )->create(
			array(
				'caption'         => 'Delete scope post',
				'platform'        => 'instagram',
				'socialAccountId' => $this->account_id,
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			)
		);

		$writer = ( new ApiKeyService() )->create_key(
			array(
				'name'        => 'Writer',
				'permissions' => array( ApiKey::PERMISSION_POSTS_WRITE ),
			)
		);

		$deleter = ( new ApiKeyService() )->create_key(
			array(
				'name'        => 'Deleter',
				'permissions' => array( ApiKey::PERMISSION_POSTS_DELETE ),
			)
		);

		wp_set_current_user( 0 );

		$forbidden = $this->api_key_request( 'DELETE', '/sms/v1/posts/' . (int) $post['id'], (string) $writer['plaintext_key'] );
		$this->assertSame( 403, $this->response_status( $forbidden ) );

		$deleted = $this->api_key_request( 'DELETE', '/sms/v1/posts/' . (int) $post['id'], (string) $deleter['plaintext_key'] );
		$this->assertSame( 204, $this->response_status( $deleted ) );
	}

	public function test_delete_scope_still_respects_post_status_restrictions(): void {
		$post = ( new PostService() )->create(
			array(
				'caption'         => 'Draft delete attempt',
				'platform'        => 'instagram',
				'socialAccountId' => $this->account_id,
				'status'          => 'DRAFT',
			)
		);

		$deleter = ( new ApiKeyService() )->create_key(
			array(
				'name'        => 'Deleter for draft',
				'permissions' => array( ApiKey::PERMISSION_POSTS_DELETE ),
			)
		);

		wp_set_current_user( 0 );

		$response = $this->api_key_request( 'DELETE', '/sms/v1/posts/' . (int) $post['id'], (string) $deleter['plaintext_key'] );
		$this->assertSame( 400, $this->response_status( $response ) );
	}

	public function test_external_posts_refresh_requires_accounts_write_scope(): void {
		( new SocialAccountRepository() )->delete( $this->account_id );

		$reader = ( new ApiKeyService() )->create_key(
			array(
				'name'        => 'External posts reader',
				'permissions' => array( ApiKey::PERMISSION_POSTS_READ ),
			)
		);

		$accounts_writer = ( new ApiKeyService() )->create_key(
			array(
				'name'        => 'Accounts writer',
				'permissions' => array( ApiKey::PERMISSION_ACCOUNTS_WRITE ),
			)
		);

		wp_set_current_user( 0 );

		$list = $this->api_key_request( 'GET', '/sms/v1/external-posts', (string) $reader['plaintext_key'] );
		$this->assertSame( 200, $this->response_status( $list ) );

		$forbidden = $this->api_key_request( 'POST', '/sms/v1/external-posts/refresh', (string) $reader['plaintext_key'] );
		$this->assertSame( 403, $this->response_status( $forbidden ) );

		$allowed = $this->api_key_request( 'POST', '/sms/v1/external-posts/refresh', (string) $accounts_writer['plaintext_key'] );
		$this->assertSame( 200, $this->response_status( $allowed ) );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 */
	private function api_key_request( string $method, string $route, string $api_key, array $params = array() ): WP_REST_Response|WP_Error {
		$request = new WP_REST_Request( $method, $route );
		$request->set_header( 'X-API-KEY', $api_key );
		$this->apply_params( $request, $method, $params );

		return rest_do_request( $request );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 */
	private function rest_request( string $method, string $route, array $params = array(), ?string $nonce = null ): WP_REST_Response|WP_Error {
		$request = new WP_REST_Request( $method, $route );
		if ( null !== $nonce ) {
			$request->set_header( 'X-WP-Nonce', $nonce );
		}
		$this->apply_params( $request, $method, $params );

		return rest_do_request( $request );
	}

	/**
	 * @param array<string,mixed> $params Request params.
	 */
	private function apply_params( WP_REST_Request $request, string $method, array $params ): void {
		if ( in_array( strtoupper( $method ), array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$request->set_body_params( $params );
			return;
		}

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
	}

	private function response_status( WP_REST_Response|WP_Error $response ): int {
		if ( $response instanceof WP_Error ) {
			return (int) ( $response->get_error_data()['status'] ?? 500 );
		}

		return $response->get_status();
	}
}
