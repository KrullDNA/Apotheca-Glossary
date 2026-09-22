<?php
/**
 * Automatic in-content linking of glossary terms.
 *
 * The largest SEO gain in the build, and the easiest to get wrong, so it runs
 * with guard rails. On the_content, at render time (never written back into the
 * stored post), it links the first mention of each glossary term, up to a cap,
 * skipping headings, existing links, alt text, code blocks, shortcodes and the
 * glossary's own output. It ships switched off.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The the_content linkify filter and its guard rails.
 */
class Apglos_Linkify {

	/**
	 * Tags whose entire subtree is left untouched.
	 *
	 * @var string[]
	 */
	const SKIP_TAGS = array( 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'script', 'style', 'textarea', 'button' );

	/**
	 * Per-post meta: opt out of linking entirely.
	 *
	 * @var string
	 */
	const META_OPTOUT = 'apglos_no_linkify';

	/**
	 * Per-post meta: override the links-per-post cap.
	 *
	 * @var string
	 */
	const META_CAP = 'apglos_linkify_cap';

	/**
	 * Nonce action for the per-post meta box.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'apglos_linkify_meta';

	/**
	 * Per-post running state within a single request, so the cap and the
	 * first-occurrence rule hold across several pieces of content (for example
	 * multiple Elementor Text Editor widgets in one post).
	 *
	 * @var array<int, array{links:int, terms:array}>
	 */
	private $runs = array();

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		// The linkify filter, late so shortcodes and wpautop have already run.
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );

		// Elementor renders Text Editor widgets without going through
		// the_content, so linkify them via Elementor's own widget filter. The
		// hook simply never fires on sites without Elementor.
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_elementor_widget' ), 20, 2 );

		// Per-post controls (opt out and cap override).
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );

		// Invalidate caches when the glossary changes.
		add_action( 'save_post_' . APGLOS_POST_TYPE, array( $this, 'bump_version' ) );
		add_action( 'before_delete_post', array( $this, 'maybe_bump_on_delete' ) );

		// When any post is saved, drop its own linkify cache.
		add_action( 'save_post', array( $this, 'clear_post_cache' ) );

		// A tiny inline style for the hidden note and icon, only when linking is
		// on. Cheaper than a whole stylesheet for a couple of rules.
		add_action( 'wp_head', array( $this, 'print_inline_style' ) );
	}

	/*
	 * -------------------------------------------------------------------------
	 * The filter
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Whether linkify should run for the current context, and with what cap.
	 *
	 * @param int $post_id The current post id.
	 * @return int The links cap, or 0 to skip.
	 */
	private function context_cap( $post_id ) {
		// Never in admin, feeds, or REST/editor renders.
		if ( is_admin() || is_feed() ) {
			return 0;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 0;
		}

		$settings = apglos_get_settings();

		// Ships off: do nothing until switched on.
		if ( empty( $settings['linkify_enabled'] ) || ! $post_id ) {
			return 0;
		}

		// Never linkify the glossary itself, or an excluded post type.
		$post_type = get_post_type( $post_id );
		if ( APGLOS_POST_TYPE === $post_type ) {
			return 0;
		}
		if ( in_array( $post_type, (array) $settings['linkify_excluded_post_types'], true ) ) {
			return 0;
		}

		// Per-post opt out.
		if ( get_post_meta( $post_id, self::META_OPTOUT, true ) ) {
			return 0;
		}

		// The cap: the global default, or a per-post override.
		$cap      = (int) $settings['linkify_cap'];
		$override = get_post_meta( $post_id, self::META_CAP, true );
		if ( '' !== $override && (int) $override >= 0 ) {
			$cap = (int) $override;
		}

		return max( 0, $cap );
	}

	/**
	 * Linkify glossary terms in post content (the standard WordPress path:
	 * classic and block editors, and Elementor's Post Content widget).
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function filter_content( $content ) {
		$post_id = get_the_ID();
		$cap     = $this->context_cap( $post_id );
		if ( $cap <= 0 ) {
			return $content;
		}

		// Cache: keyed by post, validated by a hash of the inputs, so it
		// self-invalidates when the content, settings or glossary change and is
		// also cleared explicitly on term and post save.
		$settings = apglos_get_settings();
		$version  = get_option( 'apglos_terms_version', '0' ) . '|' . APGLOS_VERSION;
		$hash     = md5( $content . '|' . wp_json_encode( $settings ) . '|' . $cap . '|' . $version );
		$key      = 'apglos_linkify_' . $post_id;
		$cached   = get_transient( $key );

		if ( is_array( $cached ) && isset( $cached['hash'] ) && $cached['hash'] === $hash ) {
			return $cached['html'];
		}

		$html = $this->process( $content, $cap );

		set_transient( $key, array( 'hash' => $hash, 'html' => $html ), WEEK_IN_SECONDS );

		return $html;
	}

	/**
	 * The Elementor widget names whose prose linkify may link inside.
	 *
	 * Text Editor is always included. JetEngine's Dynamic Field is added when
	 * enabled, since many themes render body copy through it. Sites can extend
	 * this with the apglos_linkify_widget_names filter.
	 *
	 * @return string[]
	 */
	private function linkable_widget_names() {
		$names = array( 'text-editor' );

		if ( ! empty( apglos_get_setting( 'linkify_dynamic_fields' ) ) ) {
			$names[] = 'jet-listing-dynamic-field';
		}

		return (array) apply_filters( 'apglos_linkify_widget_names', $names );
	}

	/**
	 * Linkify glossary terms inside a prose-bearing Elementor/JetEngine widget.
	 *
	 * Elementor does not pass this text through the_content, so we hook its own
	 * widget filter. The cap and the first-occurrence rule are shared across
	 * every such widget in the post via $this->runs, so a post with several
	 * widgets still gets at most the cap, each term linked once.
	 *
	 * @param string $content The rendered widget HTML.
	 * @param object $widget  The Elementor widget instance.
	 * @return string
	 */
	public function filter_elementor_widget( $content, $widget ) {
		// Only link inside prose-bearing widgets. Text Editor always; JetEngine
		// Dynamic Field when enabled (many sites render body copy through it).
		// Other widgets, and the Post Content widget (which already runs through
		// the_content), are left alone.
		$name = ( is_object( $widget ) && method_exists( $widget, 'get_name' ) ) ? $widget->get_name() : '';
		if ( '' === $name || ! in_array( $name, $this->linkable_widget_names(), true ) ) {
			return $content;
		}

		// Skip trivially short fields, so metadata like a date, a reading time
		// or a single tag is not linked, only real body copy. Filterable.
		$min = (int) apply_filters( 'apglos_linkify_min_field_length', 40, $name );
		if ( $min > 0 && strlen( trim( wp_strip_all_tags( $content ) ) ) < $min ) {
			return $content;
		}

		$post_id = get_the_ID();
		$cap     = $this->context_cap( $post_id );
		if ( $cap <= 0 ) {
			return $content;
		}

		// Start (or continue) this post's shared run.
		if ( ! isset( $this->runs[ $post_id ] ) ) {
			$this->runs[ $post_id ] = array( 'links' => 0, 'terms' => array() );
		}
		if ( $this->runs[ $post_id ]['links'] >= $cap ) {
			return $content; // Cap already reached earlier in the post.
		}

		$dict = $this->get_dictionary();
		if ( empty( $dict['regex'] ) ) {
			return $content;
		}

		$state = array(
			'links' => $this->runs[ $post_id ]['links'],
			'cap'   => $cap,
			'terms' => $this->runs[ $post_id ]['terms'],
			'dict'  => $dict,
		);

		$html = $this->transform( $content, $state );

		// Carry the running totals forward to the next widget in this post.
		$this->runs[ $post_id ]['links'] = $state['links'];
		$this->runs[ $post_id ]['terms'] = $state['terms'];

		return $html;
	}

	/**
	 * Link a piece of content, starting from a clean state (used by the
	 * the_content path).
	 *
	 * @param string $content The content.
	 * @param int    $cap     Maximum links to add.
	 * @return string
	 */
	private function process( $content, $cap ) {
		$dict = $this->get_dictionary();
		if ( empty( $dict['regex'] ) ) {
			return $content;
		}

		$state = array(
			'links' => 0,
			'cap'   => $cap,
			'terms' => array(), // Slugs already linked (first occurrence only).
			'dict'  => $dict,
		);

		return $this->transform( $content, $state );
	}

	/**
	 * The DOM transform: parse the HTML, link eligible text, and return it,
	 * carrying the running link count and linked terms in $state so callers can
	 * enforce the cap and the first-occurrence rule across several pieces.
	 *
	 * @param string $content The content.
	 * @param array  $state   Running state (by reference), with keys links,
	 *                        cap, terms and dict.
	 * @return string
	 */
	private function transform( $content, &$state ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return $content; // Can't parse safely, so leave it alone.
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument( '1.0', 'UTF-8' );

		// The XML PI forces UTF-8; the flags stop a doctype, html or body being
		// added, so we get back exactly what we put in.
		$loaded = $dom->loadHTML(
			'<?xml encoding="utf-8"?><div id="apglos-linkify-root">' . $content . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		if ( ! $loaded ) {
			return $content;
		}

		// Find our wrapper div (the first element node).
		$root = null;
		foreach ( $dom->childNodes as $node ) {
			if ( XML_ELEMENT_NODE === $node->nodeType ) {
				$root = $node;
				break;
			}
		}
		if ( ! $root ) {
			return $content;
		}

		$this->walk( $root, $state, $dom );

		// Serialise the wrapper's children back to an HTML string.
		$html = '';
		foreach ( $root->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}

		return $html;
	}

	/**
	 * Walk the DOM, linking eligible text nodes and skipping excluded subtrees.
	 *
	 * @param DOMNode     $node  The node to walk.
	 * @param array       $state Running state (by reference).
	 * @param DOMDocument $dom   The document.
	 * @return void
	 */
	private function walk( $node, &$state, $dom ) {
		// A snapshot, because we replace text nodes as we go.
		$children = iterator_to_array( $node->childNodes );

		foreach ( $children as $child ) {
			if ( $state['links'] >= $state['cap'] ) {
				return; // Cap reached, nothing more to do.
			}

			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$tag = strtolower( $child->nodeName );

				// Skip whole subtrees for headings, links, code and the like.
				if ( in_array( $tag, self::SKIP_TAGS, true ) ) {
					continue;
				}

				// Skip the glossary's own output, wherever it is embedded.
				if ( $this->is_glossary_element( $child ) ) {
					continue;
				}

				$this->walk( $child, $state, $dom );
			} elseif ( XML_TEXT_NODE === $child->nodeType ) {
				$this->process_text_node( $child, $state, $dom );
			}
		}
	}

	/**
	 * Whether an element is part of the glossary widget/shortcode output, which
	 * must never be linkified.
	 *
	 * @param DOMElement $el The element.
	 * @return bool
	 */
	private function is_glossary_element( $el ) {
		if ( $el->hasAttribute( 'data-apglos' ) ) {
			return true;
		}
		$class = $el->getAttribute( 'class' );
		return ( '' !== $class && false !== strpos( $class, 'apglos' ) );
	}

	/**
	 * Link matches inside a single text node.
	 *
	 * @param DOMText     $text_node The text node.
	 * @param array       $state     Running state (by reference).
	 * @param DOMDocument $dom       The document.
	 * @return void
	 */
	private function process_text_node( $text_node, &$state, $dom ) {
		$text = $text_node->nodeValue;
		if ( '' === trim( $text ) ) {
			return;
		}

		// Find every candidate match, longest first (the regex is ordered so).
		if ( ! preg_match_all( $state['dict']['regex'], $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			return;
		}

		// Ranges covered by any leftover shortcode brackets, to be skipped.
		$brackets = $this->bracket_ranges( $text );

		$result  = '';
		$cursor  = 0;
		$changed = false;

		foreach ( $matches[1] as $match ) {
			$matched = $match[0];
			$offset  = $match[1];

			if ( $offset < $cursor ) {
				continue; // Overlaps something we already handled.
			}
			if ( $state['links'] >= $state['cap'] ) {
				break;
			}

			$key = $this->normalise( $matched );
			if ( ! isset( $state['dict']['map'][ $key ] ) ) {
				continue;
			}
			$term = $state['dict']['map'][ $key ];

			// First occurrence only, per term, per post.
			if ( isset( $state['terms'][ $term['slug'] ] ) ) {
				continue;
			}

			// Skip anything sitting inside a shortcode bracket.
			if ( $this->in_ranges( $offset, strlen( $matched ), $brackets ) ) {
				continue;
			}

			// Text before the match, escaped, then the link.
			$result .= esc_html( substr( $text, $cursor, $offset - $cursor ) );
			$result .= $this->build_link( $term, $matched );

			$cursor                          = $offset + strlen( $matched );
			$state['terms'][ $term['slug'] ] = true;
			++$state['links'];
			$changed                         = true;
		}

		if ( ! $changed ) {
			return;
		}

		// Trailing text, escaped.
		$result .= esc_html( substr( $text, $cursor ) );

		$this->replace_text_node( $text_node, $result, $dom );
	}

	/**
	 * Replace a text node with parsed HTML nodes.
	 *
	 * @param DOMText     $text_node The node to replace.
	 * @param string      $html      The replacement HTML.
	 * @param DOMDocument $dom       The document.
	 * @return void
	 */
	private function replace_text_node( $text_node, $html, $dom ) {
		$tmp = new DOMDocument( '1.0', 'UTF-8' );
		libxml_use_internal_errors( true );
		$tmp->loadHTML(
			'<?xml encoding="utf-8"?><div id="apglos-frag">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$frag_root = null;
		foreach ( $tmp->childNodes as $node ) {
			if ( XML_ELEMENT_NODE === $node->nodeType ) {
				$frag_root = $node;
				break;
			}
		}
		if ( ! $frag_root ) {
			return;
		}

		$fragment = $dom->createDocumentFragment();
		foreach ( iterator_to_array( $frag_root->childNodes ) as $child ) {
			$fragment->appendChild( $dom->importNode( $child, true ) );
		}

		$text_node->parentNode->replaceChild( $fragment, $text_node );
	}

	/**
	 * Build the anchor HTML for a matched term.
	 *
	 * @param array  $term    The term data (url, title, slug).
	 * @param string $matched The exact matched text (original casing kept).
	 * @return string
	 */
	private function build_link( $term, $matched ) {
		$settings = apglos_get_settings();
		$new_tab  = ! empty( $settings['linkify_new_tab'] );
		$note     = $new_tab && ! empty( $settings['linkify_new_tab_note'] );
		$icon     = ! empty( $settings['linkify_external_icon'] );

		$attrs = 'href="' . esc_url( $term['url'] ) . '" class="apglos-glossary-link"';
		if ( $new_tab ) {
			// target _blank with rel noopener, so the reader keeps her place.
			$attrs .= ' target="_blank" rel="noopener"';
		}

		$inner = esc_html( $matched );

		if ( $note ) {
			// A visually hidden note inside the link, for screen readers.
			$inner .= '<span class="apglos-link-note"> (' . esc_html__( 'opens in a new tab', 'apotheca-glossary' ) . ')</span>';
		}

		if ( $icon ) {
			$inner .= $this->icon_svg();
		}

		return '<a ' . $attrs . '>' . $inner . '</a>';
	}

	/**
	 * A small inline external-link icon (optional, off by default).
	 *
	 * @return string
	 */
	private function icon_svg() {
		return '<svg class="apglos-ext-icon" aria-hidden="true" focusable="false" width="0.85em" height="0.85em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
	}

	/*
	 * -------------------------------------------------------------------------
	 * Dictionary
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Build (and cache) the phrase dictionary and the matching regex.
	 *
	 * Every term contributes its title and its synonyms. Phrases are sorted
	 * longest first so the regex matches the longest phrase at any position
	 * ("hyaluronic acid" beats "acid").
	 *
	 * @return array { map: phrase => term, regex: string, version: string }
	 */
	private function get_dictionary() {
		static $runtime = null;
		if ( null !== $runtime ) {
			return $runtime;
		}

		// The cache signature includes the plugin version, so a plugin update
		// refreshes the link dictionary automatically (a files-only update does
		// not run activation, which is where the manual cache-clear lives).
		$version = get_option( 'apglos_terms_version', '0' ) . '|' . APGLOS_VERSION;
		$stored  = get_transient( 'apglos_linkify_dict' );
		if ( is_array( $stored ) && isset( $stored['version'] ) && $stored['version'] === $version ) {
			$runtime = $stored;
			return $runtime;
		}

		$posts = get_posts(
			array(
				'post_type'      => APGLOS_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		$map     = array();
		$phrases = array();

		// When a glossary page URL is set, links open that page and the widget
		// scrolls to the term (by slug, via ?apglos_term=slug), leaving the whole
		// glossary on screen to browse, with the header offset respected. This is
		// robust: the widget finds the term by slug rather than relying on a
		// fixed HTML anchor being present. Otherwise links go to the term's own
		// page.
		$page_url = trim( (string) apglos_get_setting( 'glossary_page_url' ) );

		foreach ( $posts as $post ) {
			if ( '' !== $page_url ) {
				$url = add_query_arg( 'apglos_term', $post->post_name, $page_url );
			} else {
				$url = get_permalink( $post->ID );
			}

			$term = array(
				'url'   => $url,
				'title' => $post->post_title,
				'slug'  => $post->post_name,
			);

			// The term itself plus each synonym.
			$names = array( $post->post_title );
			$aka   = (string) get_post_meta( $post->ID, 'also_known_as', true );
			foreach ( array_filter( array_map( 'trim', explode( '|', $aka ) ), 'strlen' ) as $syn ) {
				$names[] = $syn;
			}

			foreach ( $names as $name ) {
				$key = $this->normalise( $name );
				if ( '' === $key || isset( $map[ $key ] ) ) {
					continue; // First phrase to claim a spelling keeps it.
				}
				$map[ $key ] = $term;
				$phrases[]   = $name;
			}
		}

		// Longest phrase first, so the regex prefers the longest match.
		usort(
			$phrases,
			function ( $a, $b ) {
				return $this->str_len( $b ) - $this->str_len( $a );
			}
		);

		$quoted = array_map(
			function ( $phrase ) {
				return preg_quote( $phrase, '/' );
			},
			$phrases
		);

		// Unicode-aware boundaries so we match whole words, not fragments.
		$regex = empty( $quoted )
			? ''
			: '/(?<![\p{L}\p{N}])(' . implode( '|', $quoted ) . ')(?![\p{L}\p{N}])/iu';

		$runtime = array(
			'version' => $version,
			'map'     => $map,
			'regex'   => $regex,
		);

		set_transient( 'apglos_linkify_dict', $runtime, WEEK_IN_SECONDS );

		return $runtime;
	}

	/*
	 * -------------------------------------------------------------------------
	 * Cache invalidation
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Bump the terms version, invalidating the dictionary and every cached
	 * linkified post. Runs when a glossary term is saved.
	 *
	 * @return void
	 */
	public function bump_version() {
		update_option( 'apglos_terms_version', (string) microtime( true ) );
		delete_transient( 'apglos_linkify_dict' );
	}

	/**
	 * Bump the version when a glossary term is deleted.
	 *
	 * @param int $post_id The post being deleted.
	 * @return void
	 */
	public function maybe_bump_on_delete( $post_id ) {
		if ( APGLOS_POST_TYPE === get_post_type( $post_id ) ) {
			$this->bump_version();
		}
	}

	/**
	 * Clear one post's cached linkified content when it is saved.
	 *
	 * @param int $post_id The saved post.
	 * @return void
	 */
	public function clear_post_cache( $post_id ) {
		delete_transient( 'apglos_linkify_' . $post_id );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Per-post meta box
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Add the per-post linking controls to eligible post types.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		foreach ( $this->eligible_post_types() as $post_type ) {
			add_meta_box(
				'apglos_linkify',
				__( 'Glossary linking', 'apotheca-glossary' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Post types that can carry glossary links: public types, minus the
	 * glossary itself and attachments.
	 *
	 * @return string[]
	 */
	private function eligible_post_types() {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types[ APGLOS_POST_TYPE ], $types['attachment'] );
		return array_values( $types );
	}

	/**
	 * Render the per-post meta box.
	 *
	 * @param WP_Post $post The post being edited.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, 'apglos_linkify_nonce' );

		$optout = get_post_meta( $post->ID, self::META_OPTOUT, true );
		$cap    = get_post_meta( $post->ID, self::META_CAP, true );
		$global = (int) apglos_get_setting( 'linkify_cap' );
		?>
		<p>
			<label>
				<input type="checkbox" name="apglos_no_linkify" value="1" <?php checked( $optout, '1' ); ?> />
				<?php esc_html_e( 'Do not add glossary links to this post', 'apotheca-glossary' ); ?>
			</label>
		</p>
		<p>
			<label for="apglos_linkify_cap"><?php esc_html_e( 'Maximum links (override)', 'apotheca-glossary' ); ?></label><br />
			<input type="number" min="0" step="1" id="apglos_linkify_cap" name="apglos_linkify_cap" value="<?php echo esc_attr( $cap ); ?>" placeholder="<?php echo esc_attr( $global ); ?>" style="width:80px;" />
			<span class="description">
				<?php
				printf(
					/* translators: %d: the global cap */
					esc_html__( 'Leave blank to use the site default (%d).', 'apotheca-glossary' ),
					(int) $global
				);
				?>
			</span>
		</p>
		<?php
	}

	/**
	 * Save the per-post meta box.
	 *
	 * @param int     $post_id The post id.
	 * @param WP_Post $post    The post.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['apglos_linkify_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['apglos_linkify_nonce'] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Opt out.
		if ( isset( $_POST['apglos_no_linkify'] ) && '1' === $_POST['apglos_no_linkify'] ) {
			update_post_meta( $post_id, self::META_OPTOUT, '1' );
		} else {
			delete_post_meta( $post_id, self::META_OPTOUT );
		}

		// Cap override: a blank value means "use the default".
		$cap_raw = isset( $_POST['apglos_linkify_cap'] ) ? trim( wp_unslash( $_POST['apglos_linkify_cap'] ) ) : '';
		if ( '' === $cap_raw ) {
			delete_post_meta( $post_id, self::META_CAP );
		} else {
			update_post_meta( $post_id, self::META_CAP, (string) absint( $cap_raw ) );
		}
	}

	/*
	 * -------------------------------------------------------------------------
	 * Front-end helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Print the couple of inline CSS rules the links need, only when linking is
	 * on. Keeps the hidden note truly hidden and aligns the optional icon.
	 *
	 * @return void
	 */
	public function print_inline_style() {
		if ( is_admin() || empty( apglos_get_setting( 'linkify_enabled' ) ) ) {
			return;
		}

		// The always-needed rules: hide the screen-reader note, align the icon.
		$css = '.apglos-link-note{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}';
		$css .= '.apglos-ext-icon{display:inline-block;vertical-align:baseline;margin-left:.2em}';

		// The glossary link appearance, from the settings. So the links are
		// visible by default (underlined) and can be coloured to taste.
		$settings   = apglos_get_settings();
		$underline  = ! empty( $settings['linkify_link_underline'] );
		$color      = isset( $settings['linkify_link_color'] ) ? trim( (string) $settings['linkify_link_color'] ) : '';
		$hover      = isset( $settings['linkify_link_hover_color'] ) ? trim( (string) $settings['linkify_link_hover_color'] ) : '';

		$link_css = 'text-decoration:' . ( $underline ? 'underline' : 'none' ) . ';';
		if ( '' !== $color ) {
			$link_css .= 'color:' . $color . ';';
		}
		$css .= '.apglos-glossary-link{' . $link_css . '}';

		if ( '' !== $hover ) {
			$css .= '.apglos-glossary-link:hover,.apglos-glossary-link:focus{color:' . $hover . ';}';
		}

		echo '<style id="apglos-linkify-inline">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- colours are hex-sanitised on save; the rest is static.
	}

	/*
	 * -------------------------------------------------------------------------
	 * Small utilities
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Normalise a phrase for matching (trimmed, lowercased).
	 *
	 * @param string $value The value.
	 * @return string
	 */
	private function normalise( $value ) {
		$value = trim( (string) $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * Character length, multibyte-aware where possible.
	 *
	 * @param string $value The value.
	 * @return int
	 */
	private function str_len( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/**
	 * Byte ranges covered by shortcode-style brackets in a string.
	 *
	 * @param string $text The text.
	 * @return array[] Array of array(start, end) byte offsets.
	 */
	private function bracket_ranges( $text ) {
		$ranges = array();
		if ( preg_match_all( '/\[[^\]]*\]/', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $bracket ) {
				$start    = $bracket[1];
				$end      = $start + strlen( $bracket[0] );
				$ranges[] = array( $start, $end );
			}
		}
		return $ranges;
	}

	/**
	 * Whether a byte span overlaps any of the given ranges.
	 *
	 * @param int   $offset Start offset.
	 * @param int   $length Length.
	 * @param array $ranges The ranges.
	 * @return bool
	 */
	private function in_ranges( $offset, $length, $ranges ) {
		$end = $offset + $length;
		foreach ( $ranges as $range ) {
			if ( $offset < $range[1] && $end > $range[0] ) {
				return true;
			}
		}
		return false;
	}
}
