<?php
/**
 * Plugin Name:       Apotheca® Glossary
 * Plugin URI:        https://apothecacosmetics.com/
 * Description:        A searchable, filterable glossary of cosmetic terminology, with automatic in-content linking of glossary terms in blog posts.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Krull Design & Advertising
 * Author URI:        https://krulldna.com/
 * Text Domain:       apotheca-glossary
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly, outside of WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * -----------------------------------------------------------------------------
 * Constants
 * -----------------------------------------------------------------------------
 * A handful of values the whole plugin refers back to. Defining them once here
 * means the version number, paths and URLs live in a single place.
 */

define( 'APGLOS_VERSION', '1.0.0' );
define( 'APGLOS_FILE', __FILE__ );
define( 'APGLOS_PATH', plugin_dir_path( __FILE__ ) );
define( 'APGLOS_URL', plugin_dir_url( __FILE__ ) );
define( 'APGLOS_BASENAME', plugin_basename( __FILE__ ) );

// The custom post type and taxonomy keys, kept as constants so a typo in one
// place cannot quietly break registration or a query somewhere else.
define( 'APGLOS_POST_TYPE', 'apglos_term' );
define( 'APGLOS_TAXONOMY', 'apglos_category' );

/*
 * -----------------------------------------------------------------------------
 * Includes
 * -----------------------------------------------------------------------------
 * Load the class files that make up the plugin. The main file stays lean: it
 * loads classes and wires up hooks, the real work lives in the classes.
 */

require_once APGLOS_PATH . 'includes/class-post-type.php';
require_once APGLOS_PATH . 'includes/class-meta.php';
require_once APGLOS_PATH . 'includes/class-importer.php';

/*
 * -----------------------------------------------------------------------------
 * Boot
 * -----------------------------------------------------------------------------
 * Create the plugin's objects once WordPress has loaded, and let each one
 * register its own hooks. Everything hangs off the standard load order.
 */

/**
 * Start the plugin.
 *
 * Instantiates each feature class and calls its init() method, which is where
 * that class hooks itself into WordPress.
 *
 * @return void
 */
function apglos_init() {
	// The post type and taxonomy, plus the admin columns for the term list.
	$post_type = new Apglos_Post_Type();
	$post_type->init();

	// The custom fields (also known as, related terms, cached first character)
	// and the meta box that edits the first two.
	$meta = new Apglos_Meta();
	$meta->init();

	// The CSV importer / exporter admin page under the Glossary menu.
	$importer = new Apglos_Importer();
	$importer->init();
}
add_action( 'plugins_loaded', 'apglos_init' );

/*
 * -----------------------------------------------------------------------------
 * Activation
 * -----------------------------------------------------------------------------
 * Runs once, when the plugin is activated. It registers the post type and
 * taxonomy so their rewrite rules exist, seeds the ten glossary categories,
 * then flushes rewrite rules so the /glossary/ URLs work straight away.
 */

/**
 * Activation routine.
 *
 * @return void
 */
function apglos_activate() {
	// Make sure the post type and taxonomy exist before we flush rewrite rules
	// or add terms to the taxonomy.
	$post_type = new Apglos_Post_Type();
	$post_type->register_post_type();
	$post_type->register_taxonomy();

	// Seed the ten glossary categories, skipping any that already exist.
	Apglos_Post_Type::seed_categories();

	// Rebuild permalinks so /glossary/ and /glossary-category/ resolve.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'apglos_activate' );

/**
 * Deactivation routine.
 *
 * Flushes rewrite rules so the glossary URLs are cleaned up. It does not delete
 * any terms or content, deactivating should never destroy the client's data.
 *
 * @return void
 */
function apglos_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'apglos_deactivate' );
