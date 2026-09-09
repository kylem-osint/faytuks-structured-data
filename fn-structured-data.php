<?php
/**
 * Plugin Name: Faytuks Structured Data
 * Description: Extends and corrects Rank Math's structured data for Faytuks Network: promotes the publisher Organization to a NewsMediaOrganization, adds newsroom transparency properties, and adds per-post NewsArticle subtype control.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Kyle M
 * Author URI: https://www.faytuksnetwork.com
 * Plugin URI: https://www.faytuksnetwork.com
 * Author Email: korvath85@gmail.com
 * Text Domain: fn-structured-data
 * Domain Path: /languages
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Faytuks\StructuredData
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FN_STRUCTURED_DATA_VERSION', '1.0.0' );
define( 'FN_STRUCTURED_DATA_FILE', __FILE__ );
define( 'FN_STRUCTURED_DATA_PATH', plugin_dir_path( __FILE__ ) );
define( 'FN_STRUCTURED_DATA_URL', plugin_dir_url( __FILE__ ) );

/*
 * GitHub token for private update checks: define FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN
 * in wp-config.php (preferred), or set the PUC_GITHUB_TOKEN environment variable.
 * Do not hardcode tokens in this file.
 */

/**
 * Warn administrators when the Composer autoloader is missing.
 *
 * This only happens when the plugin is installed from a source archive instead
 * of a release ZIP, so the message points at the supported install path.
 *
 * @return void
 */
function fn_structured_data_autoloader_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__(
			'Faytuks Structured Data is missing its autoloader. Install the release ZIP, or run "composer install --no-dev" in the plugin directory.',
			'fn-structured-data'
		)
	);
}

if ( ! is_readable( FN_STRUCTURED_DATA_PATH . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', 'fn_structured_data_autoloader_notice' );

	return;
}

require_once FN_STRUCTURED_DATA_PATH . 'vendor/autoload.php';

\Faytuks\StructuredData\Plugin::instance()->boot();
