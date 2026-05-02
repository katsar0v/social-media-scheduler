<?php
/**
 * Publish service media cleanup tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Repository\PostMediaRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\PublishResultRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\PublishService;

final class PublishServiceMediaCleanupTest extends SmsTestCase {
	private PostRepository $post_repository;
	private PostMediaRepository $post_media_repository;
	private PublishResultRepository $publish_result_repository;
	private PublishService $publish_service;
	private array $meta_account;

	public function set_up(): void {
		parent::set_up();

		$this->post_repository           = new PostRepository();
		$this->post_media_repository     = new PostMediaRepository();
		$this->publish_result_repository = new PublishResultRepository();
		$this->publish_service           = new PublishService( $this->post_repository, null, $this->publish_result_repository );
		$this->meta_account              = $this->create_meta_account();
	}

	public function test_facebook_scheduled_publish_deletes_media_immediately(): void {
		$post = $this->post_repository->create(
			array(
				'title'           => 'Facebook Scheduled Cleanup',
				'caption'         => 'Schedule this on Facebook',
				'platform'        => 'facebook',
				'socialAccountId' => (int) $this->meta_account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'SCHEDULED',
			)
		);
		$attachment_id = $this->create_image_attachment( 'sms-facebook-cleanup.jpg' );
		$this->post_media_repository->attach( (int) $post['id'], $attachment_id );

		$filter = array( $this, 'fake_facebook_scheduled_publish_http_response' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$results = $this->publish_service->publish_to_meta(
				array(
					'postId'          => (int) $post['id'],
					'targetPlatforms' => array( 'facebook' ),
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertCount( 1, $results );
		$this->assertTrue( $results[0]['isScheduled'] );
		$this->assertNull( get_post( $attachment_id ) );
		$this->assertCount( 0, $this->post_media_repository->find_by_post_id( (int) $post['id'] ) );

		$stored_results = $this->publish_result_repository->find_by_post_id( (int) $post['id'] );
		$this->assertCount( 1, $stored_results );
		$this->assertSame( 'scheduled', $stored_results[0]['status'] );
	}

	public function test_instagram_deferred_publish_deletes_media_only_after_success(): void {
		$post = $this->post_repository->create(
			array(
				'title'           => 'Instagram Deferred Cleanup',
				'caption'         => 'Publish to Instagram later',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->meta_account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'SCHEDULED',
			)
		);
		$attachment_id = $this->create_image_attachment( 'sms-instagram-cleanup.jpg' );
		$this->post_media_repository->attach( (int) $post['id'], $attachment_id );

		$deferred = $this->publish_service->publish_to_meta(
			array(
				'postId'          => (int) $post['id'],
				'targetPlatforms' => array( 'instagram' ),
			)
		);

		$this->assertCount( 1, $deferred );
		$this->assertTrue( $deferred[0]['isScheduled'] );
		$this->assertNotNull( get_post( $attachment_id ) );
		$this->assertCount( 1, $this->post_media_repository->find_by_post_id( (int) $post['id'] ) );

		$this->post_repository->update(
			(int) $post['id'],
			array(
				'scheduledAt' => gmdate( DATE_ATOM, time() - MINUTE_IN_SECONDS ),
			)
		);

		$filter = array( $this, 'fake_instagram_publish_http_response' );
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$published = $this->publish_service->publish_to_meta(
				array(
					'postId'          => (int) $post['id'],
					'targetPlatforms' => array( 'instagram' ),
				)
			);
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertCount( 1, $published );
		$this->assertFalse( $published[0]['isScheduled'] );
		$this->assertNull( get_post( $attachment_id ) );
		$this->assertCount( 0, $this->post_media_repository->find_by_post_id( (int) $post['id'] ) );
	}

	public function test_tiktok_deferred_publish_does_not_create_duplicate_pending_results(): void {
		$tiktok_account = ( new SocialAccountRepository() )->upsert(
			array(
				'platform'       => 'tiktok',
				'providerUserId' => 'phpunit-tiktok-' . wp_generate_uuid4(),
				'accountName'    => 'PHPUnit TikTok Account',
				'accessToken'    => 'tiktok-access-' . wp_generate_uuid4(),
				'refreshToken'   => 'tiktok-refresh-' . wp_generate_uuid4(),
				'tokenExpiresAt' => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'scopes'         => 'video.publish',
				'metadata'       => wp_json_encode( array( 'openId' => 'phpunit-open-id' ) ),
			)
		);

		$post = $this->post_repository->create(
			array(
				'title'           => 'TikTok Deferred Pending Dedup',
				'caption'         => 'Deferred TikTok publish',
				'platform'        => 'tiktok',
				'socialAccountId' => (int) $tiktok_account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'SCHEDULED',
			)
		);

		$this->publish_service->publish_to_tiktok( array( 'postId' => (int) $post['id'] ) );
		$this->publish_service->publish_to_tiktok( array( 'postId' => (int) $post['id'] ) );

		$results = $this->publish_result_repository->find_by_post_id( (int) $post['id'] );
		$pending = array_filter(
			$results,
			static fn ( array $result ): bool => 'tiktok' === (string) $result['platform'] && 'pending' === (string) $result['status']
		);

		$this->assertCount( 1, $pending );
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 */
	public function fake_facebook_scheduled_publish_http_response( mixed $preempt, array $args, string $url ): array|false {
		if ( ! str_contains( $url, 'graph.facebook.com/' ) ) {
			return false;
		}

		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );

		if ( 'POST' === $method && str_contains( $url, '/phpunit-page/photos' ) ) {
			return $this->http_response(
				array(
					'id'      => 'phpunit-photo-id',
					'post_id' => 'phpunit-page_12345',
				)
			);
		}

		if ( 'GET' === $method && str_contains( $url, '/phpunit-page_12345' ) && str_contains( $url, 'permalink_url' ) ) {
			return $this->http_response( array( 'permalink_url' => 'https://www.facebook.com/phpunit-page/posts/12345' ) );
		}

		$this->fail( 'Unexpected Facebook publish request: ' . $method . ' ' . $url );
	}

	/**
	 * @param array<string,mixed> $args Request args.
	 */
	public function fake_instagram_publish_http_response( mixed $preempt, array $args, string $url ): array|false {
		if ( ! str_contains( $url, 'graph.facebook.com/' ) ) {
			return false;
		}

		$method = strtoupper( (string) ( $args['method'] ?? 'GET' ) );

		if ( 'POST' === $method && str_contains( $url, '/phpunit-ig/media' ) && ! str_contains( $url, '/media_publish' ) ) {
			return $this->http_response( array( 'id' => 'ig-container-1' ) );
		}

		if ( 'GET' === $method && str_contains( $url, '/ig-container-1' ) ) {
			return $this->http_response(
				array(
					'status_code' => 'FINISHED',
					'status'      => 'FINISHED',
				)
			);
		}

		if ( 'POST' === $method && str_contains( $url, '/phpunit-ig/media_publish' ) ) {
			return $this->http_response( array( 'id' => 'ig-media-1' ) );
		}

		if ( 'GET' === $method && str_contains( $url, '/ig-media-1' ) && str_contains( $url, 'permalink' ) ) {
			return $this->http_response( array( 'permalink' => 'https://www.instagram.com/p/ig-media-1/' ) );
		}

		$this->fail( 'Unexpected Instagram publish request: ' . $method . ' ' . $url );
	}

	private function create_image_attachment( string $filename ): int {
		$upload = wp_upload_bits( $filename, null, 'phpunit-media-cleanup' );
		$this->assertIsArray( $upload );
		$this->assertEmpty( $upload['error'] );

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			$this->fail( 'Failed to create attachment: ' . $attachment_id->get_error_message() );
		}

		$this->assertIsInt( $attachment_id );

		return (int) $attachment_id;
	}

	/**
	 * @param array<string,mixed> $body Response body.
	 * @return array<string,mixed>
	 */
	private function http_response( array $body, int $status = 200 ): array {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => 200 === $status ? 'OK' : 'Bad Request',
			),
			'cookies'  => array(),
		);
	}
}
