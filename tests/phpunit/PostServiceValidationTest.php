<?php
/**
 * Post service validation parity tests.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

use KatsarovDesign\SocialMediaScheduler\Repository\PostRepository;
use KatsarovDesign\SocialMediaScheduler\Repository\SocialAccountRepository;
use KatsarovDesign\SocialMediaScheduler\Service\PostService;
use KatsarovDesign\SocialMediaScheduler\Service\PostValidationException;

final class PostServiceValidationTest extends SmsTestCase {
	private PostService $service;
	private PostRepository $repository;
	private array $account;

	public function set_up(): void {
		parent::set_up();
		$this->account = $this->create_meta_account();
		$this->repository = new PostRepository();
		$this->service    = new PostService( $this->repository, new SocialAccountRepository() );
	}

	public function test_rejects_empty_caption_for_non_story_posts(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => '   ',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);
	}

	public function test_rejects_unknown_platform(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => 'Hello',
				'platform'        => 'myspace',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);
	}

	public function test_rejects_unknown_social_account_id_on_create(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => 'Unknown account',
				'platform'        => 'instagram',
				'socialAccountId' => 999999,
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);
	}

	public function test_rejects_unknown_social_account_id_on_update(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'Valid account',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
			)
		);

		$this->expectException( PostValidationException::class );

		$this->service->update(
			(int) $post['id'],
			array(
				'socialAccountId' => 999999,
			)
		);
	}

	public function test_future_published_intent_becomes_scheduled(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'Future post',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			)
		);

		$this->assertSame( 'SCHEDULED', $post['status'] );
	}

	public function test_rejects_system_managed_input_statuses(): void {
		$this->expectException( PostValidationException::class );

		$this->service->create(
			array(
				'caption'         => 'Manual scheduled status',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'SCHEDULED',
			)
		);
	}

	public function test_allows_missing_scheduled_at_for_published_status(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'No date',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'status'          => 'PUBLISHED',
			)
		);

		$this->assertSame( 'PUBLISHED', $post['status'] );
		$this->assertNotEmpty( $post['scheduledAt'] );
	}

	public function test_allows_deleting_scheduled_posts(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'Scheduled delete allowed',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			)
		);

		$this->service->delete( (int) $post['id'] );
		$this->assertNull( $this->repository->find_by_id( (int) $post['id'] ) );
	}

	public function test_allows_deleting_failed_posts(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'Failed delete allowed',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'scheduledAt'     => gmdate( DATE_ATOM, time() + DAY_IN_SECONDS ),
				'status'          => 'PUBLISHED',
			)
		);

		$this->repository->update( (int) $post['id'], array( 'status' => 'FAILED' ) );
		$this->service->delete( (int) $post['id'] );
		$this->assertNull( $this->repository->find_by_id( (int) $post['id'] ) );
	}

	public function test_rejects_deleting_non_deletable_statuses(): void {
		$post = $this->service->create(
			array(
				'caption'         => 'Draft delete blocked',
				'platform'        => 'instagram',
				'socialAccountId' => (int) $this->account['id'],
				'status'          => 'DRAFT',
			)
		);

		$this->expectException( PostValidationException::class );
		$this->expectExceptionMessage( 'Only scheduled or failed posts can be deleted.' );

		$this->service->delete( (int) $post['id'] );
	}
}
