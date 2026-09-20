<?php
/**
 * Search-visibility output: schema and canonicals.
 *
 * Adds DefinedTerm schema to each single term page and DefinedTermSet schema to
 * the glossary archive, and makes filtered views (the ?q=, ?letter= and ?cat=
 * query strings the front end writes) canonical to the unfiltered glossary.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs DefinedTerm / DefinedTermSet schema and handles canonicals.
 */
class Apglos_Schema {

	/**
	 * The query args the front-end filters use, stripped from canonicals.
	 *
	 * @var string[]
	 */
	const FILTER_ARGS = array( 'q', 'letter', 'cat' );

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		// Schema in the head.
		add_action( 'wp_head', array( $this, 'output_schema' ) );

		// Canonicals: strip the filter args wherever a canonical is produced.
		add_filter( 'get_canonical_url', array( $this, 'filter_canonical' ) );
		add_filter( 'wpseo_canonical', array( $this, 'filter_canonical' ) );

		// A canonical tag for the archive and category views when no SEO plugin
		// is providing one (core only outputs rel-canonical for singular).
		add_action( 'wp_head', array( $this, 'output_archive_canonical' ), 9 );

		// Bust the cached set schema when the glossary changes.
		add_action( 'save_post_' . APGLOS_POST_TYPE, array( $this, 'clear_cache' ) );
		add_action( 'before_delete_post', array( $this, 'clear_cache' ) );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Schema
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Output the appropriate schema for the current view.
	 *
	 * @return void
	 */
	public function output_schema() {
		if ( is_singular( APGLOS_POST_TYPE ) ) {
			$this->print_json( $this->build_defined_term( get_queried_object() ) );
		} elseif ( is_post_type_archive( APGLOS_POST_TYPE ) || is_tax( APGLOS_TAXONOMY ) ) {
			$this->print_json( $this->get_defined_term_set() );
		}
	}

	/**
	 * Build DefinedTerm schema for one term.
	 *
	 * @param WP_Post $post The term.
	 * @return array
	 */
	private function build_defined_term( $post ) {
		$set_url = $this->set_url();

		$data = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'DefinedTerm',
			'name'             => $post->post_title,
			'termCode'         => $post->post_name,
			'url'              => get_permalink( $post ),
			'description'      => $this->plain_definition( $post->post_content ),
			'inDefinedTermSet' => array(
				'@type' => 'DefinedTermSet',
				'@id'   => $set_url . '#glossary',
				'name'  => $this->set_name(),
				'url'   => $set_url,
			),
		);

		return $data;
	}

	/**
	 * Build (and cache) DefinedTermSet schema listing every term.
	 *
	 * @return array
	 */
	private function get_defined_term_set() {
		$version = get_option( 'apglos_terms_version', '0' );
		$cached  = get_transient( 'apglos_schema_set' );
		if ( is_array( $cached ) && isset( $cached['version'] ) && $cached['version'] === $version ) {
			return $cached['data'];
		}

		$set_url = $this->set_url();

		$posts = get_posts(
			array(
				'post_type'      => APGLOS_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$has_terms = array();
		foreach ( $posts as $post ) {
			$has_terms[] = array(
				'@type'       => 'DefinedTerm',
				'name'        => $post->post_title,
				'termCode'    => $post->post_name,
				'url'         => get_permalink( $post->ID ),
				'description' => $this->plain_definition( $post->post_content ),
			);
		}

		$data = array(
			'@context'       => 'https://schema.org',
			'@type'          => 'DefinedTermSet',
			'@id'            => $set_url . '#glossary',
			'name'           => $this->set_name(),
			'url'            => $set_url,
			'hasDefinedTerm' => $has_terms,
		);

		set_transient(
			'apglos_schema_set',
			array( 'version' => $version, 'data' => $data ),
			WEEK_IN_SECONDS
		);

		return $data;
	}

	/**
	 * Print a schema array as a JSON-LD script tag.
	 *
	 * @param array $data The schema data.
	 * @return void
	 */
	private function print_json( $data ) {
		if ( empty( $data ) ) {
			return;
		}
		echo '<script type="application/ld+json">'
			. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>' . "\n";
	}

	/*
	 * -------------------------------------------------------------------------
	 * Canonicals
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Strip the front-end filter args from a canonical URL, so a shared
	 * filtered view points back at the unfiltered glossary.
	 *
	 * @param string $url The canonical URL.
	 * @return string
	 */
	public function filter_canonical( $url ) {
		if ( ! $url ) {
			return $url;
		}
		return remove_query_arg( self::FILTER_ARGS, $url );
	}

	/**
	 * Output a canonical link on the archive and category views when no SEO
	 * plugin is handling canonicals. Core does singular pages itself.
	 *
	 * @return void
	 */
	public function output_archive_canonical() {
		// Leave it to the SEO plugin if one is active.
		if ( function_exists( 'YoastSEO' ) || defined( 'WPSEO_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}

		$url = '';
		if ( is_post_type_archive( APGLOS_POST_TYPE ) ) {
			$url = $this->set_url();
		} elseif ( is_tax( APGLOS_TAXONOMY ) ) {
			$term = get_queried_object();
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				$url  = is_wp_error( $link ) ? '' : $link;
			}
		}

		if ( $url ) {
			echo '<link rel="canonical" href="' . esc_url( remove_query_arg( self::FILTER_ARGS, $url ) ) . '" />' . "\n";
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 * Helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * The URL of the glossary set (the post type archive, or the site home as a
	 * fallback if the archive is disabled).
	 *
	 * @return string
	 */
	private function set_url() {
		$archive = get_post_type_archive_link( APGLOS_POST_TYPE );
		return $archive ? $archive : home_url( '/' );
	}

	/**
	 * The name of the glossary set.
	 *
	 * @return string
	 */
	private function set_name() {
		return __( 'Apotheca Glossary', 'apotheca-glossary' );
	}

	/**
	 * A plain-text, single-line definition for schema, with entities decoded.
	 *
	 * @param string $content The stored definition.
	 * @return string
	 */
	private function plain_definition( $content ) {
		$text = wp_strip_all_tags( $content );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Clear the cached set schema.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( 'apglos_schema_set' );
	}
}
