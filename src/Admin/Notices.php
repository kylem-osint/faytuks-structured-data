<?php
/**
 * Admin notices.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Admin;

use Faytuks\StructuredData\Support\RankMath;

/**
 * Tells administrators when Rank Math cannot be extended.
 *
 * The notice is scoped to the screens where it is actionable and can be
 * dismissed per user. The plugin never deactivates itself and never alters
 * Rank Math's own settings.
 */
final class Notices {

	/**
	 * User meta key recording dismissal.
	 */
	private const DISMISSED_META = 'fn_structured_data_dismissed_rank_math_notice';

	/**
	 * Nonce action for dismissal.
	 */
	private const NONCE_ACTION = 'fn_structured_data_dismiss_notice';

	/**
	 * Screens where the notice is shown.
	 */
	private const SCREENS = array( 'plugins', 'dashboard', 'settings_page_fn-structured-data' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_notices', array( $this, 'maybe_render' ) );
		add_action( 'admin_post_' . self::NONCE_ACTION, array( $this, 'handle_dismissal' ) );
	}

	/**
	 * Render the notice where relevant.
	 *
	 * @return void
	 */
	public function maybe_render(): void {
		if ( ! current_user_can( Settings::CAPABILITY ) ) {
			return;
		}

		if ( self::rank_math_ready() ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || ! in_array( $screen->id, self::SCREENS, true ) ) {
			return;
		}

		// The settings screen renders its own copy inline.
		if ( 'settings_page_' . Settings::PAGE_SLUG === $screen->id ) {
			return;
		}

		if ( (bool) get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}

		$dismiss_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::NONCE_ACTION ),
			self::NONCE_ACTION
		);

		?>
		<div class="notice notice-warning">
			<p><?php echo esc_html( self::message() ); ?></p>
			<p>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button-secondary">
					<?php esc_html_e( 'Dismiss', 'fn-structured-data' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the inline warning used on the settings screen.
	 *
	 * @return void
	 */
	public static function render_rank_math_warning(): void {
		if ( self::rank_math_ready() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning inline"><p>%s</p></div>',
			esc_html( self::message() )
		);
	}

	/**
	 * Store the dismissal.
	 *
	 * @return void
	 */
	public function handle_dismissal(): void {
		if ( ! current_user_can( Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to dismiss this notice.', 'fn-structured-data' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );

		exit;
	}

	/**
	 * Whether Rank Math can currently be extended.
	 *
	 * @return bool
	 */
	private static function rank_math_ready(): bool {
		return RankMath::supports_json_ld() && RankMath::is_schema_module_active();
	}

	/**
	 * The notice text for the current situation.
	 *
	 * @return string
	 */
	private static function message(): string {
		if ( ! RankMath::is_active() ) {
			return __( 'Faytuks Structured Data extends Rank Math\'s structured data. Rank Math is not active, so structured data is left untouched. Settings can still be configured and will apply as soon as Rank Math is available.', 'fn-structured-data' );
		}

		return __( 'Rank Math is active but its schema module appears to be switched off, so there is no structured data graph to extend. Enable the Rank Math Schema module to apply these settings.', 'fn-structured-data' );
	}
}
