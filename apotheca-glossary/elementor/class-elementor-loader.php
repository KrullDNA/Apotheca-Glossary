<?php
/**
 * Elementor integration loader.
 *
 * Registers the KDNA Tools widget category and loads the glossary widget, but
 * only when Elementor is present. Everything here hangs off Elementor's own
 * hooks, so on a site without Elementor these methods simply never fire.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the glossary widget into Elementor.
 */
class Apglos_Elementor_Loader {

	/**
	 * The widget category slug.
	 *
	 * @var string
	 */
	const CATEGORY = 'kdna-tools';

	/**
	 * Hook into Elementor.
	 *
	 * @return void
	 */
	public function init() {
		// Add the KDNA Tools category to the widget panel.
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );

		// Register the widget itself.
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register the KDNA Tools category, so custom widgets sit in their own group
	 * rather than the general widget list.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager The elements manager.
	 * @return void
	 */
	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'KDNA Tools', 'apotheca-glossary' ),
				'icon'  => 'eicon-apps',
			)
		);
	}

	/**
	 * Load and register the glossary widget.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager The widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		require_once APGLOS_PATH . 'elementor/widgets/class-glossary-widget.php';
		$widgets_manager->register( new \Apglos_Glossary_Widget() );
	}
}
