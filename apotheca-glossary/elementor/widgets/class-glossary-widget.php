<?php
/**
 * The Apotheca Glossary Elementor widget.
 *
 * One widget, because search, filters and results are a single linked
 * interaction. It renders through the shared Apglos_Renderer, so its markup and
 * behaviour are identical to the [apotheca_glossary] shortcode, and it carries
 * a full set of content toggles and style controls so a designer can restyle
 * every visible element from the Elementor panel.
 *
 * Atomic-friendly markup: has_widget_inner_wrapper() returns false under
 * optimized markup, the render is a single wrapper div, and no style selector
 * targets .elementor-widget-container.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Box_Shadow;

/**
 * Glossary widget.
 */
class Apglos_Glossary_Widget extends Widget_Base {

	/**
	 * Widget slug used by Elementor.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'apotheca_glossary';
	}

	/**
	 * Widget title shown in the panel.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Apotheca Glossary', 'apotheca-glossary' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-table-of-contents';
	}

	/**
	 * Categories this widget belongs to.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Apglos_Elementor_Loader::CATEGORY );
	}

	/**
	 * Search keywords in the panel.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'glossary', 'apotheca', 'terms', 'dictionary', 'definitions' );
	}

	/**
	 * Front-end style handles this widget needs, so Elementor enqueues them.
	 *
	 * @return string[]
	 */
	public function get_style_depends() {
		return array( Apglos_Renderer::HANDLE );
	}

	/**
	 * Front-end script handles this widget needs.
	 *
	 * @return string[]
	 */
	public function get_script_depends() {
		return array( Apglos_Renderer::HANDLE );
	}

	/**
	 * Atomic markup: no extra inner wrapper when optimized markup is on.
	 *
	 * @return bool
	 */
	public function has_widget_inner_wrapper(): bool {
		return ! \Elementor\Plugin::$instance->experiments->is_feature_active( 'e_optimized_markup' );
	}

	/*
	 * =========================================================================
	 * Controls
	 * =========================================================================
	 */

	/**
	 * Register all controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		// Content.
		$this->section_content_elements();
		$this->section_content_text();
		$this->section_content_filter();
		$this->section_layout();
		$this->section_editor_preview();

		// Style.
		$this->section_style_wrapper();
		$this->section_style_search();
		$this->section_style_letters();
		$this->section_style_category();
		$this->section_style_clear();
		$this->section_style_count();
		$this->section_style_headings();
		$this->section_style_term();
		$this->section_style_definition();
		$this->section_style_category_label();
		$this->section_style_aka();
		$this->section_style_related();
		$this->section_style_entry_spacing();
		$this->section_style_highlight();
		$this->section_style_empty();
	}

	/*
	 * -------------------------------------------------------------------------
	 * Content sections
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Content: which elements to show.
	 *
	 * @return void
	 */
	private function section_content_elements() {
		$this->start_controls_section(
			'section_elements',
			array(
				'label' => __( 'Elements', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$toggles = array(
			'show_search'         => array( __( 'Search field', 'apotheca-glossary' ), 'yes' ),
			'show_az_bar'         => array( __( 'A to Z bar', 'apotheca-glossary' ), 'yes' ),
			'show_09'             => array( __( '0-9 button', 'apotheca-glossary' ), 'yes' ),
			'show_category'       => array( __( 'Category dropdown', 'apotheca-glossary' ), 'yes' ),
			'show_count'          => array( __( 'Result count', 'apotheca-glossary' ), 'yes' ),
			'show_headings'       => array( __( 'Letter headings', 'apotheca-glossary' ), 'yes' ),
			'show_category_label' => array( __( 'Category label', 'apotheca-glossary' ), '' ),
			'show_also_known_as'  => array( __( 'Also known as', 'apotheca-glossary' ), '' ),
			'show_related'        => array( __( 'Related terms', 'apotheca-glossary' ), '' ),
		);

		foreach ( $toggles as $id => $conf ) {
			$this->add_control(
				$id,
				array(
					'label'        => $conf[0],
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => __( 'Show', 'apotheca-glossary' ),
					'label_off'    => __( 'Hide', 'apotheca-glossary' ),
					'return_value' => 'yes',
					'default'      => $conf[1],
				)
			);
		}

		// Empty-letter behaviour, only relevant when the A to Z bar shows.
		$this->add_control(
			'empty_letters',
			array(
				'label'     => __( 'Empty letters', 'apotheca-glossary' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'grey',
				'options'   => array(
					'grey' => __( 'Grey out', 'apotheca-glossary' ),
					'hide' => __( 'Hide', 'apotheca-glossary' ),
				),
				'condition' => array( 'show_az_bar' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content: the editable labels and messages.
	 *
	 * @return void
	 */
	private function section_content_text() {
		$this->start_controls_section(
			'section_text',
			array(
				'label' => __( 'Text and labels', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'search_placeholder',
			array(
				'label'     => __( 'Search placeholder', 'apotheca-glossary' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Search the glossary', 'apotheca-glossary' ),
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->add_control(
			'clear_label',
			array(
				'label'   => __( 'Clear all label', 'apotheca-glossary' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Clear all', 'apotheca-glossary' ),
			)
		);

		$this->add_control(
			'aka_label',
			array(
				'label'     => __( 'Also known as prefix', 'apotheca-glossary' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Also known as:', 'apotheca-glossary' ),
				'condition' => array( 'show_also_known_as' => 'yes' ),
			)
		);

		$this->add_control(
			'related_label',
			array(
				'label'     => __( 'Related terms prefix', 'apotheca-glossary' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Related:', 'apotheca-glossary' ),
				'condition' => array( 'show_related' => 'yes' ),
			)
		);

		$this->add_control(
			'empty_message',
			array(
				'label'   => __( 'No results message', 'apotheca-glossary' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'No terms match your search.', 'apotheca-glossary' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content: limit to a single category.
	 *
	 * @return void
	 */
	private function section_content_filter() {
		$this->start_controls_section(
			'section_filter',
			array(
				'label' => __( 'Filter', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'category',
			array(
				'label'       => __( 'Limit to one category', 'apotheca-glossary' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $this->get_category_choices(),
				'description' => __( 'Show only terms in this category. Leave as All categories to show everything. When set, the category dropdown is hidden.', 'apotheca-glossary' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content: layout, including the responsive two-column option.
	 *
	 * @return void
	 */
	private function section_layout() {
		$this->start_controls_section(
			'section_layout',
			array(
				'label' => __( 'Layout', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		// Columns, chosen per device. Maps to CSS column-count on the list.
		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'apotheca-glossary' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '1',
				'tablet_default' => '1',
				'mobile_default' => '1',
				'options'        => array(
					'1' => __( '1 column', 'apotheca-glossary' ),
					'2' => __( '2 columns', 'apotheca-glossary' ),
				),
				'selectors'      => array(
					'{{WRAPPER}} .apglos__list' => 'column-count: {{VALUE}};',
				),
				'description'    => __( 'Two columns flow the terms down the left then the right. Set it separately for desktop, tablet and mobile.', 'apotheca-glossary' ),
			)
		);

		// Column gap, only meaningful with two columns.
		$this->add_responsive_control(
			'column_gap',
			array(
				'label'      => __( 'Column gap', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 100 ) ),
				'default'    => array( 'unit' => 'rem', 'size' => 2 ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__list' => 'column-gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		// Anchor scroll offset: how far below the top a related-term jump lands,
		// so a fixed or transparent header does not cover the term. Per device,
		// since header height often differs on mobile.
		$this->add_responsive_control(
			'anchor_offset',
			array(
				'label'          => __( 'Anchor offset', 'apotheca-glossary' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( 'px', 'rem', 'em' ),
				'range'          => array( 'px' => array( 'min' => 0, 'max' => 400 ) ),
				'default'        => array( 'unit' => 'px', 'size' => 100 ),
				'selectors'      => array(
					'{{WRAPPER}}' => '--apglos-anchor-offset: {{SIZE}}{{UNIT}};',
				),
				'description'    => __( 'How far below the top a jump to a related term lands, so a fixed or transparent header does not cover it. Set it per device.', 'apotheca-glossary' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Content: editor-only preview state.
	 *
	 * @return void
	 */
	private function section_editor_preview() {
		$this->start_controls_section(
			'section_preview',
			array(
				'label' => __( 'Editor preview', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'preview_state',
			array(
				'label'       => __( 'Preview state', 'apotheca-glossary' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => 'default',
				'options'     => array(
					'default'    => __( 'Default', 'apotheca-glossary' ),
					'searching'  => __( 'Searching (with highlight)', 'apotheca-glossary' ),
					'no_results' => __( 'No results', 'apotheca-glossary' ),
				),
				'description' => __( 'Preview and style the searching and no-results states here in the editor. This has no effect on the live page.', 'apotheca-glossary' ),
			)
		);

		$this->end_controls_section();
	}

	/*
	 * -------------------------------------------------------------------------
	 * Style sections
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Style: the outer wrapper.
	 *
	 * @return void
	 */
	private function section_style_wrapper() {
		$this->start_controls_section(
			'style_wrapper',
			array(
				'label' => __( 'Wrapper', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'     => 'wrapper_background',
				'types'    => array( 'classic', 'gradient' ),
				'selector' => '{{WRAPPER}} .apglos',
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'wrapper_border',
				'selector' => '{{WRAPPER}} .apglos',
			)
		);

		$this->add_responsive_control(
			'wrapper_radius',
			array(
				'label'      => __( 'Border radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'wrapper_shadow',
				'selector' => '{{WRAPPER}} .apglos',
			)
		);

		// Full width and no padding/margin by default, as requested.
		$this->add_responsive_control(
			'wrapper_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'default'    => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'wrapper_margin',
			array(
				'label'      => __( 'Margin', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem', '%' ),
				'default'    => array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: search field.
	 *
	 * @return void
	 */
	private function section_style_search() {
		$this->start_controls_section(
			'style_search',
			array(
				'label'     => __( 'Search field', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_search' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'search_typography',
				'selector' => '{{WRAPPER}} .apglos__search',
			)
		);

		$this->add_control(
			'search_text_color',
			array(
				'label'     => __( 'Text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__search' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'search_placeholder_color',
			array(
				'label'     => __( 'Placeholder colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__search::placeholder' => 'color: {{VALUE}}; opacity: 1;' ),
			)
		);

		$this->add_control(
			'search_bg',
			array(
				'label'     => __( 'Background colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__search' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'search_border',
				'selector' => '{{WRAPPER}} .apglos__search',
			)
		);

		$this->add_responsive_control(
			'search_radius',
			array(
				'label'      => __( 'Border radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__search' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'search_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__search' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'search_min_height',
			array(
				'label'      => __( 'Minimum height', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__search' => 'min-height: {{SIZE}}{{UNIT}};' ),
			)
		);

		// Focus state.
		$this->add_control(
			'search_focus_heading',
			array(
				'label'     => __( 'Focus', 'apotheca-glossary' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'search_focus_border',
			array(
				'label'     => __( 'Focus border colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__search:focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'search_focus_bg',
			array(
				'label'     => __( 'Focus background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__search:focus' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: letter buttons, with normal / hover / active / disabled states.
	 *
	 * @return void
	 */
	private function section_style_letters() {
		$this->start_controls_section(
			'style_letters',
			array(
				'label'     => __( 'Letter buttons', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_az_bar' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'letter_typography',
				'selector' => '{{WRAPPER}} .apglos__letter',
			)
		);

		$this->add_responsive_control(
			'letter_gap',
			array(
				'label'      => __( 'Gap between buttons', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__az' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'letter_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__letter' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'letter_radius',
			array(
				'label'      => __( 'Border radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__letter' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Normal / Hover / Active / Disabled tabs.
		$this->start_controls_tabs( 'letter_state_tabs' );

		// Normal.
		$this->start_controls_tab( 'letter_normal', array( 'label' => __( 'Normal', 'apotheca-glossary' ) ) );
		$this->letter_state_controls( 'normal', '{{WRAPPER}} .apglos__letter' );
		$this->end_controls_tab();

		// Hover.
		$this->start_controls_tab( 'letter_hover', array( 'label' => __( 'Hover', 'apotheca-glossary' ) ) );
		$this->letter_state_controls( 'hover', '{{WRAPPER}} .apglos__letter:hover:not(:disabled)' );
		$this->end_controls_tab();

		// Active (selected letter).
		$this->start_controls_tab( 'letter_active', array( 'label' => __( 'Active', 'apotheca-glossary' ) ) );
		$this->letter_state_controls( 'active', '{{WRAPPER}} .apglos__letter.is-active' );
		$this->end_controls_tab();

		// Disabled (empty letter).
		$this->start_controls_tab( 'letter_disabled', array( 'label' => __( 'Disabled', 'apotheca-glossary' ) ) );
		$this->letter_state_controls( 'disabled', '{{WRAPPER}} .apglos__letter.is-empty, {{WRAPPER}} .apglos__letter:disabled' );
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * The three per-state colour controls used inside each letter-button tab.
	 *
	 * @param string $state    A unique key for the state.
	 * @param string $selector The CSS selector this state targets.
	 * @return void
	 */
	private function letter_state_controls( $state, $selector ) {
		$this->add_control(
			'letter_' . $state . '_color',
			array(
				'label'     => __( 'Text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'letter_' . $state . '_bg',
			array(
				'label'     => __( 'Background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'letter_' . $state . '_border',
			array(
				'label'     => __( 'Border colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( $selector => 'border-color: {{VALUE}};' ),
			)
		);
	}

	/**
	 * Style: category dropdown.
	 *
	 * @return void
	 */
	private function section_style_category() {
		$this->start_controls_section(
			'style_category',
			array(
				'label'     => __( 'Category dropdown', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_category' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'category_typography',
				'selector' => '{{WRAPPER}} .apglos__category',
			)
		);

		$this->add_control(
			'category_text_color',
			array(
				'label'     => __( 'Text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__category' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'category_bg',
			array(
				'label'     => __( 'Background colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__category' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'category_border',
				'selector' => '{{WRAPPER}} .apglos__category',
			)
		);

		$this->add_responsive_control(
			'category_radius',
			array(
				'label'      => __( 'Border radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__category' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'category_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__category' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'category_min_height',
			array(
				'label'      => __( 'Minimum height', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 120 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__category' => 'min-height: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'category_focus_border',
			array(
				'label'     => __( 'Focus border colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .apglos__category:focus' => 'border-color: {{VALUE}}; outline-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: Clear all link.
	 *
	 * @return void
	 */
	private function section_style_clear() {
		$this->start_controls_section(
			'style_clear',
			array(
				'label' => __( 'Clear all link', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'clear_typography',
				'selector' => '{{WRAPPER}} .apglos__clear',
			)
		);

		// Text and background across Normal and Hover, so the Clear all control
		// can be a plain link or a full button. The !important on backgrounds
		// keeps them from being overridden by a theme's own button styling.
		$this->start_controls_tabs( 'clear_state_tabs' );

		$this->start_controls_tab( 'clear_normal', array( 'label' => __( 'Normal', 'apotheca-glossary' ) ) );
		$this->add_control(
			'clear_color',
			array(
				'label'     => __( 'Text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__clear' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'clear_bg',
			array(
				'label'     => __( 'Background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__clear' => 'background-color: {{VALUE}} !important;' ),
			)
		);
		$this->end_controls_tab();

		$this->start_controls_tab( 'clear_hover', array( 'label' => __( 'Hover', 'apotheca-glossary' ) ) );
		$this->add_control(
			'clear_hover_color',
			array(
				'label'     => __( 'Text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__clear:hover, {{WRAPPER}} .apglos__clear:focus' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'clear_hover_bg',
			array(
				'label'     => __( 'Background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__clear:hover, {{WRAPPER}} .apglos__clear:focus' => 'background-color: {{VALUE}} !important;' ),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		// Border, radius and padding so it can be shaped into a button.
		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'      => 'clear_border',
				'selector'  => '{{WRAPPER}} .apglos__clear',
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'clear_radius',
			array(
				'label'      => __( 'Border radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__clear' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'clear_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__clear' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'clear_spacing',
			array(
				'label'      => __( 'Spacing (margin)', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__clear' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: result count.
	 *
	 * @return void
	 */
	private function section_style_count() {
		$this->start_controls_section(
			'style_count',
			array(
				'label'     => __( 'Result count', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_count' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'count_typography',
				'selector' => '{{WRAPPER}} .apglos__count',
			)
		);

		$this->add_control(
			'count_color',
			array(
				'label'     => __( 'Colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__count' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'count_align',
			array(
				'label'     => __( 'Alignment', 'apotheca-glossary' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => $this->align_options(),
				'selectors' => array( '{{WRAPPER}} .apglos__count' => 'text-align: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'count_spacing',
			array(
				'label'      => __( 'Spacing', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__count' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: letter headings.
	 *
	 * @return void
	 */
	private function section_style_headings() {
		$this->start_controls_section(
			'style_headings',
			array(
				'label'     => __( 'Letter headings', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_headings' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'selector' => '{{WRAPPER}} .apglos__heading',
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__heading' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'heading_bg',
			array(
				'label'     => __( 'Background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__heading' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'heading_border_color',
			array(
				'label'     => __( 'Bottom border colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__heading' => 'border-bottom-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'heading_border_width',
			array(
				'label'      => __( 'Bottom border width', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__heading' => 'border-bottom-width: {{SIZE}}{{UNIT}}; border-bottom-style: solid;' ),
			)
		);

		$this->add_responsive_control(
			'heading_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__heading' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'heading_spacing',
			array(
				'label'      => __( 'Spacing', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__heading' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: term.
	 *
	 * @return void
	 */
	private function section_style_term() {
		$this->start_controls_section(
			'style_term',
			array(
				'label' => __( 'Term', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'term_typography',
				'selector' => '{{WRAPPER}} .apglos__term',
			)
		);

		$this->add_control(
			'term_color',
			array(
				'label'     => __( 'Colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__term' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'term_spacing',
			array(
				'label'      => __( 'Spacing below', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__term' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: definition.
	 *
	 * @return void
	 */
	private function section_style_definition() {
		$this->start_controls_section(
			'style_definition',
			array(
				'label' => __( 'Definition', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'definition_typography',
				'selector' => '{{WRAPPER}} .apglos__definition',
			)
		);

		$this->add_control(
			'definition_color',
			array(
				'label'     => __( 'Colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__definition' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'definition_max_width',
			array(
				'label'      => __( 'Maximum width', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'ch', '%' ),
				'range'      => array(
					'px' => array( 'min' => 200, 'max' => 1200 ),
					'ch' => array( 'min' => 20, 'max' => 120 ),
				),
				'selectors'  => array( '{{WRAPPER}} .apglos__definition' => 'max-width: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_responsive_control(
			'definition_spacing',
			array(
				'label'      => __( 'Spacing below', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__definition' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: category label.
	 *
	 * @return void
	 */
	private function section_style_category_label() {
		$this->start_controls_section(
			'style_category_label',
			array(
				'label'     => __( 'Category label', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_category_label' => 'yes' ),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'cat_label_typography',
				'selector' => '{{WRAPPER}} .apglos__cat-label',
			)
		);

		$this->add_control(
			'cat_label_color',
			array(
				'label'     => __( 'Colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__cat-label' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'cat_label_bg',
			array(
				'label'     => __( 'Background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__cat-label' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'cat_label_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__cat-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'cat_label_radius',
			array(
				'label'      => __( 'Border radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__cat-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'cat_label_hover_color',
			array(
				'label'     => __( 'Hover text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'separator' => 'before',
				'selectors' => array( '{{WRAPPER}} .apglos__cat-label:hover' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'cat_label_hover_bg',
			array(
				'label'     => __( 'Hover background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__cat-label:hover' => 'background-color: {{VALUE}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: also known as.
	 *
	 * @return void
	 */
	private function section_style_aka() {
		$this->start_controls_section(
			'style_aka',
			array(
				'label'     => __( 'Also known as', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_also_known_as' => 'yes' ),
			)
		);

		$this->add_control(
			'aka_prefix_heading',
			array( 'label' => __( 'Prefix', 'apotheca-glossary' ), 'type' => Controls_Manager::HEADING )
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'aka_prefix_typography',
				'selector' => '{{WRAPPER}} .apglos__aka-label',
			)
		);

		$this->add_control(
			'aka_prefix_color',
			array(
				'label'     => __( 'Prefix colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__aka-label' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'aka_value_heading',
			array( 'label' => __( 'Value', 'apotheca-glossary' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'aka_value_typography',
				'selector' => '{{WRAPPER}} .apglos__aka-value',
			)
		);

		$this->add_control(
			'aka_value_color',
			array(
				'label'     => __( 'Value colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__aka-value' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'aka_spacing',
			array(
				'label'      => __( 'Spacing', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .apglos__aka' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: related terms.
	 *
	 * @return void
	 */
	private function section_style_related() {
		$this->start_controls_section(
			'style_related',
			array(
				'label'     => __( 'Related terms', 'apotheca-glossary' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array( 'show_related' => 'yes' ),
			)
		);

		$this->add_control(
			'related_prefix_heading',
			array( 'label' => __( 'Prefix', 'apotheca-glossary' ), 'type' => Controls_Manager::HEADING )
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'related_prefix_typography',
				'selector' => '{{WRAPPER}} .apglos__related-label',
			)
		);

		$this->add_control(
			'related_prefix_color',
			array(
				'label'     => __( 'Prefix colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__related-label' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'related_link_heading',
			array( 'label' => __( 'Links', 'apotheca-glossary' ), 'type' => Controls_Manager::HEADING, 'separator' => 'before' )
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'related_link_typography',
				'selector' => '{{WRAPPER}} .apglos__related-link',
			)
		);

		$this->add_control(
			'related_link_color',
			array(
				'label'     => __( 'Link colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__related-link' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'related_link_hover',
			array(
				'label'     => __( 'Link hover colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__related-link:hover' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'related_sep_color',
			array(
				'label'     => __( 'Separator colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__related-sep' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'related_spacing',
			array(
				'label'      => __( 'Spacing', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .apglos__related' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: entry spacing and divider.
	 *
	 * @return void
	 */
	private function section_style_entry_spacing() {
		$this->start_controls_section(
			'style_entry_spacing',
			array(
				'label' => __( 'Entry spacing', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'entry_gap',
			array(
				'label'      => __( 'Gap between entries', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
				'selectors'  => array( '{{WRAPPER}} .apglos__entry' => 'margin-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'entry_divider',
			array(
				'label'        => __( 'Divider', 'apotheca-glossary' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'On', 'apotheca-glossary' ),
				'label_off'    => __( 'Off', 'apotheca-glossary' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);

		$this->add_control(
			'entry_divider_color',
			array(
				'label'     => __( 'Divider colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'condition' => array( 'entry_divider' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .apglos__entry' => 'border-bottom-color: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'entry_divider_width',
			array(
				'label'      => __( 'Divider width', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
				'default'    => array( 'unit' => 'px', 'size' => 1 ),
				'condition'  => array( 'entry_divider' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .apglos__entry' => 'border-bottom-width: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'entry_divider_style',
			array(
				'label'     => __( 'Divider style', 'apotheca-glossary' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'solid',
				'options'   => array(
					'solid'  => __( 'Solid', 'apotheca-glossary' ),
					'dashed' => __( 'Dashed', 'apotheca-glossary' ),
					'dotted' => __( 'Dotted', 'apotheca-glossary' ),
					'double' => __( 'Double', 'apotheca-glossary' ),
				),
				'condition' => array( 'entry_divider' => 'yes' ),
				'selectors' => array( '{{WRAPPER}} .apglos__entry' => 'border-bottom-style: {{VALUE}};' ),
			)
		);

		// Padding below each entry, useful when a divider is on.
		$this->add_responsive_control(
			'entry_divider_padding',
			array(
				'label'      => __( 'Padding below entry', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
				'condition'  => array( 'entry_divider' => 'yes' ),
				'selectors'  => array( '{{WRAPPER}} .apglos__entry' => 'padding-bottom: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: search highlight.
	 *
	 * @return void
	 */
	private function section_style_highlight() {
		$this->start_controls_section(
			'style_highlight',
			array(
				'label' => __( 'Search highlight', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		// These set custom properties on the wrapper. The plugin's static CSS
		// reads them with !important, so the chosen colours win over a theme
		// that styles the bare <mark> element, without hardcoding anything.
		$this->add_control(
			'highlight_bg',
			array(
				'label'     => __( 'Highlight background', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--apglos-hl-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'highlight_color',
			array(
				'label'     => __( 'Highlight text colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}}' => '--apglos-hl-color: {{VALUE}};' ),
			)
		);

		// The persistent highlight shown on the term a reader is brought to from
		// an in-content link or a related term.
		$this->add_control(
			'target_heading',
			array(
				'label'     => __( 'Selected term', 'apotheca-glossary' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'target_bg',
			array(
				'label'       => __( 'Selected term highlight', 'apotheca-glossary' ),
				'type'        => Controls_Manager::COLOR,
				'description' => __( 'The background shown on the term a reader lands on from an in-content link or a related term, so it is clear which term is meant. It clears when they search or filter.', 'apotheca-glossary' ),
				'selectors'   => array( '{{WRAPPER}}' => '--apglos-target-bg: {{VALUE}};' ),
			)
		);

		$this->add_control(
			'target_radius',
			array(
				'label'      => __( 'Selected term corner radius', 'apotheca-glossary' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px'  => array(
						'min' => 0,
						'max' => 30,
					),
					'rem' => array(
						'min'  => 0,
						'max'  => 2,
						'step' => 0.1,
					),
				),
				'selectors'  => array( '{{WRAPPER}}' => '--apglos-target-radius: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Style: empty state.
	 *
	 * @return void
	 */
	private function section_style_empty() {
		$this->start_controls_section(
			'style_empty',
			array(
				'label' => __( 'Empty state', 'apotheca-glossary' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'empty_typography',
				'selector' => '{{WRAPPER}} .apglos__empty',
			)
		);

		$this->add_control(
			'empty_color',
			array(
				'label'     => __( 'Colour', 'apotheca-glossary' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .apglos__empty' => 'color: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'empty_align',
			array(
				'label'     => __( 'Alignment', 'apotheca-glossary' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => $this->align_options(),
				'selectors' => array( '{{WRAPPER}} .apglos__empty' => 'text-align: {{VALUE}};' ),
			)
		);

		$this->add_responsive_control(
			'empty_padding',
			array(
				'label'      => __( 'Padding', 'apotheca-glossary' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', 'rem' ),
				'selectors'  => array(
					'{{WRAPPER}} .apglos__empty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/*
	 * -------------------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * The text-alignment CHOOSE options, reused by count and empty state.
	 *
	 * @return array
	 */
	private function align_options() {
		return array(
			'left'   => array( 'title' => __( 'Left', 'apotheca-glossary' ), 'icon' => 'eicon-text-align-left' ),
			'center' => array( 'title' => __( 'Center', 'apotheca-glossary' ), 'icon' => 'eicon-text-align-center' ),
			'right'  => array( 'title' => __( 'Right', 'apotheca-glossary' ), 'icon' => 'eicon-text-align-right' ),
		);
	}

	/**
	 * Build the category dropdown choices for the "limit to one category"
	 * control: an All option plus every glossary category.
	 *
	 * @return array
	 */
	private function get_category_choices() {
		$choices = array( '' => __( 'All categories', 'apotheca-glossary' ) );

		$terms = get_terms(
			array(
				'taxonomy'   => APGLOS_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$choices[ $term->slug ] = $term->name;
			}
		}

		return $choices;
	}

	/*
	 * =========================================================================
	 * Render
	 * =========================================================================
	 */

	/**
	 * Render the widget by handing the settings to the shared renderer.
	 *
	 * @return void
	 */
	protected function render() {
		$s = $this->get_settings_for_display();

		$args = array(
			'show_search'         => $s['show_search'],
			'show_az_bar'         => $s['show_az_bar'],
			'show_09'             => $s['show_09'],
			'show_category'       => $s['show_category'],
			'show_count'          => $s['show_count'],
			'show_headings'       => $s['show_headings'],
			'show_category_label' => $s['show_category_label'],
			'show_also_known_as'  => $s['show_also_known_as'],
			'show_related'        => $s['show_related'],
			'empty_letters'       => ( isset( $s['empty_letters'] ) && 'hide' === $s['empty_letters'] ) ? 'hide' : 'grey',
			'category'            => isset( $s['category'] ) ? sanitize_title( $s['category'] ) : '',
			'search_placeholder'  => isset( $s['search_placeholder'] ) ? sanitize_text_field( $s['search_placeholder'] ) : '',
			'aka_label'           => isset( $s['aka_label'] ) ? sanitize_text_field( $s['aka_label'] ) : '',
			'related_label'       => isset( $s['related_label'] ) ? sanitize_text_field( $s['related_label'] ) : '',
			'clear_label'         => isset( $s['clear_label'] ) ? sanitize_text_field( $s['clear_label'] ) : '',
			'empty_message'       => isset( $s['empty_message'] ) ? sanitize_text_field( $s['empty_message'] ) : '',
		);

		// The preview state applies only inside the Elementor editor, so the
		// live page is never affected by it.
		if ( $this->is_edit_mode() && isset( $s['preview_state'] ) && in_array( $s['preview_state'], array( 'searching', 'no_results' ), true ) ) {
			$args['preview_state'] = $s['preview_state'];
		}

		echo Apglos_Renderer::render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped throughout the renderer.
	}

	/**
	 * Whether we are rendering inside the Elementor editor.
	 *
	 * @return bool
	 */
	private function is_edit_mode() {
		return isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}
}
