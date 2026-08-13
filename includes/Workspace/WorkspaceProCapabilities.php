<?php
/**
 * Central Free ↔ Pro capability checks for Workspace UI.
 *
 * Detection uses the public Free/Pro bridge filters — never plugin file paths
 * or hardcoded plugin slugs.
 *
 * @package HvnlyNab\Workspace
 * @since   3.7.4
 */

namespace HvnlyNab\Workspace;

defined( 'ABSPATH' ) || exit;

/**
 * Workspace Pro capability helpers (branding gate, etc.).
 *
 * @since 3.7.4
 */
final class WorkspaceProCapabilities {

	public const LOCK_COMING_IN_PRO    = 'coming_in_pro';
	public const LOCK_LICENSE_REQUIRED = 'license_required';

	/**
	 * Whether Havenlytics Pro is commercially active on this site.
	 *
	 * Order:
	 * 1. HVNLY_IS_PRO constant (Pro may define when unlocked)
	 * 2. hvnly_pro_is_active filter
	 * 3. hvnly_is_pro filter
	 *
	 * @return bool
	 */
	public static function is_pro_active(): bool {
		if ( defined( 'HVNLY_IS_PRO' ) && HVNLY_IS_PRO ) {
			return true;
		}

		/**
		 * Filter whether Havenlytics Pro is active (primary Free check).
		 *
		 * @since 3.7.4
		 *
		 * @param bool $active Default false.
		 */
		if ( (bool) apply_filters( 'hvnly_pro_is_active', false ) ) {
			return true;
		}

		/**
		 * Filter whether this site is treated as Pro (alias / legacy).
		 *
		 * @since 3.7.4
		 *
		 * @param bool $is_pro Default false.
		 */
		return (bool) apply_filters( 'hvnly_is_pro', false );
	}

	/**
	 * Whether a named Pro feature is enabled.
	 *
	 * @param string $feature_id Feature id (e.g. workspace.branding).
	 * @param bool   $default    Default when Pro has not answered.
	 * @return bool
	 */
	public static function is_feature_enabled( string $feature_id, bool $default = false ): bool {
		$feature_id = trim( $feature_id );
		if ( '' === $feature_id ) {
			return false;
		}

		/**
		 * Filter whether a Pro feature is enabled for this site.
		 *
		 * @since 3.7.4
		 *
		 * @param bool   $enabled    Default.
		 * @param string $feature_id Feature id.
		 */
		return (bool) apply_filters( 'hvnly_feature_enabled', $default, $feature_id );
	}

	/**
	 * Whether Workspace Branding (white-label) controls are editable.
	 *
	 * - Core-only → false
	 * - Pro active → true by default
	 * - Pro / extensions may force-disable via {@see 'hvnly_workspace_branding_enabled'}
	 *   or by mapping feature `workspace.branding` (when that returns false while Pro is active,
	 *   callers should use {@see branding_lock_reason()} for Case 3 messaging).
	 *
	 * @return bool
	 */
	public static function is_branding_enabled(): bool {
		$is_pro = self::is_pro_active();

		/*
		 * Prefer an explicit FeatureBridge answer when Pro registers
		 * workspace.branding. Unmapped features return the $default (false).
		 * Then fall through to Pro-active default so Case 2 stays unlocked.
		 */
		$by_feature = self::is_feature_enabled( 'workspace.branding', false );
		$default    = $by_feature ? true : $is_pro;

		/**
		 * Filter whether Workspace Branding UI is editable.
		 *
		 * @since 3.7.4
		 *
		 * @param bool $enabled Default based on Pro / feature.
		 */
		return (bool) apply_filters( 'hvnly_workspace_branding_enabled', $default );
	}

	/**
	 * Lock reason when branding is disabled.
	 *
	 * @return string Empty when enabled; coming_in_pro | license_required | custom.
	 */
	public static function branding_lock_reason(): string {
		if ( self::is_branding_enabled() ) {
			return '';
		}

		$is_pro  = self::is_pro_active();
		$default = $is_pro ? self::LOCK_LICENSE_REQUIRED : self::LOCK_COMING_IN_PRO;

		/**
		 * Filter branding lock reason for Workspace Settings UI.
		 *
		 * @since 3.7.4
		 *
		 * @param string $reason  Lock reason code.
		 * @param bool   $is_pro  Whether Pro is active.
		 */
		$reason = (string) apply_filters( 'hvnly_workspace_branding_lock_reason', $default, $is_pro );
		return '' !== $reason ? $reason : $default;
	}

	/**
	 * Payload for Workspace localize + settings REST gate.
	 *
	 * @return array{isProActive:bool,branding:array{enabled:bool,lockReason:string}}
	 */
	public static function localize_payload(): array {
		$enabled = self::is_branding_enabled();

		return array(
			'isProActive' => self::is_pro_active(),
			'branding'    => array(
				'enabled'    => $enabled,
				'lockReason' => self::branding_lock_reason(),
			),
		);
	}
}
