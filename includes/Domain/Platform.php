<?php
/**
 * Supported social platforms.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum Platform: string {
	case INSTAGRAM = 'instagram';
	case FACEBOOK  = 'facebook';
	case TIKTOK    = 'tiktok';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return array_map( fn( $case ) => $case->value, self::cases() );
	}

	/**
	 * @return list<string>
	 */
	public static function video_only_values(): array {
		return array( self::TIKTOK->value );
	}

	/**
	 * @return list<string>
	 */
	public static function videoOnlyValues(): array {
		return self::video_only_values();
	}

	/**
	 * @return list<string>
	 */
	public static function story_values(): array {
		return array( self::INSTAGRAM->value, self::FACEBOOK->value );
	}

	/**
	 * @return list<string>
	 */
	public static function storyValues(): array {
		return self::story_values();
	}

	public static function is_valid( string $platform ): bool {
		return self::tryFrom( $platform ) !== null;
	}

	public static function is_video_only( string $platform ): bool {
		return self::tryFrom( $platform ) !== null && in_array( $platform, self::video_only_values(), true );
	}

	public static function isVideoOnly( string $platform ): bool {
		return self::is_video_only( $platform );
	}

	public static function supports_stories( string $platform ): bool {
		return self::tryFrom( $platform ) !== null && in_array( $platform, self::story_values(), true );
	}

	public static function supportsStories( string $platform ): bool {
		return self::supports_stories( $platform );
	}
}
