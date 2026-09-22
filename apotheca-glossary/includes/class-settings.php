<?php
/**
 * The Glossary settings page.
 *
 * A single page under the Glossary menu, collecting the in-content linking
 * options and the glossary URL slug. It writes to the one apglos_settings
 * option that linkify and schema read.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the settings page using the WordPress Settings API.
 */
class Apglos_Settings {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE = 'apglos-settings';

	/**
	 * Settings group name.
	 *
	 * @var string
	 */
	const GROUP = 'apglos_settings_group';

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/**
	 * Add the settings page under the Glossary menu.
	 *
	 * @return void
	 */
	public function add_page() {
		add_submenu_page(
			'edit.php?post_type=' . APGLOS_POST_TYPE,
			__( 'Glossary Settings', 'apotheca-glossary' ),
			__( 'Settings', 'apotheca-glossary' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			self::GROUP,
			APGLOS_SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => apglos_default_settings(),
			)
		);

		// In-content linking section.
		add_settings_section(
			'apglos_linkify_section',
			__( 'In-content linking', 'apotheca-glossary' ),
			array( $this, 'linkify_section_intro' ),
			self::PAGE
		);

		$this->add_field( 'linkify_enabled', __( 'Enable linking', 'apotheca-glossary' ), 'field_linkify_enabled', 'apglos_linkify_section' );
		$this->add_field( 'linkify_cap', __( 'Maximum links per post', 'apotheca-glossary' ), 'field_linkify_cap', 'apglos_linkify_section' );
		$this->add_field( 'linkify_new_tab', __( 'Open links in a new tab', 'apotheca-glossary' ), 'field_linkify_new_tab', 'apglos_linkify_section' );
		$this->add_field( 'linkify_new_tab_note', __( 'New-tab note for screen readers', 'apotheca-glossary' ), 'field_linkify_new_tab_note', 'apglos_linkify_section' );
		$this->add_field( 'linkify_external_icon', __( 'External-link icon', 'apotheca-glossary' ), 'field_linkify_external_icon', 'apglos_linkify_section' );
		$this->add_field( 'linkify_dynamic_fields', __( 'Link inside dynamic fields', 'apotheca-glossary' ), 'field_linkify_dynamic_fields', 'apglos_linkify_section' );
		$this->add_field( 'linkify_link_underline', __( 'Underline links', 'apotheca-glossary' ), 'field_link_underline', 'apglos_linkify_section' );
		$this->add_field( 'linkify_link_color', __( 'Link colour', 'apotheca-glossary' ), 'field_link_color', 'apglos_linkify_section' );
		$this->add_field( 'linkify_link_hover_color', __( 'Link hover colour', 'apotheca-glossary' ), 'field_link_hover_color', 'apglos_linkify_section' );
		$this->add_field( 'linkify_excluded_post_types', __( 'Do not link in these post types', 'apotheca-glossary' ), 'field_excluded_post_types', 'apglos_linkify_section' );

		// General section.
		add_settings_section(
			'apglos_general_section',
			__( 'General', 'apotheca-glossary' ),
			'__return_false',
			self::PAGE
		);

		$this->add_field( 'glossary_slug', __( 'Glossary URL slug', 'apotheca-glossary' ), 'field_glossary_slug', 'apglos_general_section' );
		$this->add_field( 'glossary_page_url', __( 'Glossary page URL', 'apotheca-glossary' ), 'field_glossary_page_url', 'apglos_general_section' );
	}

	/**
	 * Small helper to register a field.
	 *
	 * @param string $id       Field id (also the label's `for`).
	 * @param string $label    Field label.
	 * @param string $callback Method on this class that renders the field.
	 * @param string $section  Section id.
	 * @return void
	 */
	private function add_field( $id, $label, $callback, $section ) {
		add_settings_field(
			$id,
			$label,
			array( $this, $callback ),
			self::PAGE,
			$section,
			array( 'label_for' => $id )
		);
	}

	/**
	 * Intro text for the linking section.
	 *
	 * @return void
	 */
	public function linkify_section_intro() {
		echo '<p>' . esc_html__( 'Automatically link the first mention of each glossary term in your posts. This ships switched off. Turn it on once the glossary is populated and a few posts are live.', 'apotheca-glossary' ) . '</p>';
	}

	/*
	 * -------------------------------------------------------------------------
	 * Field renderers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Enable linking.
	 *
	 * @return void
	 */
	public function field_linkify_enabled() {
		$this->checkbox( 'linkify_enabled', __( 'Add glossary links inside post content', 'apotheca-glossary' ) );
	}

	/**
	 * Cap field.
	 *
	 * @return void
	 */
	public function field_linkify_cap() {
		$value = (int) apglos_get_setting( 'linkify_cap' );
		printf(
			'<input type="number" min="0" step="1" id="linkify_cap" name="%1$s[linkify_cap]" value="%2$s" class="small-text" />',
			esc_attr( APGLOS_SETTINGS_OPTION ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The most links added to any single post. The default is three. Set 0 to add none.', 'apotheca-glossary' ) . '</p>';
	}

	/**
	 * New-tab field.
	 *
	 * @return void
	 */
	public function field_linkify_new_tab() {
		$this->checkbox( 'linkify_new_tab', __( 'Open glossary links in a new tab, so the reader keeps her place in the article', 'apotheca-glossary' ) );
	}

	/**
	 * New-tab note field.
	 *
	 * @return void
	 */
	public function field_linkify_new_tab_note() {
		$this->checkbox( 'linkify_new_tab_note', __( 'Add a visually hidden "opens in a new tab" note for screen readers', 'apotheca-glossary' ) );
	}

	/**
	 * External-icon field.
	 *
	 * @return void
	 */
	public function field_linkify_external_icon() {
		$this->checkbox( 'linkify_external_icon', __( 'Show a small external-link icon after each glossary link', 'apotheca-glossary' ) );
	}

	/**
	 * Link-inside-dynamic-fields field.
	 *
	 * @return void
	 */
	public function field_linkify_dynamic_fields() {
		$this->checkbox( 'linkify_dynamic_fields', __( 'Also add links inside dynamic field widgets (for pages built with JetEngine or Elementor dynamic fields, not just the classic editor)', 'apotheca-glossary' ) );
	}

	/**
	 * Underline links field.
	 *
	 * @return void
	 */
	public function field_link_underline() {
		$this->checkbox( 'linkify_link_underline', __( 'Underline glossary links so they are visible as links', 'apotheca-glossary' ) );
	}

	/**
	 * Link colour field.
	 *
	 * @return void
	 */
	public function field_link_color() {
		$this->color_field( 'linkify_link_color', __( 'Leave blank to use the surrounding text colour.', 'apotheca-glossary' ) );
	}

	/**
	 * Link hover colour field.
	 *
	 * @return void
	 */
	public function field_link_hover_color() {
		$this->color_field( 'linkify_link_hover_color', __( 'Leave blank to keep the link colour on hover.', 'apotheca-glossary' ) );
	}

	/**
	 * Add a leading # to a hex colour if the user left it off, so
	 * sanitize_hex_color accepts it. Returns '' for anything empty.
	 *
	 * @param string $value The raw value.
	 * @return string
	 */
	private function normalise_hex( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( '#' !== $value[0] ) {
			$value = '#' . $value;
		}
		return $value;
	}

	/**
	 * Render a hex colour text field bound to a setting.
	 *
	 * @param string $key         The setting key.
	 * @param string $description The help text.
	 * @return void
	 */
	private function color_field( $key, $description ) {
		$value = (string) apglos_get_setting( $key );
		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" placeholder="#e13172" pattern="#?[A-Fa-f0-9]{3,8}" />',
			esc_attr( $key ),
			esc_attr( APGLOS_SETTINGS_OPTION ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}

	/**
	 * Excluded post types field.
	 *
	 * @return void
	 */
	public function field_excluded_post_types() {
		$excluded = (array) apglos_get_setting( 'linkify_excluded_post_types' );
		$types    = get_post_types( array( 'public' => true ), 'objects' );

		echo '<fieldset>';
		foreach ( $types as $type ) {
			// The glossary itself and attachments are never linked anyway.
			if ( APGLOS_POST_TYPE === $type->name || 'attachment' === $type->name ) {
				continue;
			}
			printf(
				'<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="%1$s[linkify_excluded_post_types][]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( APGLOS_SETTINGS_OPTION ),
				esc_attr( $type->name ),
				checked( in_array( $type->name, $excluded, true ), true, false ),
				esc_html( $type->labels->name )
			);
		}
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Linking is skipped entirely in the post types you tick here.', 'apotheca-glossary' ) . '</p>';
	}

	/**
	 * Glossary slug field.
	 *
	 * @return void
	 */
	public function field_glossary_slug() {
		$value = (string) apglos_get_setting( 'glossary_slug' );
		printf(
			'<code>%1$s/</code> <input type="text" id="glossary_slug" name="%2$s[glossary_slug]" value="%3$s" class="regular-text" />',
			esc_html( untrailingslashit( home_url() ) ),
			esc_attr( APGLOS_SETTINGS_OPTION ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The URL base for the glossary, for example "glossary" gives /glossary/term-slug/. Permalinks refresh automatically when you change this.', 'apotheca-glossary' ) . '</p>';
	}

	/**
	 * Glossary page URL field.
	 *
	 * @return void
	 */
	public function field_glossary_page_url() {
		$value = (string) apglos_get_setting( 'glossary_page_url' );
		printf(
			'<input type="url" id="glossary_page_url" name="%1$s[glossary_page_url]" value="%2$s" class="regular-text" placeholder="%3$s" />',
			esc_attr( APGLOS_SETTINGS_OPTION ),
			esc_attr( $value ),
			esc_attr( home_url( '/glossary-page/' ) )
		);
		echo '<p class="description">' . esc_html__( 'The page where you placed the glossary widget or shortcode. When set, in-content links point to this page and scroll to the term, so the reader lands on the full glossary and can search other terms. Leave blank to link to each term\'s own page instead.', 'apotheca-glossary' ) . '</p>';
	}

	/**
	 * Render a single checkbox bound to a boolean setting.
	 *
	 * @param string $key   The setting key.
	 * @param string $label The label text.
	 * @return void
	 */
	private function checkbox( $key, $label ) {
		$checked = ! empty( apglos_get_setting( $key ) );
		printf(
			'<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>',
			esc_attr( $key ),
			esc_attr( APGLOS_SETTINGS_OPTION ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Sanitise and render
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Sanitise the submitted settings into a clean array.
	 *
	 * @param array $input The raw submitted values.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = apglos_default_settings();

		$out['linkify_enabled']       = ! empty( $input['linkify_enabled'] );
		$out['linkify_cap']           = isset( $input['linkify_cap'] ) ? max( 0, absint( $input['linkify_cap'] ) ) : 3;
		$out['linkify_new_tab']        = ! empty( $input['linkify_new_tab'] );
		$out['linkify_new_tab_note']   = ! empty( $input['linkify_new_tab_note'] );
		$out['linkify_external_icon']  = ! empty( $input['linkify_external_icon'] );
		$out['linkify_dynamic_fields'] = ! empty( $input['linkify_dynamic_fields'] );

		// Link appearance.
		$out['linkify_link_underline']   = ! empty( $input['linkify_link_underline'] );
		$out['linkify_link_color']       = isset( $input['linkify_link_color'] ) ? (string) sanitize_hex_color( $this->normalise_hex( $input['linkify_link_color'] ) ) : '';
		$out['linkify_link_hover_color'] = isset( $input['linkify_link_hover_color'] ) ? (string) sanitize_hex_color( $this->normalise_hex( $input['linkify_link_hover_color'] ) ) : '';

		// Excluded post types: keep only real, public ones.
		$submitted = isset( $input['linkify_excluded_post_types'] ) ? (array) $input['linkify_excluded_post_types'] : array();
		$valid     = get_post_types( array( 'public' => true ) );
		$out['linkify_excluded_post_types'] = array_values(
			array_intersect( array_map( 'sanitize_key', $submitted ), array_keys( $valid ) )
		);

		// Glossary slug.
		$slug = isset( $input['glossary_slug'] ) ? sanitize_title( $input['glossary_slug'] ) : 'glossary';
		if ( '' === $slug ) {
			$slug = 'glossary';
		}
		$out['glossary_slug'] = $slug;

		// Glossary page URL, where the widget or shortcode lives.
		$out['glossary_page_url'] = isset( $input['glossary_page_url'] ) ? esc_url_raw( trim( $input['glossary_page_url'] ) ) : '';

		// If the slug changed, flag a rewrite-rules flush for the next init.
		$old = apglos_get_settings();
		if ( ! isset( $old['glossary_slug'] ) || $old['glossary_slug'] !== $slug ) {
			update_option( 'apglos_needs_flush', '1' );
		}

		// Settings feed the linkify link targets, so drop the cached link
		// dictionary and schema set. Per-post link caches carry the settings in
		// their hash and refresh on their own.
		delete_transient( 'apglos_linkify_dict' );
		delete_transient( 'apglos_schema_set' );

		return $out;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'apotheca-glossary' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Glossary Settings', 'apotheca-glossary' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
