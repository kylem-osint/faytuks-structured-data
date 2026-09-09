<?php
/**
 * Plugin bootstrap.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData;

use Faytuks\StructuredData\Admin\Notices;
use Faytuks\StructuredData\Admin\Settings;
use Faytuks\StructuredData\PostMeta\ArticleType;
use Faytuks\StructuredData\Schema\ArticleTransformer;
use Faytuks\StructuredData\Schema\OrganizationTransformer;
use Faytuks\StructuredData\Schema\RankMathIntegration;
use Faytuks\StructuredData\Settings\SettingsRepository;

/**
 * Wires the plugin's services together.
 *
 * Deliberately the only stateful singleton: everything else is a plain object
 * built here, which keeps the individual services testable in isolation.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Per-post article type control.
	 *
	 * @var ArticleType
	 */
	private $article_type;

	/**
	 * Rank Math integration.
	 *
	 * @var RankMathIntegration
	 */
	private $integration;

	/**
	 * Update checker.
	 *
	 * @var Updater
	 */
	private $updater;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Build the service graph.
	 */
	private function __construct() {
		$this->settings     = new SettingsRepository();
		$this->article_type = new ArticleType();
		$this->integration  = new RankMathIntegration(
			$this->settings,
			new OrganizationTransformer(),
			new ArticleTransformer(),
			$this->article_type
		);
		$this->updater      = new Updater();
	}

	/**
	 * The shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register everything with WordPress.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->article_type->init();
		$this->integration->init();
		$this->updater->init();

		if ( is_admin() ) {
			( new Settings( $this->settings, $this->integration, $this->article_type ) )->init();
			( new Notices() )->init();
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'fn-structured-data',
			false,
			dirname( plugin_basename( FN_STRUCTURED_DATA_FILE ) ) . '/languages'
		);
	}

	/**
	 * Settings repository.
	 *
	 * @return SettingsRepository
	 */
	public function settings(): SettingsRepository {
		return $this->settings;
	}

	/**
	 * Per-post article type control.
	 *
	 * @return ArticleType
	 */
	public function article_type(): ArticleType {
		return $this->article_type;
	}

	/**
	 * Rank Math integration.
	 *
	 * @return RankMathIntegration
	 */
	public function integration(): RankMathIntegration {
		return $this->integration;
	}
}
