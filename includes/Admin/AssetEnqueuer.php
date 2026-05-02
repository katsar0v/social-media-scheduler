<?php
/**
 * Admin asset registration.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

namespace KatsarovDesign\SocialMediaScheduler\Admin;

use KatsarovDesign\SocialMediaScheduler\Plugin;
use KatsarovDesign\SocialMediaScheduler\Service\SettingsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetEnqueuer {
	public static function enqueue( string $hook_suffix ): void {
		if ( ! AdminMenu::is_plugin_page() ) {
			return;
		}

		wp_enqueue_style( 'sms-admin', SMS_PLUGIN_URL . 'assets/css/admin.css', array(), SMS_PLUGIN_VERSION );

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$settings = ( new SettingsService() )->get();
		$config   = array(
			'root'          => esc_url_raw( rest_url( 'sms/v1/' ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'currentUserId' => get_current_user_id(),
			'adminUrl'      => admin_url( 'admin.php' ),
			'calendarUrl'   => admin_url( 'admin.php?page=' . AdminMenu::PAGE_CALENDAR ),
			'settings'      => array(
				'timezone'          => (string) ( $settings['timezone'] ?? '' ),
				'defaultPlatform'   => (string) ( $settings['defaultPlatform'] ?? '' ),
				'calendarWeekStart' => (int) ( $settings['calendarWeekStart'] ?? 1 ),
			),
		);

		if ( AdminMenu::PAGE_CALENDAR === $page ) {
			$config['monthNames'] = array();
			for ( $i = 1; $i <= 12; $i++ ) {
				$monthName = wp_date( 'F', mktime( 0, 0, 0, $i, 1 ) );
				$config['monthNames'][] = mb_convert_case( $monthName, MB_CASE_TITLE, 'UTF-8' );
			}
		}

		if ( AdminMenu::PAGE_CALENDAR === $page ) {
			self::enqueue_script( 'sms-calendar', 'assets/js/calendar.js', array( 'wp-i18n' ), 'smsCalendar', $config );
		}

		if ( AdminMenu::PAGE_COMPOSER === $page ) {
			$config['postId'] = isset( $_GET['post'] )
				? absint( wp_unslash( $_GET['post'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				: 0;
			$config['i18n']   = array(
				'savingAsDraft'       => __( 'Saving as draft...', 'social-media-scheduler' ),
				'draftSaved'          => __( 'Draft saved.', 'social-media-scheduler' ),
				'savingAndScheduling' => __( 'Saving and scheduling...', 'social-media-scheduler' ),
				'savingAndPublishing' => __( 'Saving and publishing...', 'social-media-scheduler' ),
				'publishingErrors'    => __( 'Publishing errors', 'social-media-scheduler' ),
				'fixFieldsAndRetry'   => __( 'Fix the fields below, then publish or schedule the post again.', 'social-media-scheduler' ),
				'publishedPost'       => __( 'Published post', 'social-media-scheduler' ),
				/* translators: %s: post status label. */
				'publishedReadOnly'   => __( 'Status: %s. Published posts are read-only.', 'social-media-scheduler' ),
				/* translators: %s: post status label. */
				'postStatus'          => __( 'Post status: %s', 'social-media-scheduler' ),
				'readOnlyCurrent'     => __( 'This post is read-only in its current status.', 'social-media-scheduler' ),
				'editPostEyebrow'     => __( 'Edit post', 'social-media-scheduler' ),
				'editPostTitle'       => __( 'Edit Post', 'social-media-scheduler' ),
				'loadingPost'         => __( 'Loading post...', 'social-media-scheduler' ),
				'postReadOnly'        => __( 'This post is read-only.', 'social-media-scheduler' ),
				'deleteConfirm'       => __( 'Are you sure you want to delete this post? This action cannot be undone.', 'social-media-scheduler' ),
				'deletingPost'        => __( 'Deleting post...', 'social-media-scheduler' ),
				'postDeleted'         => __( 'Post deleted.', 'social-media-scheduler' ),
				'deleteFailed'        => __( 'Failed to delete post.', 'social-media-scheduler' ),
				'deleteNotAllowed'    => __( 'Only scheduled or failed posts can be deleted.', 'social-media-scheduler' ),
			);
			wp_enqueue_media();
			self::enqueue_script( 'sms-composer', 'assets/js/composer.js', array( 'wp-i18n', 'media-views' ), 'smsComposer', $config );
		}

		if ( AdminMenu::PAGE_ACCOUNTS === $page ) {
			self::enqueue_script( 'sms-accounts', 'assets/js/accounts.js', array( 'wp-i18n' ), 'smsAccounts', $config );
		}

		if ( AdminMenu::PAGE_SETTINGS === $page ) {
			self::enqueue_script( 'sms-settings', 'assets/js/settings.js', array( 'wp-i18n' ), 'smsSettings', $config );
			wp_enqueue_script( 'jquery' );
		}
	}

	/**
	 * @param list<string>         $deps Script dependencies.
	 * @param array<string,mixed>  $config Localized config.
	 */
	private static function enqueue_script( string $handle, string $path, array $deps, string $object_name, array $config ): void {
		wp_enqueue_script( $handle, SMS_PLUGIN_URL . $path, $deps, SMS_PLUGIN_VERSION, true );
		wp_localize_script( $handle, $object_name, $config );
		wp_set_script_translations( $handle, Plugin::TEXT_DOMAIN, SMS_PLUGIN_DIR . 'languages' );
	}
}
