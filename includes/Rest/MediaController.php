<?php
/**
 * Media REST controller.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Rest;

use KatsarovDesign\SocialMediaScheduler\Service\MediaService;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MediaController extends Controller {
	private MediaService $service;

	public function __construct( ?MediaService $service = null ) {
		$this->service = $service ?? new MediaService();
	}

	public function upload( WP_REST_Request $request ): mixed {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return $this->error_response(
				new \KatsarovDesign\SocialMediaScheduler\Domain\ValidationError(
					__( 'No file was uploaded.', 'social-media-scheduler' )
				)
			);
		}

		try {
			$result = $this->service->upload( $files['file'] );

			return $this->response( $result );
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}

	public function delete( WP_REST_Request $request ): mixed {
		try {
			$this->service->delete_attachment( (int) $request['id'] );

			return $this->empty_response();
		} catch ( \Throwable $error ) {
			return $this->error_response( $error );
		}
	}
}
