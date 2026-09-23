<?php
/**
 * The [apotheca_glossary] shortcode.
 *
 * A non-Elementor way to drop the glossary onto any page or post. It maps the
 * shortcode attributes onto the shared renderer's settings, so the shortcode
 * and the Elementor widget produce identical markup and behaviour.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the glossary shortcode.
 */
class Apglos_Shortcode {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		// Register the shared front-end assets so the renderer can enqueue them.
		add_action( 'wp_enqueue_scripts', array( 'Apglos_Renderer', 'register_assets' ) );

		// Register the shortcode itself.
		add_shortcode( 'apotheca_glossary', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * Accepts attributes for every toggle and the main labels, so the glossary
	 * can be tailored without Elementor. Booleans accept 1/0, true/false,
	 * yes/no or on/off.
	 *
	 * Example:
	 *   [apotheca_glossary show_related="yes" category="actives-and-ingredients"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$defaults = Apglos_Renderer::defaults();

		// Only expose the settings that make sense as shortcode attributes.
		$atts = shortcode_atts(
			array(
				'show_search'         => $defaults['show_search'] ? '1' : '0',
				'show_az_bar'         => $defaults['show_az_bar'] ? '1' : '0',
				'show_09'             => $defaults['show_09'] ? '1' : '0',
				'show_category'       => $defaults['show_category'] ? '1' : '0',
				'show_count'          => $defaults['show_count'] ? '1' : '0',
				'show_headings'       => $defaults['show_headings'] ? '1' : '0',
				'show_category_label' => $defaults['show_category_label'] ? '1' : '0',
				'show_also_known_as'  => $defaults['show_also_known_as'] ? '1' : '0',
				'show_related'        => $defaults['show_related'] ? '1' : '0',
				'show_to_top'         => $defaults['show_to_top'] ? '1' : '0',
				'empty_letters'       => $defaults['empty_letters'],
				'category'            => $defaults['category'],
				'search_placeholder'  => $defaults['search_placeholder'],
				'aka_label'           => $defaults['aka_label'],
				'related_label'       => $defaults['related_label'],
				'clear_label'         => $defaults['clear_label'],
				'to_top_label'        => $defaults['to_top_label'],
				'empty_message'       => $defaults['empty_message'],
			),
			$atts,
			'apotheca_glossary'
		);

		// Sanitise the free-text and slug attributes. Toggle values are cast to
		// booleans inside the renderer.
		$atts['category']           = sanitize_title( $atts['category'] );
		$atts['empty_letters']      = 'hide' === $atts['empty_letters'] ? 'hide' : 'grey';
		$atts['search_placeholder'] = sanitize_text_field( $atts['search_placeholder'] );
		$atts['aka_label']          = sanitize_text_field( $atts['aka_label'] );
		$atts['related_label']      = sanitize_text_field( $atts['related_label'] );
		$atts['clear_label']        = sanitize_text_field( $atts['clear_label'] );
		$atts['to_top_label']       = sanitize_text_field( $atts['to_top_label'] );
		$atts['empty_message']      = sanitize_text_field( $atts['empty_message'] );

		return Apglos_Renderer::render( $atts );
	}
}
