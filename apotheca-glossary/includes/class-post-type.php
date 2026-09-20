<?php
/**
 * Custom post type, taxonomy and admin columns for the glossary.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the `apglos_term` post type and `apglos_category` taxonomy, seeds
 * the ten categories, and adds the sortable admin columns to the term list.
 */
class Apglos_Post_Type {

	/**
	 * The ten glossary categories, seeded on activation.
	 *
	 * The order here is the order the brief lists them in. Each becomes a term
	 * in the apglos_category taxonomy. The bracketed counts in the brief are
	 * just how many terms each will hold once the CSV is imported, they are not
	 * stored here.
	 *
	 * @var string[]
	 */
	const DEFAULT_CATEGORIES = array(
		'Skin anatomy',
		'Moisture and barrier',
		'How formulas work',
		'Actives and ingredients',
		'Sun protection',
		'Label and packaging',
		'Claims and certification',
		'Textures and formats',
		'Skin concerns',
		'Routine and habits',
	);

	/**
	 * Hook everything into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		// Register the post type and taxonomy on the standard init hook.
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );

		// After registration, flush rewrite rules once if the slug just changed
		// on the settings page.
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );

		// Add and populate the admin columns on the term list screen.
		add_filter( 'manage_' . APGLOS_POST_TYPE . '_posts_columns', array( $this, 'set_admin_columns' ) );
		add_action( 'manage_' . APGLOS_POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );

		// Make the category and first character columns sortable.
		add_filter( 'manage_edit-' . APGLOS_POST_TYPE . '_sortable_columns', array( $this, 'set_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register the `apglos_term` custom post type.
	 *
	 * Public, with an archive, using /glossary/ as its URL base. The post title
	 * holds the term and the post content holds the definition, so the standard
	 * title and editor are all we need on the edit screen.
	 *
	 * @return void
	 */
	public function register_post_type() {
		// The URL base, configurable on the settings page, defaulting to
		// "glossary".
		$slug = function_exists( 'apglos_get_setting' ) ? sanitize_title( (string) apglos_get_setting( 'glossary_slug' ) ) : 'glossary';
		if ( '' === $slug ) {
			$slug = 'glossary';
		}

		$labels = array(
			'name'                  => _x( 'Glossary Terms', 'Post type general name', 'apotheca-glossary' ),
			'singular_name'         => _x( 'Glossary Term', 'Post type singular name', 'apotheca-glossary' ),
			'menu_name'             => _x( 'Glossary', 'Admin Menu text', 'apotheca-glossary' ),
			'name_admin_bar'        => _x( 'Glossary Term', 'Add New on Toolbar', 'apotheca-glossary' ),
			'add_new'               => __( 'Add New', 'apotheca-glossary' ),
			'add_new_item'          => __( 'Add New Term', 'apotheca-glossary' ),
			'new_item'              => __( 'New Term', 'apotheca-glossary' ),
			'edit_item'             => __( 'Edit Term', 'apotheca-glossary' ),
			'view_item'             => __( 'View Term', 'apotheca-glossary' ),
			'all_items'             => __( 'All Terms', 'apotheca-glossary' ),
			'search_items'          => __( 'Search Terms', 'apotheca-glossary' ),
			'not_found'             => __( 'No terms found.', 'apotheca-glossary' ),
			'not_found_in_trash'    => __( 'No terms found in Trash.', 'apotheca-glossary' ),
			'featured_image'        => __( 'Term Image', 'apotheca-glossary' ),
			'archives'              => __( 'Glossary Archive', 'apotheca-glossary' ),
			'item_published'        => __( 'Term published.', 'apotheca-glossary' ),
			'item_updated'          => __( 'Term updated.', 'apotheca-glossary' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			// The archive lives at the configured slug, matching the rewrite.
			'has_archive'        => $slug,
			// The term editor: title (the term) and content (the definition).
			'supports'           => array( 'title', 'editor', 'revisions', 'page-attributes' ),
			'menu_icon'          => 'dashicons-book-alt',
			// A single top-level Glossary menu that later stages hang the
			// importer and settings pages off.
			'menu_position'      => 25,
			'show_in_rest'       => true,
			'rewrite'            => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			// Link the category taxonomy at registration too, so it appears on
			// the edit screen.
			'taxonomies'         => array( APGLOS_TAXONOMY ),
		);

		register_post_type( APGLOS_POST_TYPE, $args );
	}

	/**
	 * Register the `apglos_category` taxonomy.
	 *
	 * Hierarchical, so it behaves like categories rather than tags, with a
	 * /glossary-category/ URL base. Attached to the glossary post type.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => _x( 'Glossary Categories', 'taxonomy general name', 'apotheca-glossary' ),
			'singular_name'     => _x( 'Glossary Category', 'taxonomy singular name', 'apotheca-glossary' ),
			'search_items'      => __( 'Search Categories', 'apotheca-glossary' ),
			'all_items'         => __( 'All Categories', 'apotheca-glossary' ),
			'parent_item'       => __( 'Parent Category', 'apotheca-glossary' ),
			'parent_item_colon' => __( 'Parent Category:', 'apotheca-glossary' ),
			'edit_item'         => __( 'Edit Category', 'apotheca-glossary' ),
			'update_item'       => __( 'Update Category', 'apotheca-glossary' ),
			'add_new_item'      => __( 'Add New Category', 'apotheca-glossary' ),
			'new_item_name'     => __( 'New Category Name', 'apotheca-glossary' ),
			'menu_name'         => __( 'Categories', 'apotheca-glossary' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array(
				'slug'       => 'glossary-category',
				'with_front' => false,
			),
		);

		register_taxonomy( APGLOS_TAXONOMY, array( APGLOS_POST_TYPE ), $args );
	}

	/**
	 * Seed the ten default categories.
	 *
	 * Runs on activation. Each category is only created if a term with that
	 * name does not already exist, so re-activating never duplicates them.
	 *
	 * @return void
	 */
	public static function seed_categories() {
		foreach ( self::DEFAULT_CATEGORIES as $category_name ) {
			// term_exists() checks by name within this taxonomy.
			if ( ! term_exists( $category_name, APGLOS_TAXONOMY ) ) {
				wp_insert_term( $category_name, APGLOS_TAXONOMY );
			}
		}
	}

	/**
	 * Flush rewrite rules once, if the settings page flagged a slug change.
	 *
	 * Runs after the post type is registered with its new slug, so the new
	 * /slug/ URLs resolve straight away without the admin needing to visit the
	 * Permalinks screen.
	 *
	 * @return void
	 */
	public function maybe_flush() {
		if ( get_option( 'apglos_needs_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'apglos_needs_flush' );
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 * Admin columns
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Define the columns shown on the glossary term list screen.
	 *
	 * We keep the checkbox and title, add our own Category and Letter columns,
	 * then keep the date last.
	 *
	 * @param array $columns Existing columns, keyed by column id.
	 * @return array Reordered columns.
	 */
	public function set_admin_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			// Rebuild the column list in order, inserting our two columns after
			// the title.
			if ( 'date' === $key ) {
				$new_columns['apglos_category'] = __( 'Category', 'apotheca-glossary' );
				$new_columns['apglos_letter']   = __( 'Letter', 'apotheca-glossary' );
			}
			$new_columns[ $key ] = $label;
		}

		// If for some reason there was no date column, append ours at the end.
		if ( ! isset( $new_columns['apglos_category'] ) ) {
			$new_columns['apglos_category'] = __( 'Category', 'apotheca-glossary' );
			$new_columns['apglos_letter']   = __( 'Letter', 'apotheca-glossary' );
		}

		return $new_columns;
	}

	/**
	 * Fill in the content of our custom columns for each row.
	 *
	 * @param string $column  The column id being rendered.
	 * @param int    $post_id The term (post) id for this row.
	 * @return void
	 */
	public function render_admin_column( $column, $post_id ) {
		switch ( $column ) {
			case 'apglos_category':
				// List the term's categories as links to the filtered term list.
				$terms = get_the_terms( $post_id, APGLOS_TAXONOMY );

				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					echo '<span aria-hidden="true">&#8212;</span>';
					break;
				}

				$links = array();
				foreach ( $terms as $term ) {
					$url = add_query_arg(
						array(
							'post_type'      => APGLOS_POST_TYPE,
							APGLOS_TAXONOMY  => $term->slug,
						),
						admin_url( 'edit.php' )
					);
					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
				}

				// Category names are safe, the links are escaped above.
				echo wp_kses_post( implode( ', ', $links ) );
				break;

			case 'apglos_letter':
				// Show the cached first character. If it is not stored yet
				// (a term added before the meta was in place), fall back to
				// computing it live from the title so the column is never blank.
				$first_char = get_post_meta( $post_id, 'first_char', true );

				if ( '' === $first_char ) {
					$first_char = Apglos_Meta::compute_first_char( get_the_title( $post_id ) );
				}

				echo esc_html( $first_char );
				break;
		}
	}

	/**
	 * Register which of our columns can be sorted.
	 *
	 * @param array $columns Sortable columns, keyed by column id.
	 * @return array
	 */
	public function set_sortable_columns( $columns ) {
		// The value here is the query var used in handle_column_sorting().
		$columns['apglos_letter']   = 'apglos_letter';
		$columns['apglos_category'] = 'apglos_category';
		return $columns;
	}

	/**
	 * Apply the ordering when one of our sortable columns is clicked.
	 *
	 * @param WP_Query $query The main admin query.
	 * @return void
	 */
	public function handle_column_sorting( $query ) {
		// Only touch the main query in the admin, and only for our post type.
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( APGLOS_POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );

		if ( 'apglos_letter' === $orderby ) {
			// Sort by the cached first character meta value.
			$query->set( 'meta_key', 'first_char' );
			$query->set( 'orderby', 'meta_value' );
		} elseif ( 'apglos_category' === $orderby ) {
			// Sorting by a taxonomy in the admin list is done with a small
			// clause filter, added only for this one query.
			add_filter( 'posts_clauses', array( $this, 'sort_by_category_clauses' ), 10, 2 );
		}
	}

	/**
	 * Join the taxonomy tables so the term list can be ordered by category name.
	 *
	 * This only runs when the Category column has been clicked (it is added in
	 * handle_column_sorting and removed again here after it fires once).
	 *
	 * @param array    $clauses The SQL clauses for the query.
	 * @param WP_Query $query   The query being run.
	 * @return array Modified clauses.
	 */
	public function sort_by_category_clauses( $clauses, $query ) {
		global $wpdb;

		// Remove ourselves straight away, so we only ever affect this one query.
		remove_filter( 'posts_clauses', array( $this, 'sort_by_category_clauses' ), 10 );

		$clauses['join'] .= "
			LEFT JOIN {$wpdb->term_relationships} AS apglos_tr ON ({$wpdb->posts}.ID = apglos_tr.object_id)
			LEFT JOIN {$wpdb->term_taxonomy} AS apglos_tt ON (apglos_tr.term_taxonomy_id = apglos_tt.term_taxonomy_id AND apglos_tt.taxonomy = '" . esc_sql( APGLOS_TAXONOMY ) . "')
			LEFT JOIN {$wpdb->terms} AS apglos_t ON (apglos_tt.term_id = apglos_t.term_id)
		";

		$order = ( 'DESC' === strtoupper( $query->get( 'order' ) ) ) ? 'DESC' : 'ASC';

		$clauses['groupby'] = "{$wpdb->posts}.ID";
		$clauses['orderby'] = "apglos_t.name {$order}";

		return $clauses;
	}
}
