<?php
/**
 * Settings screen.
 *
 * @package Faytuks\StructuredData
 */

declare( strict_types=1 );

namespace Faytuks\StructuredData\Admin;

use Faytuks\StructuredData\PostMeta\ArticleType;
use Faytuks\StructuredData\Schema\RankMathIntegration;
use Faytuks\StructuredData\Settings\SettingsRepository;
use Faytuks\StructuredData\Support\RankMath;

/**
 * Registers and renders Settings -> Faytuks Structured Data.
 *
 * Rendering only: every value written here goes through
 * SettingsRepository::sanitize(), and no schema transformation logic lives in
 * this class.
 */
final class Settings {

	/**
	 * Capability required to view and save settings.
	 */
	public const CAPABILITY = 'manage_options';

	/**
	 * Settings page slug.
	 */
	public const PAGE_SLUG = 'fn-structured-data';

	/**
	 * Settings repository.
	 *
	 * @var SettingsRepository
	 */
	private $settings;

	/**
	 * Rank Math integration, for diagnostics.
	 *
	 * @var RankMathIntegration
	 */
	private $integration;

	/**
	 * Per-post article type control, for diagnostics.
	 *
	 * @var ArticleType
	 */
	private $article_type;

	/**
	 * Values Rank Math contributes, keyed by inherit key.
	 *
	 * @var array<string, string>|null
	 */
	private $inherited = null;

	/**
	 * Hook suffix of the settings page.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param SettingsRepository  $settings     Settings repository.
	 * @param RankMathIntegration $integration  Rank Math integration.
	 * @param ArticleType         $article_type Per-post article type control.
	 */
	public function __construct( SettingsRepository $settings, RankMathIntegration $integration, ArticleType $article_type ) {
		$this->settings     = $settings;
		$this->integration  = $integration;
		$this->article_type = $article_type;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook_suffix = add_options_page(
			__( 'Faytuks Structured Data', 'fn-structured-data' ),
			__( 'Faytuks Structured Data', 'fn-structured-data' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
	}

	/**
	 * Hook suffix WordPress assigned to the settings page.
	 *
	 * Empty until register_menu() has run. The prefix depends on whether the
	 * admin menu has been built, so callers must not assume `settings_page_`.
	 *
	 * @return string
	 */
	public function hook_suffix(): string {
		return $this->hook_suffix;
	}

	/**
	 * Register the option, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			SettingsRepository::OPTION_GROUP,
			SettingsRepository::OPTION_KEY,
			array(
				'type'              => 'array',
				'default'           => SettingsRepository::defaults(),
				'show_in_rest'      => false,
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);

		foreach ( SettingsFields::sections() as $section ) {
			add_settings_section(
				(string) $section['id'],
				(string) $section['title'],
				array( $this, 'render_section_description' ),
				self::PAGE_SLUG,
				array( 'description' => (string) $section['description'] )
			);

			foreach ( $section['fields'] as $field ) {
				add_settings_field(
					'fn_structured_data_' . $field['group'] . '_' . $field['key'],
					(string) $field['label'],
					array( $this, 'render_field' ),
					self::PAGE_SLUG,
					(string) $section['id'],
					array(
						'field'     => $field,
						'label_for' => $this->field_id( $field ),
					)
				);
			}
		}
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $raw Submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( $raw ): array {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			$existing = get_option( SettingsRepository::OPTION_KEY, array() );

			return $this->settings->sanitize( is_array( $existing ) ? $existing : array() );
		}

		$sanitized = $this->settings->sanitize( $raw );

		$this->settings->flush();

		return $sanitized;
	}

	/**
	 * Load the settings screen assets, scoped to this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'fn-structured-data-admin',
			FN_STRUCTURED_DATA_URL . 'assets/css/admin.css',
			array(),
			FN_STRUCTURED_DATA_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'fn-structured-data-admin',
			FN_STRUCTURED_DATA_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			FN_STRUCTURED_DATA_VERSION,
			true
		);

		wp_localize_script(
			'fn-structured-data-admin',
			'fnStructuredDataAdmin',
			array(
				'mediaTitle'  => __( 'Select organization logo', 'fn-structured-data' ),
				'mediaButton' => __( 'Use this logo', 'fn-structured-data' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to manage these settings.', 'fn-structured-data' ) );
		}

		?>
		<div class="wrap fn-structured-data-settings">
			<h1><?php esc_html_e( 'Faytuks Structured Data', 'fn-structured-data' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Rank Math continues to generate the structured data graph. This plugin promotes the publisher entity to a NewsMediaOrganization, adds newsroom transparency properties, and lets editors pick a more specific news article type per post.', 'fn-structured-data' ); ?>
			</p>
			<?php Notices::render_rank_math_warning(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( SettingsRepository::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
			<?php ( new Diagnostics( $this->settings, $this->integration, $this->article_type ) )->render(); ?>
		</div>
		<?php
	}

	/**
	 * Render a section description.
	 *
	 * @param array<string, mixed> $section Section arguments.
	 * @return void
	 */
	public function render_section_description( $section ): void {
		$description = '';

		if ( is_array( $section ) && isset( $section['description'] ) && is_string( $section['description'] ) ) {
			$description = $section['description'];
		}

		if ( '' === $description ) {
			return;
		}

		printf( '<p class="description">%s</p>', esc_html( $description ) );
	}

	/**
	 * Render one settings field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_field( $args ): void {
		if ( ! is_array( $args ) || ! isset( $args['field'] ) || ! is_array( $args['field'] ) ) {
			return;
		}

		$field = $args['field'];
		$value = $this->field_value( $field );

		switch ( $field['type'] ) {
			case 'urls':
				$this->render_textarea_field( $field, $value );
				break;
			case 'logo':
				$this->render_logo_field( $field, $value );
				break;
			default:
				$this->render_input_field( $field, $value );
				break;
		}

		$this->render_inheritance_hint( $field, $value );

		if ( isset( $field['description'] ) && is_string( $field['description'] ) && '' !== $field['description'] ) {
			printf( '<p class="description">%s</p>', esc_html( $field['description'] ) );
		}
	}

	/**
	 * Render a single-line input.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Current value.
	 * @return void
	 */
	private function render_input_field( array $field, $value ): void {
		$input_type = 'text';

		if ( 'url' === $field['type'] ) {
			$input_type = 'url';
		} elseif ( 'email' === $field['type'] ) {
			$input_type = 'email';
		}

		printf(
			'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" />',
			esc_attr( $input_type ),
			esc_attr( $this->field_id( $field ) ),
			esc_attr( $this->field_name( $field ) ),
			esc_attr( is_string( $value ) ? $value : '' )
		);
	}

	/**
	 * Render the newline separated URL list.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Current value.
	 * @return void
	 */
	private function render_textarea_field( array $field, $value ): void {
		$lines = is_array( $value ) ? $value : array();

		printf(
			'<textarea id="%1$s" name="%2$s" rows="5" class="large-text code">%3$s</textarea>',
			esc_attr( $this->field_id( $field ) ),
			esc_attr( $this->field_name( $field ) ),
			esc_textarea( implode( "\n", array_map( 'strval', $lines ) ) )
		);
	}

	/**
	 * Render the logo picker.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Current value.
	 * @return void
	 */
	private function render_logo_field( array $field, $value ): void {
		$settings = $this->settings->get();
		$logo_id  = 0;

		if ( isset( $settings['organization']['logo_id'] ) ) {
			$logo_id = (int) $settings['organization']['logo_id'];
		}

		$url = is_string( $value ) ? $value : '';

		$logo_id_name = $this->field_name(
			array(
				'group' => 'organization',
				'key'   => 'logo_id',
			)
		);

		?>
		<span class="fn-structured-data-logo-field">
			<input
				type="url"
				id="<?php echo esc_attr( $this->field_id( $field ) ); ?>"
				name="<?php echo esc_attr( $this->field_name( $field ) ); ?>"
				value="<?php echo esc_attr( $url ); ?>"
				class="regular-text fn-structured-data-logo-url"
			/>
			<input
				type="hidden"
				name="<?php echo esc_attr( $logo_id_name ); ?>"
				value="<?php echo esc_attr( (string) $logo_id ); ?>"
				class="fn-structured-data-logo-id"
			/>
			<button type="button" class="button fn-structured-data-logo-select">
				<?php esc_html_e( 'Select image', 'fn-structured-data' ); ?>
			</button>
			<button type="button" class="button-link fn-structured-data-logo-clear">
				<?php esc_html_e( 'Clear', 'fn-structured-data' ); ?>
			</button>
		</span>
		<?php if ( '' !== $url ) : ?>
			<span class="fn-structured-data-logo-preview">
				<img src="<?php echo esc_url( $url ); ?>" alt="" />
			</span>
		<?php endif; ?>
		<?php
	}

	/**
	 * Show whether the effective value is inherited or overridden.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Current value.
	 * @return void
	 */
	private function render_inheritance_hint( array $field, $value ): void {
		$has_override = is_array( $value ) ? array() !== $value : ( is_string( $value ) && '' !== $value );

		if ( $has_override ) {
			printf(
				'<p class="fn-structured-data-source fn-structured-data-source--override">%s</p>',
				esc_html__( 'Override supplied by Faytuks Structured Data.', 'fn-structured-data' )
			);

			return;
		}

		$inherit_key = isset( $field['inherits'] ) && is_string( $field['inherits'] ) ? $field['inherits'] : '';

		if ( '' === $inherit_key ) {
			return;
		}

		$inherited = $this->inherited_values();
		$current   = $inherited[ $inherit_key ] ?? '';

		if ( '' === $current ) {
			printf(
				'<p class="fn-structured-data-source">%s</p>',
				esc_html__( 'Inherited from Rank Math (no value configured there yet).', 'fn-structured-data' )
			);

			return;
		}

		printf(
			'<p class="fn-structured-data-source">%s <code>%s</code></p>',
			esc_html__( 'Inherited from Rank Math:', 'fn-structured-data' ),
			esc_html( $current )
		);
	}

	/**
	 * Rank Math's current organization values.
	 *
	 * @return array<string, string>
	 */
	private function inherited_values(): array {
		if ( null === $this->inherited ) {
			$this->inherited = RankMath::inherited_organization();
		}

		return $this->inherited;
	}

	/**
	 * The stored value for a field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return mixed
	 */
	private function field_value( array $field ) {
		$settings = $this->settings->get();
		$group    = (string) $field['group'];
		$key      = (string) $field['key'];

		if ( ! isset( $settings[ $group ] ) || ! is_array( $settings[ $group ] ) ) {
			return '';
		}

		return $settings[ $group ][ $key ] ?? '';
	}

	/**
	 * The form input name for a field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return string
	 */
	private function field_name( array $field ): string {
		return sprintf(
			'%s[%s][%s]',
			SettingsRepository::OPTION_KEY,
			(string) $field['group'],
			(string) $field['key']
		);
	}

	/**
	 * The DOM id for a field.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @return string
	 */
	private function field_id( array $field ): string {
		return 'fn-structured-data-' . str_replace( '_', '-', (string) $field['group'] . '-' . (string) $field['key'] );
	}
}
