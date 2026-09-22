<?php
/**
 * Shared front-end renderer for the glossary.
 *
 * Produces the glossary markup used by both the [apotheca_glossary] shortcode
 * (Stage 3) and the Elementor widget (Stage 4). All filtering happens in the
 * browser, so this class renders every term once, tagged with the data the
 * JavaScript needs to search, filter and highlight without a page reload.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the glossary HTML and registers the front-end assets.
 */
class Apglos_Renderer {

	/**
	 * Script and style handle.
	 *
	 * @var string
	 */
	const HANDLE = 'apotheca-glossary';

	/**
	 * Counter so each glossary on a page gets a unique instance id, which keeps
	 * anchor links (for related terms) unique when more than one is present.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Default settings. The shortcode and the widget both start from these and
	 * override what they expose. Booleans match the brief's default column.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Which controls to show.
			'show_search'         => true,
			'show_az_bar'         => true,
			'show_09'             => true,
			'show_category'       => true,
			'show_count'          => true,
			// Which parts of each entry to show.
			'show_headings'       => true,
			'show_category_label' => false,
			'show_also_known_as'  => false,
			'show_related'        => false,
			// Behaviour and labels.
			'empty_letters'       => 'grey', // 'grey' or 'hide'.
			'category'            => '',      // Limit to one category slug; empty = all.
			'search_placeholder'  => __( 'Search the glossary', 'apotheca-glossary' ),
			'aka_label'           => __( 'Also known as:', 'apotheca-glossary' ),
			'related_label'       => __( 'Related:', 'apotheca-glossary' ),
			'clear_label'         => __( 'Clear all', 'apotheca-glossary' ),
			'empty_message'       => __( 'No terms match your search.', 'apotheca-glossary' ),
			// Editor-only preview state: '' (default), 'searching' or 'no_results'.
			// The widget only ever sets this inside the Elementor editor; it is
			// never passed on the front end.
			'preview_state'       => '',
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Assets
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register (but do not enqueue) the front-end script and style.
	 *
	 * Registering on wp_enqueue_scripts, then enqueuing only inside render(),
	 * means the assets load on the pages where the glossary actually appears
	 * and nowhere else.
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style(
			self::HANDLE,
			APGLOS_URL . 'assets/css/apotheca-glossary.css',
			array(),
			APGLOS_VERSION
		);

		wp_register_script(
			self::HANDLE,
			APGLOS_URL . 'assets/js/apotheca-glossary.js',
			array(),
			APGLOS_VERSION,
			true // In the footer.
		);

		// Translatable templates the JavaScript builds strings from. The %1$s
		// and %2$s are the visible count and the total.
		wp_localize_script(
			self::HANDLE,
			'apglosL10n',
			array(
				'showing'    => __( 'Showing %1$s of %2$s terms', 'apotheca-glossary' ),
				'showingOne' => __( 'Showing %1$s of %2$s term', 'apotheca-glossary' ),
			)
		);
	}

	/**
	 * Enqueue the assets. Called from render(), so only where the glossary is.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Data
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Fetch and prepare every term to render.
	 *
	 * Returns entries sorted A to Z with the 0-9 bucket last, each carrying the
	 * fields the markup and the JavaScript need. Also returns the set of first
	 * characters actually present, so the A to Z bar can grey or hide the empty
	 * letters.
	 *
	 * @param array $settings The resolved settings.
	 * @return array {
	 *     @type array $entries Prepared entry arrays.
	 *     @type array $present Map of present bucket => true.
	 * }
	 */
	private static function get_entries( $settings ) {
		$query_args = array(
			'post_type'      => APGLOS_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);

		// Limit to a single category when the widget or shortcode asks for it.
		if ( ! empty( $settings['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => APGLOS_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $settings['category'],
				),
			);
		}

		$posts = get_posts( $query_args );

		// Build a name => slug map across ALL terms so related terms resolve
		// even when the current view is limited to one category.
		$name_to_slug = self::get_name_slug_map();

		$entries = array();
		$present = array();

		foreach ( $posts as $post ) {
			$bucket = get_post_meta( $post->ID, 'first_char', true );
			if ( '' === $bucket ) {
				$bucket = Apglos_Meta::compute_first_char( $post->post_title );
			}
			$present[ $bucket ] = true;

			// Synonyms, split from the pipe-delimited meta.
			$aka_raw = (string) get_post_meta( $post->ID, 'also_known_as', true );
			$synonyms = array_filter( array_map( 'trim', explode( '|', $aka_raw ) ), 'strlen' );

			// Related terms, resolved from names to on-page anchors.
			$related_raw = (string) get_post_meta( $post->ID, 'related_terms', true );
			$related     = array();
			foreach ( array_filter( array_map( 'trim', explode( '|', $related_raw ) ), 'strlen' ) as $rel_name ) {
				$key = self::normalise( $rel_name );
				if ( isset( $name_to_slug[ $key ] ) ) {
					$related[] = array(
						'name' => $rel_name,
						'slug' => $name_to_slug[ $key ],
					);
				}
			}

			// Categories assigned to this term (usually one).
			$terms      = get_the_terms( $post->ID, APGLOS_TAXONOMY );
			$categories = array();
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $t ) {
					$categories[] = array(
						'name' => $t->name,
						'slug' => $t->slug,
						'link' => get_term_link( $t ),
					);
				}
			}

			// Plain-text blob the search matches against: term, synonyms and
			// definition, lowercased and stripped of any markup.
			$search_blob = strtolower(
				wp_strip_all_tags(
					$post->post_title . ' ' . implode( ' ', $synonyms ) . ' ' . $post->post_content
				)
			);

			$entries[] = array(
				'id'         => $post->ID,
				'title'      => $post->post_title,
				'slug'       => $post->post_name,
				'content'    => $post->post_content,
				'bucket'     => $bucket,
				'synonyms'   => $synonyms,
				'related'    => $related,
				'categories' => $categories,
				'search'     => $search_blob,
			);
		}

		// Sort A to Z, with the 0-9 bucket after Z, then alphabetically by title.
		usort(
			$entries,
			function ( $a, $b ) {
				$rank_a = self::bucket_rank( $a['bucket'] );
				$rank_b = self::bucket_rank( $b['bucket'] );
				if ( $rank_a !== $rank_b ) {
					return $rank_a - $rank_b;
				}
				return strcasecmp( $a['title'], $b['title'] );
			}
		);

		return array(
			'entries' => $entries,
			'present' => $present,
		);
	}

	/**
	 * Sort rank for a bucket: A=65...Z=90, then 0-9 last.
	 *
	 * @param string $bucket The first-character bucket.
	 * @return int
	 */
	private static function bucket_rank( $bucket ) {
		if ( '0-9' === $bucket ) {
			return 200; // After every letter.
		}
		return ord( strtoupper( substr( $bucket, 0, 1 ) ) );
	}

	/**
	 * Build a lowercased term-name => slug map across the whole glossary.
	 *
	 * @return array
	 */
	private static function get_name_slug_map() {
		$map   = array();
		$posts = get_posts(
			array(
				'post_type'      => APGLOS_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);
		foreach ( $posts as $post ) {
			$map[ self::normalise( $post->post_title ) ] = $post->post_name;
		}
		return $map;
	}

	/**
	 * Normalise a term name for matching (trimmed, lowercased).
	 *
	 * @param string $value The value.
	 * @return string
	 */
	private static function normalise( $value ) {
		$value = trim( (string) $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * Wrap the first case-insensitive match of a query in a plain-text string
	 * with a highlight mark, returning safe HTML. Used only by the editor
	 * "searching" preview, so the front end never runs it.
	 *
	 * @param string $text  The plain text.
	 * @param string $query The substring to highlight.
	 * @return string
	 */
	private static function highlight_plain( $text, $query ) {
		$text = (string) $text;
		if ( '' === $query ) {
			return wptexturize( esc_html( $text ) );
		}

		$pos = stripos( $text, $query );
		if ( false === $pos ) {
			return wptexturize( esc_html( $text ) );
		}

		$len    = strlen( $query );
		$before = substr( $text, 0, $pos );
		$match  = substr( $text, $pos, $len );
		$after  = substr( $text, $pos + $len );

		return wptexturize( esc_html( $before ) )
			. '<mark class="apglos-mark">' . wptexturize( esc_html( $match ) ) . '</mark>'
			. wptexturize( esc_html( $after ) );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Render
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Render the glossary and return the HTML.
	 *
	 * @param array $args Settings overriding the defaults.
	 * @return string
	 */
	public static function render( $args = array() ) {
		$settings = wp_parse_args( $args, self::defaults() );

		// Coerce the toggles to real booleans (shortcode atts arrive as strings).
		foreach ( array( 'show_search', 'show_az_bar', 'show_09', 'show_category', 'show_count', 'show_headings', 'show_category_label', 'show_also_known_as', 'show_related' ) as $key ) {
			$settings[ $key ] = self::to_bool( $settings[ $key ] );
		}

		// Make sure the assets load on this page.
		self::enqueue_assets();

		$data    = self::get_entries( $settings );
		$entries = $data['entries'];
		$present = $data['present'];
		$total   = count( $entries );

		++self::$instance;
		$instance_id = 'apglos-' . self::$instance;

		// Stable anchor base for entry ids, so in-content links can target a
		// term reliably. The first glossary on a page uses the bare "apglos"
		// prefix (giving ids like apglos-term-slug, which linkify points to);
		// any further glossaries are numbered to keep ids unique.
		$anchor_base = ( 1 === self::$instance ) ? 'apglos' : 'apglos-' . self::$instance;

		// Editor-only preview state. The JavaScript skips any instance carrying
		// data-apglos-preview, so these mocks never move on the front end.
		$preview = in_array( $settings['preview_state'], array( 'searching', 'no_results' ), true ) ? $settings['preview_state'] : '';

		// A sample query for the "searching" mock, and the buckets its first few
		// results belong to (so the right letter headings stay visible).
		$preview_search  = '';
		$preview_visible = array();
		$preview_shown   = min( 3, $total );
		if ( 'searching' === $preview && $total > 0 ) {
			$first_title    = $entries[0]['title'];
			$preview_search = function_exists( 'mb_substr' ) ? mb_substr( $first_title, 0, 3 ) : substr( $first_title, 0, 3 );
			for ( $p = 0; $p < $preview_shown; $p++ ) {
				$preview_visible[ $entries[ $p ]['bucket'] ] = true;
			}
		} elseif ( 'no_results' === $preview ) {
			$preview_search = 'qzxwy';
		}

		// Data attributes carrying per-instance config the JavaScript reads.
		$wrapper_data = array(
			'data-apglos'        => '1',
			'data-instance'      => $instance_id,
			'data-empty-letters' => 'hide' === $settings['empty_letters'] ? 'hide' : 'grey',
			'data-total'         => (string) $total,
		);
		if ( $preview ) {
			$wrapper_data['data-apglos-preview'] = $preview;
		}

		ob_start();
		?>
		<div class="apglos"
			<?php
			foreach ( $wrapper_data as $attr => $value ) {
				printf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
			}
			?>
		>
			<?php if ( $settings['show_search'] || $settings['show_az_bar'] || $settings['show_category'] ) : ?>
				<div class="apglos__controls">

					<?php if ( $settings['show_search'] ) : ?>
						<div class="apglos__search-wrap" role="search">
							<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-search">
								<?php echo esc_html( $settings['search_placeholder'] ); ?>
							</label>
							<input
								type="search"
								id="<?php echo esc_attr( $instance_id ); ?>-search"
								class="apglos__search"
								data-apglos-search
								placeholder="<?php echo esc_attr( $settings['search_placeholder'] ); ?>"
								value="<?php echo esc_attr( $preview_search ); ?>"
								autocomplete="off"
							/>
						</div>
					<?php endif; ?>

					<?php if ( $settings['show_category'] && empty( $settings['category'] ) ) : ?>
						<div class="apglos__category-wrap">
							<label class="screen-reader-text" for="<?php echo esc_attr( $instance_id ); ?>-category">
								<?php esc_html_e( 'Filter by category', 'apotheca-glossary' ); ?>
							</label>
							<select id="<?php echo esc_attr( $instance_id ); ?>-category" class="apglos__category" data-apglos-category>
								<option value=""><?php esc_html_e( 'All categories', 'apotheca-glossary' ); ?></option>
								<?php foreach ( self::get_category_options() as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat['slug'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<?php if ( $settings['show_az_bar'] ) : ?>
						<?php echo self::render_az_bar( $present, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within the method. ?>
					<?php endif; ?>

					<button type="button" class="apglos__clear" data-apglos-clear <?php echo $preview ? '' : 'hidden'; ?>>
						<?php echo esc_html( $settings['clear_label'] ); ?>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $settings['show_count'] ) : ?>
				<?php
				// The visible count: the total by default, or the mocked number in
				// an editor preview state.
				if ( 'no_results' === $preview ) {
					$shown = 0;
				} elseif ( 'searching' === $preview ) {
					$shown = $preview_shown;
				} else {
					$shown = $total;
				}
				$count_text = sprintf(
					/* translators: 1: visible count, 2: total count */
					_n( 'Showing %1$s of %2$s term', 'Showing %1$s of %2$s terms', $total, 'apotheca-glossary' ),
					number_format_i18n( $shown ),
					number_format_i18n( $total )
				);
				?>
				<p class="apglos__count" data-apglos-count role="status" aria-live="polite" aria-atomic="true">
					<?php echo esc_html( $count_text ); ?>
				</p>
			<?php endif; ?>

			<div class="apglos__list" data-apglos-list>
				<?php
				$current_bucket = null;
				$row_index      = 0;
				foreach ( $entries as $entry ) :
					// In the "searching" mock, only the first few rows stay
					// visible and the first one gets a highlighted match; in the
					// "no results" mock every row is hidden (via CSS).
					$force_hidden = ( 'searching' === $preview && $row_index >= $preview_shown );
					$mark_query   = ( 'searching' === $preview && 0 === $row_index ) ? $preview_search : '';

					// Emit a letter heading when the bucket changes. In the
					// "searching" mock, hide headings whose group is not shown.
					if ( $settings['show_headings'] && $entry['bucket'] !== $current_bucket ) :
						$current_bucket = $entry['bucket'];
						$heading_hidden = ( 'searching' === $preview && empty( $preview_visible[ $entry['bucket'] ] ) );
						?>
						<h2 class="apglos__heading" data-apglos-heading data-letter="<?php echo esc_attr( $entry['bucket'] ); ?>" <?php echo $heading_hidden ? 'hidden' : ''; ?>>
							<?php echo esc_html( $entry['bucket'] ); ?>
						</h2>
						<?php
					endif;

					echo self::render_entry( $entry, $settings, $anchor_base, $force_hidden, $mark_query ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped within the method.
					++$row_index;
				endforeach;
				?>

				<p class="apglos__empty" data-apglos-empty <?php echo ( 'no_results' === $preview ) ? '' : 'hidden'; ?>>
					<?php echo esc_html( $settings['empty_message'] ); ?>
				</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the A to Z plus 0-9 bar.
	 *
	 * @param array $present  Map of present buckets.
	 * @param array $settings Resolved settings.
	 * @return string
	 */
	private static function render_az_bar( $present, $settings ) {
		$hide_empty = 'hide' === $settings['empty_letters'];
		$letters    = range( 'A', 'Z' );

		ob_start();
		?>
		<div class="apglos__az" role="group" aria-label="<?php esc_attr_e( 'Filter by first letter', 'apotheca-glossary' ); ?>" data-apglos-az>
			<?php
			foreach ( $letters as $letter ) :
				$is_present = ! empty( $present[ $letter ] );

				// Hidden empty letters are skipped entirely.
				if ( ! $is_present && $hide_empty ) {
					continue;
				}

				$classes = 'apglos__letter';
				if ( ! $is_present ) {
					$classes .= ' is-empty';
				}
				?>
				<button
					type="button"
					class="<?php echo esc_attr( $classes ); ?>"
					data-letter="<?php echo esc_attr( $letter ); ?>"
					aria-pressed="false"
					<?php disabled( ! $is_present ); ?>
				><?php echo esc_html( $letter ); ?></button>
			<?php endforeach; ?>

			<?php
			// The single 0-9 button, if enabled.
			if ( $settings['show_09'] ) :
				$has_09 = ! empty( $present['0-9'] );
				if ( $has_09 || ! $hide_empty ) :
					$classes = 'apglos__letter apglos__letter--num';
					if ( ! $has_09 ) {
						$classes .= ' is-empty';
					}
					?>
					<button
						type="button"
						class="<?php echo esc_attr( $classes ); ?>"
						data-letter="0-9"
						aria-pressed="false"
						<?php disabled( ! $has_09 ); ?>
					><?php echo esc_html_x( '0-9', 'number range button label', 'apotheca-glossary' ); ?></button>
					<?php
				endif;
			endif;
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render one glossary entry.
	 *
	 * @param array  $entry        The prepared entry.
	 * @param array  $settings     Resolved settings.
	 * @param string $anchor_base  The anchor prefix, for the entry id and the
	 *                             related-term links.
	 * @param bool   $force_hidden Start hidden (used by the editor preview mock).
	 * @param string $mark_query   Highlight this substring in the term (editor
	 *                             preview mock only).
	 * @return string
	 */
	private static function render_entry( $entry, $settings, $anchor_base, $force_hidden = false, $mark_query = '' ) {
		// The category slugs this entry belongs to, for client-side filtering.
		$cat_slugs = array();
		foreach ( $entry['categories'] as $cat ) {
			$cat_slugs[] = $cat['slug'];
		}
		$anchor = $anchor_base . '-term-' . $entry['slug'];

		// Term markup: plain by default, or with a highlighted match for the
		// editor "searching" preview.
		$term_html = ( '' !== $mark_query )
			? self::highlight_plain( $entry['title'], $mark_query )
			: wptexturize( esc_html( $entry['title'] ) );

		ob_start();
		?>
		<div class="apglos__entry"
			id="<?php echo esc_attr( $anchor ); ?>"
			data-apglos-entry
			data-letter="<?php echo esc_attr( $entry['bucket'] ); ?>"
			data-category="<?php echo esc_attr( implode( ' ', $cat_slugs ) ); ?>"
			data-search="<?php echo esc_attr( $entry['search'] ); ?>"
			<?php echo $force_hidden ? 'hidden' : ''; ?>
		>
			<?php // Term, always shown, bold by default, curled apostrophes. ?>
			<span class="apglos__term" data-apglos-term><?php echo wp_kses_post( $term_html ); ?></span>

			<?php // Category label, off by default, links to that filtered view. ?>
			<?php if ( $settings['show_category_label'] && ! empty( $entry['categories'] ) ) : ?>
				<span class="apglos__cat-labels">
					<?php foreach ( $entry['categories'] as $cat ) : ?>
						<a class="apglos__cat-label"
							href="<?php echo esc_url( is_wp_error( $cat['link'] ) ? '#' : $cat['link'] ); ?>"
							data-apglos-cat-link="<?php echo esc_attr( $cat['slug'] ); ?>"
						><?php echo esc_html( $cat['name'] ); ?></a>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>

			<?php // Definition, always shown, run through wptexturize. ?>
			<div class="apglos__definition" data-apglos-def>
				<?php echo wp_kses_post( wptexturize( wpautop( $entry['content'] ) ) ); ?>
			</div>

			<?php // Also known as, off by default. ?>
			<?php if ( $settings['show_also_known_as'] && ! empty( $entry['synonyms'] ) ) : ?>
				<p class="apglos__aka" data-apglos-aka>
					<span class="apglos__aka-label"><?php echo esc_html( $settings['aka_label'] ); ?></span>
					<span class="apglos__aka-value"><?php echo esc_html( implode( ', ', $entry['synonyms'] ) ); ?></span>
				</p>
			<?php endif; ?>

			<?php // Related terms, off by default, linked to jump on the page. ?>
			<?php if ( $settings['show_related'] && ! empty( $entry['related'] ) ) : ?>
				<p class="apglos__related">
					<span class="apglos__related-label"><?php echo esc_html( $settings['related_label'] ); ?></span>
					<?php
					$links = array();
					foreach ( $entry['related'] as $rel ) {
						$links[] = sprintf(
							'<a class="apglos__related-link" href="#%1$s" data-apglos-related="%2$s">%3$s</a>',
							esc_attr( $anchor_base . '-term-' . $rel['slug'] ),
							esc_attr( $rel['slug'] ),
							esc_html( $rel['name'] )
						);
					}
					// The separator between related links.
					echo wp_kses_post( implode( '<span class="apglos__related-sep">, </span>', $links ) );
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get category options for the dropdown: name and slug, ordered by name.
	 *
	 * @return array
	 */
	private static function get_category_options() {
		$terms = get_terms(
			array(
				'taxonomy'   => APGLOS_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		$options = array();
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}
		return $options;
	}

	/**
	 * Cast a mixed value (including shortcode att strings) to a boolean.
	 *
	 * @param mixed $value The value.
	 * @return bool
	 */
	private static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}
}
