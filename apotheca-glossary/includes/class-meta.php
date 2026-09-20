<?php
/**
 * Custom fields for glossary terms.
 *
 * Three pieces of data live alongside each term:
 *   - also_known_as  Pipe-delimited synonyms, edited in the meta box.
 *   - related_terms  Pipe-delimited term names, edited in the meta box.
 *   - first_char     A cached first character, computed automatically on save
 *                    and used by the A to Z bar and the Letter admin column.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the meta fields, renders the meta box, and saves its values.
 */
class Apglos_Meta {

	/**
	 * Nonce action, used to verify the meta box save came from our form.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'apglos_save_meta';

	/**
	 * Nonce field name.
	 *
	 * @var string
	 */
	const NONCE_NAME = 'apglos_meta_nonce';

	/**
	 * Hook everything into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		// Register the meta so it is known to WordPress and the REST API, with
		// its own sanitisation callback per field.
		add_action( 'init', array( $this, 'register_meta' ) );

		// Add the editable meta box for synonyms and related terms.
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );

		// Save the meta box values, and (re)compute the cached first character,
		// whenever a term is saved.
		add_action( 'save_post_' . APGLOS_POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Registration
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register the three meta fields.
	 *
	 * Registering meta (rather than just reading and writing it ad hoc) gives
	 * each field a sanitisation callback WordPress applies automatically, and
	 * exposes them to the REST API for the block editor.
	 *
	 * @return void
	 */
	public function register_meta() {
		// Synonyms, stored pipe-delimited, e.g. "vitamin C|ascorbic acid".
		register_post_meta(
			APGLOS_POST_TYPE,
			'also_known_as',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_pipe_list' ),
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
			)
		);

		// Related term names, stored pipe-delimited. Resolved to links on the
		// front end in a later stage, stored here as plain names.
		register_post_meta(
			APGLOS_POST_TYPE,
			'related_terms',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_pipe_list' ),
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
			)
		);

		// The cached first character. Written automatically on save, so it is
		// not user-editable, but it is registered so it is a known, sanitised
		// field rather than stray post meta.
		register_post_meta(
			APGLOS_POST_TYPE,
			'first_char',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => array( __CLASS__, 'can_edit' ),
			)
		);
	}

	/**
	 * Permission check for editing our meta via the REST API.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Sanitisation and helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Sanitise a pipe-delimited list.
	 *
	 * Splits on the pipe, trims and cleans each entry, drops empties and
	 * duplicates, then rejoins with a single pipe. This keeps the stored value
	 * tidy however the editor typed it in (stray spaces, trailing pipes, and so
	 * on), which matters because search and linkify read this field directly.
	 *
	 * @param string $value The raw value from the form.
	 * @return string Cleaned, pipe-delimited value.
	 */
	public static function sanitize_pipe_list( $value ) {
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return '';
		}

		$parts   = explode( '|', $value );
		$cleaned = array();

		foreach ( $parts as $part ) {
			$part = sanitize_text_field( $part );
			$part = trim( $part );

			// Skip empties and anything we have already kept (case-insensitive),
			// so the list holds each synonym or related term just once.
			if ( '' === $part ) {
				continue;
			}

			$lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $part ) : strtolower( $part );
			if ( isset( $cleaned[ $lower ] ) ) {
				continue;
			}

			$cleaned[ $lower ] = $part;
		}

		return implode( '|', array_values( $cleaned ) );
	}

	/**
	 * Work out the cached first character for a term.
	 *
	 * Takes the first character of the term, upper-cases it, and normalises any
	 * digit to the shared "0-9" bucket so all numeric terms group together on
	 * the A to Z bar. Non-alphanumeric leading characters are returned upper
	 * cased as-is, the CSV is not expected to contain any.
	 *
	 * @param string $title The term (post title).
	 * @return string A single uppercase letter, or "0-9" for a digit.
	 */
	public static function compute_first_char( $title ) {
		$title = trim( (string) $title );

		if ( '' === $title ) {
			return '';
		}

		// Grab the first character, multibyte-safe where the extension exists.
		if ( function_exists( 'mb_substr' ) ) {
			$first = mb_substr( $title, 0, 1 );
			$first = mb_strtoupper( $first );
		} else {
			$first = strtoupper( substr( $title, 0, 1 ) );
		}

		// Any digit collapses into the single 0-9 bucket.
		if ( ctype_digit( $first ) ) {
			return '0-9';
		}

		return $first;
	}

	/*
	 * -------------------------------------------------------------------------
	 * Meta box
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register the meta box on the term edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'apglos_term_details',
			__( 'Term Details', 'apotheca-glossary' ),
			array( $this, 'render_meta_box' ),
			APGLOS_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the meta box: two fields, synonyms and related terms.
	 *
	 * @param WP_Post $post The term being edited.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		// A nonce so the save handler can confirm the request came from here.
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$also_known_as = get_post_meta( $post->ID, 'also_known_as', true );
		$related_terms = get_post_meta( $post->ID, 'related_terms', true );
		?>
		<p>
			<label for="apglos_also_known_as">
				<strong><?php esc_html_e( 'Also known as', 'apotheca-glossary' ); ?></strong>
			</label>
			<br />
			<input
				type="text"
				id="apglos_also_known_as"
				name="apglos_also_known_as"
				class="widefat"
				value="<?php echo esc_attr( $also_known_as ); ?>"
			/>
			<span class="description">
				<?php esc_html_e( 'Synonyms for this term, separated by a pipe. Example: vitamin C|ascorbic acid', 'apotheca-glossary' ); ?>
			</span>
		</p>

		<p>
			<label for="apglos_related_terms">
				<strong><?php esc_html_e( 'Related terms', 'apotheca-glossary' ); ?></strong>
			</label>
			<br />
			<input
				type="text"
				id="apglos_related_terms"
				name="apglos_related_terms"
				class="widefat"
				value="<?php echo esc_attr( $related_terms ); ?>"
			/>
			<span class="description">
				<?php esc_html_e( 'Other glossary terms to link to, separated by a pipe. Each must match another term exactly. Example: occlusive|humectant', 'apotheca-glossary' ); ?>
			</span>
		</p>
		<?php
	}

	/*
	 * -------------------------------------------------------------------------
	 * Save
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Save the meta box values and refresh the cached first character.
	 *
	 * @param int     $post_id The term being saved.
	 * @param WP_Post $post    The term object.
	 * @return void
	 */
	public function save_meta( $post_id, $post ) {
		// Don't run on autosaves or revisions, there is no form data then.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// The first character is derived from the title, so keep it in sync on
		// every save regardless of whether the meta box was submitted (for
		// example a quick edit of the title, or a programmatic import).
		$this->update_first_char( $post_id, $post );

		// From here on we are handling the meta box form, which only posts on a
		// full edit-screen save. If our nonce isn't present, there is nothing
		// more to do (this is normal for quick edits and imports).
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		// Verify the nonce and the current user's capability.
		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save "also known as". Sanitisation is handled by the registered
		// sanitize_callback, but we clean here too so the stored value is tidy
		// even on code paths that bypass register_post_meta.
		$also_known_as = isset( $_POST['apglos_also_known_as'] ) ? wp_unslash( $_POST['apglos_also_known_as'] ) : '';
		$also_known_as = self::sanitize_pipe_list( $also_known_as );

		if ( '' === $also_known_as ) {
			delete_post_meta( $post_id, 'also_known_as' );
		} else {
			update_post_meta( $post_id, 'also_known_as', $also_known_as );
		}

		// Save "related terms".
		$related_terms = isset( $_POST['apglos_related_terms'] ) ? wp_unslash( $_POST['apglos_related_terms'] ) : '';
		$related_terms = self::sanitize_pipe_list( $related_terms );

		if ( '' === $related_terms ) {
			delete_post_meta( $post_id, 'related_terms' );
		} else {
			update_post_meta( $post_id, 'related_terms', $related_terms );
		}
	}

	/**
	 * Compute and store the cached first character for a term.
	 *
	 * @param int     $post_id The term id.
	 * @param WP_Post $post    The term object.
	 * @return void
	 */
	public function update_first_char( $post_id, $post ) {
		$first_char = self::compute_first_char( $post->post_title );

		if ( '' === $first_char ) {
			delete_post_meta( $post_id, 'first_char' );
		} else {
			update_post_meta( $post_id, 'first_char', $first_char );
		}
	}
}
