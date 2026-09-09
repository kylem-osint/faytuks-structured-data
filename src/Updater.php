<?php
/**
 * Plugin Update Checker integration.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Wires GitHub release updates through Plugin Update Checker.
 *
 * Mirrors the release/update model used by FN Live: releases are published on
 * the repository's stable branch, and sites install the built ZIP attached to
 * the GitHub Release (never a source archive, which would omit `vendor/`).
 */
final class Updater {

	/**
	 * Default source repository.
	 *
	 * Override with the FN_STRUCTURED_DATA_PUC_REPOSITORY constant or the
	 * `fn_structured_data_puc_repository` filter if the remote differs.
	 */
	public const REPOSITORY_URL = 'https://github.com/kylem-osint/faytuks-structured-data/';

	/**
	 * Branch PUC reads releases and tags from.
	 */
	public const RELEASE_BRANCH = 'main';

	/**
	 * Matches the ZIP produced by `npm run build` and the release workflow.
	 */
	public const RELEASE_ASSET_REGEX = '/^fn-structured-data-.*\.zip$/i';

	/**
	 * Plugin slug; must match the installed directory name.
	 */
	public const SLUG = 'fn-structured-data';

	/**
	 * Whether an update checker was built during this request.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Register hooks.
	 *
	 * Hooked on `plugins_loaded` rather than an admin-only hook so WordPress
	 * can run update checks during cron and WP-CLI requests too.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
	}

	/**
	 * Build the update checker.
	 *
	 * @return void
	 */
	public function bootstrap(): void {
		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		$update_checker = PucFactory::buildUpdateChecker(
			self::repository_url(),
			FN_STRUCTURED_DATA_FILE,
			self::SLUG
		);

		// With a branch set, PUC prefers the latest GitHub Release, then tags,
		// then the branch tip. Release assets are required so sites download the
		// built ZIP (which includes vendor/) rather than the source archive.
		//
		// The methods below only exist on PUC's VCS-backed checkers, which is
		// what a GitHub repository URL produces. They are guarded so a
		// self-hosted metadata URL degrades to a plain update check instead of
		// a fatal error.
		if ( method_exists( $update_checker, 'setBranch' ) ) {
			$update_checker->setBranch( self::RELEASE_BRANCH );
		}

		if ( method_exists( $update_checker, 'getVcsApi' ) ) {
			$api = $update_checker->getVcsApi();

			if ( is_object( $api ) && method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets( self::RELEASE_ASSET_REGEX, self::require_release_assets( $api ) );
			}
		}

		$token = self::token();

		if ( '' !== $token && method_exists( $update_checker, 'setAuthentication' ) ) {
			$update_checker->setAuthentication( $token );
		}

		self::$active = true;
	}

	/**
	 * The repository the plugin updates from.
	 *
	 * @return string
	 */
	public static function repository_url(): string {
		$url = self::REPOSITORY_URL;

		if ( defined( 'FN_STRUCTURED_DATA_PUC_REPOSITORY' ) ) {
			$constant = (string) constant( 'FN_STRUCTURED_DATA_PUC_REPOSITORY' );

			if ( '' !== trim( $constant ) ) {
				$url = trim( $constant );
			}
		}

		/**
		 * Filters the repository the plugin checks for updates.
		 *
		 * @param string $url Repository URL.
		 */
		$filtered = apply_filters( 'fn_structured_data_puc_repository', $url );

		return is_string( $filtered ) && '' !== trim( $filtered ) ? trim( $filtered ) : $url;
	}

	/**
	 * Whether an update checker is wired up for this request.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		return self::$active;
	}

	/**
	 * Whether a read token is configured for private repository access.
	 *
	 * @return bool
	 */
	public static function has_token(): bool {
		return '' !== self::token();
	}

	/**
	 * The GitHub token used for update checks.
	 *
	 * Read from a wp-config.php constant (preferred) or the environment. Never
	 * stored in the database, never exposed in the admin UI, never committed.
	 *
	 * @return string
	 */
	public static function token(): string {
		if ( defined( 'FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN' ) ) {
			$token = trim( (string) constant( 'FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN' ) );

			if ( '' !== $token ) {
				return $token;
			}
		}

		$token = getenv( 'PUC_GITHUB_TOKEN' );

		return is_string( $token ) ? trim( $token ) : '';
	}

	/**
	 * The "require release assets" preference for the installed PUC version.
	 *
	 * Resolved from the API class so a PUC minor upgrade does not need a code
	 * change here.
	 *
	 * @param object $api PUC VCS API instance.
	 * @return int
	 */
	private static function require_release_assets( object $api ): int {
		$constant = get_class( $api ) . '::REQUIRE_RELEASE_ASSETS';

		if ( defined( $constant ) ) {
			return (int) constant( $constant );
		}

		return 2;
	}
}
