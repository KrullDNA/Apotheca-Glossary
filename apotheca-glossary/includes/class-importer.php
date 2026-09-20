<?php
/**
 * CSV importer and exporter for the glossary.
 *
 * Provides an Import / Export admin page under the Glossary menu. It parses an
 * uploaded CSV, strips a byte order mark if Excel added one, previews exactly
 * what will be created, updated, skipped or deleted, validates that every
 * related term resolves to a real term, and only writes to the database once
 * the admin confirms. It also exports the current glossary in the same format.
 *
 * The CSV format (all fields quoted, comma separated, UTF-8, no BOM):
 *   term, slug, category, also_known_as, related_terms, definition
 * The also_known_as and related_terms fields are pipe delimited internally.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the importer admin page, the three import modes, and the export.
 */
class Apglos_Importer {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'apglos-import';

	/**
	 * Nonce action for the upload / preview step.
	 *
	 * @var string
	 */
	const NONCE_PREVIEW = 'apglos_import_preview';

	/**
	 * Nonce action for the commit step.
	 *
	 * @var string
	 */
	const NONCE_COMMIT = 'apglos_import_commit';

	/**
	 * Nonce action for the export download.
	 *
	 * @var string
	 */
	const NONCE_EXPORT = 'apglos_export';

	/**
	 * How long the previewed data is held between preview and commit, in
	 * seconds. Long enough to read the preview, short enough to tidy itself up.
	 *
	 * @var int
	 */
	const TRANSIENT_TTL = HOUR_IN_SECONDS;

	/**
	 * The CSV columns we recognise. Order here is the order used on export.
	 *
	 * @var string[]
	 */
	const COLUMNS = array( 'term', 'slug', 'category', 'also_known_as', 'related_terms', 'definition' );

	/**
	 * The columns a row must contain a value for to be importable.
	 *
	 * @var string[]
	 */
	const REQUIRED_COLUMNS = array( 'term', 'slug', 'category', 'definition' );

	/**
	 * The three import modes.
	 *
	 * @var string[]
	 */
	const MODES = array( 'create', 'create_update', 'replace_all' );

	/**
	 * Hook everything into WordPress.
	 *
	 * @return void
	 */
	public function init() {
		// Add the Import / Export page under the Glossary menu.
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );

		// Handle the export download early, before any HTML is sent, so we can
		// stream a file with the right headers.
		add_action( 'admin_post_apglos_glossary_export', array( $this, 'handle_export' ) );

		// Load the admin stylesheet only on our page.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the Import / Export submenu page.
	 *
	 * @return void
	 */
	public function add_admin_page() {
		add_submenu_page(
			'edit.php?post_type=' . APGLOS_POST_TYPE,
			__( 'Import / Export Glossary', 'apotheca-glossary' ),
			__( 'Import / Export', 'apotheca-glossary' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue the admin stylesheet, only on the importer page.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		// The hook for a CPT submenu page ends with our page slug.
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'apglos-admin',
			APGLOS_URL . 'admin/admin-style.css',
			array(),
			APGLOS_VERSION
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Page controller
	 * -------------------------------------------------------------------------
	 * Works out which step we are on (upload form, preview, replace-all
	 * confirmation, or result), does that step's work, then hands the data to
	 * the view template for rendering.
	 */

	/**
	 * Render the Import / Export page.
	 *
	 * @return void
	 */
	public function render_page() {
		// Guard the whole page.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'apotheca-glossary' ) );
		}

		// Values the view template reads. Defaults show the plain upload form.
		$step    = 'form';
		$mode    = 'create_update';
		$token   = '';
		$preview = null;
		$result  = null;
		$notices = array();

		// Which action was submitted, if any.
		$action = isset( $_POST['apglos_action'] ) ? sanitize_key( wp_unslash( $_POST['apglos_action'] ) ) : '';

		if ( 'preview' === $action ) {
			// Step 1: an upload was submitted. Parse and analyse it.
			$outcome = $this->process_preview();

			if ( is_wp_error( $outcome ) ) {
				$notices[] = array( 'type' => 'error', 'text' => $outcome->get_error_message() );
			} else {
				$preview = $outcome;
				$mode    = $outcome['mode'];
				$token   = $outcome['token'];
				$step    = 'preview';
			}
		} elseif ( 'commit' === $action ) {
			// Step 2 (or 3 for replace all): commit the previewed data.
			$commit = $this->process_commit();

			if ( is_wp_error( $commit ) ) {
				// A special error code means: replace all needs a second,
				// explicit confirmation before we delete anything.
				if ( 'needs_confirmation' === $commit->get_error_code() ) {
					$data    = $commit->get_error_data();
					$preview = $data['preview'];
					$mode    = $data['preview']['mode'];
					$token   = $data['preview']['token'];
					$step    = 'confirm_replace';
				} else {
					$notices[] = array( 'type' => 'error', 'text' => $commit->get_error_message() );
				}
			} else {
				$result = $commit;
				$step   = 'result';
			}
		}

		// Hand off to the view. These locals are in scope inside the template.
		include APGLOS_PATH . 'admin/import-page.php';
	}

	/*
	 * -------------------------------------------------------------------------
	 * Preview
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Handle the upload and produce a preview.
	 *
	 * Verifies the nonce, reads the uploaded file, parses it, analyses each row
	 * against the chosen mode and the existing glossary, stashes the parsed
	 * rows in a transient so the commit step does not need a re-upload, and
	 * returns everything the preview view needs.
	 *
	 * @return array|WP_Error Preview data, or an error to show the admin.
	 */
	private function process_preview() {
		// Verify the request came from our form.
		if ( ! isset( $_POST['apglos_preview_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['apglos_preview_nonce'] ) ), self::NONCE_PREVIEW ) ) {
			return new WP_Error( 'bad_nonce', __( 'Security check failed. Please try again.', 'apotheca-glossary' ) );
		}

		// Validate the chosen mode.
		$mode = isset( $_POST['apglos_mode'] ) ? sanitize_key( wp_unslash( $_POST['apglos_mode'] ) ) : '';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error( 'bad_mode', __( 'Please choose an import mode.', 'apotheca-glossary' ) );
		}

		// Check a file actually arrived and uploaded cleanly.
		if ( empty( $_FILES['apglos_csv'] ) || ! isset( $_FILES['apglos_csv']['error'] ) || UPLOAD_ERR_OK !== $_FILES['apglos_csv']['error'] ) {
			return new WP_Error( 'no_file', __( 'Please choose a CSV file to upload.', 'apotheca-glossary' ) );
		}

		// Confirm it is a real uploaded file, not a path someone slipped in.
		$tmp_path = $_FILES['apglos_csv']['tmp_name']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_uploaded_file( $tmp_path ) ) {
			return new WP_Error( 'bad_file', __( 'The upload could not be verified. Please try again.', 'apotheca-glossary' ) );
		}

		// Light extension check. The real safety is that we only ever read the
		// file as CSV text, never execute it.
		$filename = isset( $_FILES['apglos_csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['apglos_csv']['name'] ) ) : '';
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext && 'txt' !== $ext ) {
			return new WP_Error( 'bad_ext', __( 'Please upload a .csv file.', 'apotheca-glossary' ) );
		}

		// Read and parse the file.
		$content = file_get_contents( $tmp_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $content ) {
			return new WP_Error( 'read_failed', __( 'The file could not be read.', 'apotheca-glossary' ) );
		}

		$parsed = $this->parse_csv( $content );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// Analyse the rows against the mode and the current glossary.
		$analysis = $this->analyse( $parsed, $mode );

		// Stash the importable rows so commit needs no re-upload. A random
		// token ties this stash to the confirmation form.
		$token = wp_generate_password( 24, false );
		set_transient(
			'apglos_import_' . $token,
			array(
				'mode' => $mode,
				'rows' => $analysis['rows'],
			),
			self::TRANSIENT_TTL
		);

		return array(
			'mode'               => $mode,
			'token'              => $token,
			'filename'           => $filename,
			'rows'               => $analysis['rows'],
			'counts'             => $analysis['counts'],
			'errors'             => $analysis['errors'],
			'unresolved_related' => $analysis['unresolved_related'],
			'delete_slugs'       => $analysis['delete_slugs'],
		);
	}

	/*
	 * -------------------------------------------------------------------------
	 * Commit
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Handle the confirmed import.
	 *
	 * @return array|WP_Error Result counts, or an error. A "needs_confirmation"
	 *                        error carries the preview back so replace-all can
	 *                        show its second, stern confirmation screen.
	 */
	private function process_commit() {
		// Verify the nonce.
		if ( ! isset( $_POST['apglos_commit_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['apglos_commit_nonce'] ) ), self::NONCE_COMMIT ) ) {
			return new WP_Error( 'bad_nonce', __( 'Security check failed. Please try again.', 'apotheca-glossary' ) );
		}

		// Recover the previewed rows from the transient.
		$token = isset( $_POST['apglos_token'] ) ? sanitize_text_field( wp_unslash( $_POST['apglos_token'] ) ) : '';
		$stash = $token ? get_transient( 'apglos_import_' . $token ) : false;

		if ( empty( $stash ) || empty( $stash['rows'] ) ) {
			return new WP_Error( 'expired', __( 'The preview has expired. Please upload the file again.', 'apotheca-glossary' ) );
		}

		$mode = $stash['mode'];
		$rows = $stash['rows'];

		// Replace all deletes existing terms, so it needs a second confirmation.
		// If that confirmation has not been given yet, bounce back to the
		// warning screen rather than committing.
		if ( 'replace_all' === $mode ) {
			$confirmed = isset( $_POST['apglos_confirm_replace'] ) && '1' === $_POST['apglos_confirm_replace'];

			if ( ! $confirmed ) {
				// Rebuild the preview so the warning screen can restate the
				// counts, then signal that confirmation is still needed.
				$analysis = $this->analyse( array( 'rows' => $rows ), $mode );
				$preview  = array_merge(
					$analysis,
					array( 'mode' => $mode, 'token' => $token )
				);
				return new WP_Error(
					'needs_confirmation',
					__( 'Replace all needs confirmation.', 'apotheca-glossary' ),
					array( 'preview' => $preview )
				);
			}
		}

		// Do the write.
		$result = $this->run_import( $rows, $mode );

		// The stash has served its purpose.
		delete_transient( 'apglos_import_' . $token );

		return $result;
	}

	/**
	 * Write the previewed rows to the database.
	 *
	 * @param array  $rows Analysed rows (each carrying its computed action).
	 * @param string $mode The import mode.
	 * @return array Result counts.
	 */
	private function run_import( $rows, $mode ) {
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$deleted = 0;

		// Track which slugs are present in the file, for replace-all cleanup.
		$csv_slugs = array();

		foreach ( $rows as $row ) {
			$csv_slugs[ $row['slug'] ] = true;

			// Rows flagged skip or invalid are left untouched.
			if ( 'skip' === $row['action'] || 'invalid' === $row['action'] ) {
				++$skipped;
				continue;
			}

			$post_id = $this->write_term( $row );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				++$skipped;
				continue;
			}

			if ( 'update' === $row['action'] ) {
				++$updated;
			} else {
				++$created;
			}
		}

		// Replace all: remove any existing term whose slug is not in the file.
		if ( 'replace_all' === $mode ) {
			foreach ( $this->get_existing_slug_map() as $slug => $existing_id ) {
				if ( ! isset( $csv_slugs[ $slug ] ) ) {
					wp_delete_post( $existing_id, true );
					++$deleted;
				}
			}
		}

		return array(
			'mode'    => $mode,
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'deleted' => $deleted,
		);
	}

	/**
	 * Create or update a single glossary term from a row.
	 *
	 * @param array $row An analysed row.
	 * @return int|WP_Error The term (post) id, or an error.
	 */
	private function write_term( $row ) {
		// Core expects slashed data, it unslashes internally. The definition is
		// run through wp_kses_post so imported content is safe on a public page
		// while keeping straight apostrophes for WordPress to curl on output.
		$postarr = array(
			'post_type'    => APGLOS_POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => wp_slash( $row['term'] ),
			'post_name'    => $row['slug'],
			'post_content' => wp_slash( wp_kses_post( $row['definition'] ) ),
		);

		if ( 'update' === $row['action'] ) {
			$existing = $this->get_existing_slug_map();
			if ( isset( $existing[ $row['slug'] ] ) ) {
				$postarr['ID'] = $existing[ $row['slug'] ];
			}
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return $post_id;
		}

		// Assign the category, creating the taxonomy term if it is new.
		$this->assign_category( $post_id, $row['category'] );

		// Store the two pipe-delimited meta fields, cleaned the same way the
		// meta box cleans them. first_char is set automatically by the save
		// hook from Stage 1. Empty values are removed rather than stored blank.
		foreach ( array( 'also_known_as', 'related_terms' ) as $meta_key ) {
			$value = Apglos_Meta::sanitize_pipe_list( $row[ $meta_key ] );
			if ( '' === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		return $post_id;
	}

	/**
	 * Assign a category to a term, creating the category if it does not exist.
	 *
	 * @param int    $post_id  The term id.
	 * @param string $category The category name.
	 * @return void
	 */
	private function assign_category( $post_id, $category ) {
		$category = trim( $category );
		if ( '' === $category ) {
			return;
		}

		$existing = term_exists( $category, APGLOS_TAXONOMY );

		if ( ! $existing ) {
			$existing = wp_insert_term( $category, APGLOS_TAXONOMY );
		}

		if ( is_wp_error( $existing ) ) {
			return;
		}

		$term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;

		// false = replace, so each term carries exactly one category.
		wp_set_object_terms( $post_id, $term_id, APGLOS_TAXONOMY, false );
	}

	/*
	 * -------------------------------------------------------------------------
	 * CSV parsing
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Parse CSV text into a header map and rows.
	 *
	 * Strips a UTF-8 byte order mark (Excel adds one on save, and it corrupts
	 * the first column name), reads the header to locate each column by name so
	 * column order does not matter, and returns each data row keyed by column.
	 *
	 * @param string $content Raw file contents.
	 * @return array|WP_Error Parsed data, or an error.
	 */
	private function parse_csv( $content ) {
		// Strip a UTF-8 BOM from the very start if present.
		$bom = "\xEF\xBB\xBF";
		if ( 0 === strncmp( $content, $bom, 3 ) ) {
			$content = substr( $content, 3 );
		}

		// Read the text through a memory stream so fgetcsv handles quoted
		// fields, including any that contain commas or line breaks.
		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $content );
		rewind( $handle );

		$header = fgetcsv( $handle );
		if ( false === $header || null === $header ) {
			fclose( $handle );
			return new WP_Error( 'empty_file', __( 'The file appears to be empty.', 'apotheca-glossary' ) );
		}

		// Belt and braces: strip a BOM from the first header cell too.
		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
		}

		// Map each recognised column name to its position in the file.
		$index = array();
		foreach ( $header as $pos => $name ) {
			$key = strtolower( trim( (string) $name ) );
			if ( in_array( $key, self::COLUMNS, true ) ) {
				$index[ $key ] = $pos;
			}
		}

		// Every required column must be present in the header.
		$missing = array_diff( self::REQUIRED_COLUMNS, array_keys( $index ) );
		if ( ! empty( $missing ) ) {
			fclose( $handle );
			return new WP_Error(
				'missing_columns',
				sprintf(
					/* translators: %s: comma separated list of column names */
					__( 'The file is missing required column(s): %s. The header must include term, slug, category and definition.', 'apotheca-glossary' ),
					implode( ', ', $missing )
				)
			);
		}

		// Read the data rows.
		$rows      = array();
		$line_no   = 1; // Header was line 1.
		while ( true ) {
			$data = fgetcsv( $handle );
			if ( false === $data || null === $data ) {
				break;
			}
			++$line_no;

			// Skip a wholly empty line (fgetcsv returns array(null) for these).
			if ( array( null ) === $data || ( 1 === count( $data ) && ( null === $data[0] || '' === trim( (string) $data[0] ) ) ) ) {
				continue;
			}

			// Pull each recognised column out by its mapped position.
			$row = array( '_line' => $line_no );
			foreach ( self::COLUMNS as $col ) {
				$pos           = isset( $index[ $col ] ) ? $index[ $col ] : null;
				$raw           = ( null !== $pos && isset( $data[ $pos ] ) ) ? (string) $data[ $pos ] : '';
				$row[ $col ]   = trim( $raw );
			}

			$rows[] = $row;
		}

		fclose( $handle );

		if ( empty( $rows ) ) {
			return new WP_Error( 'no_rows', __( 'The file has a header but no term rows.', 'apotheca-glossary' ) );
		}

		return array( 'rows' => $rows );
	}

	/*
	 * -------------------------------------------------------------------------
	 * Analysis (the preview logic)
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Analyse parsed rows against a mode and the current glossary.
	 *
	 * Decides each row's action (create, update, skip or invalid), tallies the
	 * counts, collects row errors, and reports any related_terms that will not
	 * resolve to a real term once the import has run.
	 *
	 * @param array  $parsed Output of parse_csv (has a 'rows' key).
	 * @param string $mode   The import mode.
	 * @return array Analysis for the view.
	 */
	private function analyse( $parsed, $mode ) {
		$rows          = $parsed['rows'];
		$existing_map  = $this->get_existing_slug_map();
		$counts        = array(
			'create'  => 0,
			'update'  => 0,
			'skip'    => 0,
			'invalid' => 0,
			'delete'  => 0,
		);
		$errors        = array();
		$seen_slugs    = array();
		$analysed      = array();

		// All term names that will exist after import, for related validation.
		// In replace-all only the CSV's terms survive; otherwise the CSV's
		// terms plus the terms already in the glossary.
		$valid_terms = $this->collect_valid_term_names( $rows, $mode );

		foreach ( $rows as $row ) {
			$line = isset( $row['_line'] ) ? (int) $row['_line'] : 0;

			// Fall back to a slug derived from the term if none was supplied.
			if ( '' === $row['slug'] && '' !== $row['term'] ) {
				$row['slug'] = sanitize_title( $row['term'] );
			} else {
				$row['slug'] = sanitize_title( $row['slug'] );
			}

			// Check the required fields.
			$row_errors = array();
			foreach ( self::REQUIRED_COLUMNS as $req ) {
				if ( '' === trim( (string) $row[ $req ] ) ) {
					$row_errors[] = $req;
				}
			}

			// Guard against two rows sharing a slug within the same file.
			if ( '' !== $row['slug'] ) {
				if ( isset( $seen_slugs[ $row['slug'] ] ) ) {
					$row_errors[] = 'duplicate slug';
				}
				$seen_slugs[ $row['slug'] ] = true;
			}

			if ( ! empty( $row_errors ) ) {
				$row['action'] = 'invalid';
				++$counts['invalid'];
				$errors[] = sprintf(
					/* translators: 1: line number, 2: term, 3: list of problems */
					__( 'Line %1$d (%2$s): %3$s.', 'apotheca-glossary' ),
					$line,
					'' !== $row['term'] ? $row['term'] : __( 'no term', 'apotheca-glossary' ),
					implode( ', ', $row_errors )
				);
				$analysed[] = $row;
				continue;
			}

			// Decide the action from the mode and whether the slug exists.
			$exists = isset( $existing_map[ $row['slug'] ] );

			switch ( $mode ) {
				case 'create':
					$row['action'] = $exists ? 'skip' : 'create';
					break;
				case 'create_update':
					$row['action'] = $exists ? 'update' : 'create';
					break;
				case 'replace_all':
				default:
					$row['action'] = $exists ? 'update' : 'create';
					break;
			}

			++$counts[ $row['action'] ];

			// Validate related terms against the post-import term set.
			$unresolved = $this->find_unresolved_related( $row['related_terms'], $valid_terms );
			if ( ! empty( $unresolved ) ) {
				$row['unresolved'] = $unresolved;
			}

			$analysed[] = $row;
		}

		// Replace-all deletions: existing terms whose slug is not in the file.
		$delete_slugs = array();
		if ( 'replace_all' === $mode ) {
			foreach ( $existing_map as $slug => $id ) {
				if ( ! isset( $seen_slugs[ $slug ] ) ) {
					$delete_slugs[]    = $slug;
					++$counts['delete'];
				}
			}
		}

		// Gather the related-term warnings into one flat, readable list.
		$unresolved_related = array();
		foreach ( $analysed as $row ) {
			if ( ! empty( $row['unresolved'] ) ) {
				$unresolved_related[] = array(
					'term'    => $row['term'],
					'missing' => $row['unresolved'],
				);
			}
		}

		return array(
			'rows'               => $analysed,
			'counts'             => $counts,
			'errors'             => $errors,
			'unresolved_related' => $unresolved_related,
			'delete_slugs'       => $delete_slugs,
		);
	}

	/**
	 * Build the set of term names that will exist after an import.
	 *
	 * @param array  $rows The parsed rows.
	 * @param string $mode The import mode.
	 * @return array Map of lowercased term name => true.
	 */
	private function collect_valid_term_names( $rows, $mode ) {
		$valid = array();

		// Every term named in the CSV will exist after import.
		foreach ( $rows as $row ) {
			$name = trim( (string) $row['term'] );
			if ( '' !== $name ) {
				$valid[ $this->normalise( $name ) ] = true;
			}
		}

		// In every mode except replace-all, terms already in the glossary
		// survive and are valid link targets too.
		if ( 'replace_all' !== $mode ) {
			$existing = get_posts(
				array(
					'post_type'      => APGLOS_POST_TYPE,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'all',
				)
			);
			foreach ( $existing as $post ) {
				$valid[ $this->normalise( $post->post_title ) ] = true;
			}
		}

		return $valid;
	}

	/**
	 * Find related terms in a pipe-delimited value that do not resolve.
	 *
	 * @param string $related_value Pipe-delimited related term names.
	 * @param array  $valid_terms   Map of normalised valid term name => true.
	 * @return string[] The related term names that did not resolve.
	 */
	private function find_unresolved_related( $related_value, $valid_terms ) {
		$related_value = trim( (string) $related_value );
		if ( '' === $related_value ) {
			return array();
		}

		$unresolved = array();
		foreach ( explode( '|', $related_value ) as $name ) {
			$name = trim( $name );
			if ( '' === $name ) {
				continue;
			}
			if ( ! isset( $valid_terms[ $this->normalise( $name ) ] ) ) {
				$unresolved[] = $name;
			}
		}

		return $unresolved;
	}

	/**
	 * Normalise a term name for comparison (trimmed, lowercased).
	 *
	 * @param string $value The value to normalise.
	 * @return string
	 */
	private function normalise( $value ) {
		$value = trim( (string) $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value ) : strtolower( $value );
	}

	/**
	 * Build a map of existing glossary slugs to their post ids.
	 *
	 * Cached on the instance so a single request does not query repeatedly.
	 *
	 * @return array Map of slug => post id.
	 */
	private function get_existing_slug_map() {
		static $map = null;

		if ( null !== $map ) {
			return $map;
		}

		$map   = array();
		$posts = get_posts(
			array(
				'post_type'      => APGLOS_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'all',
			)
		);

		foreach ( $posts as $post ) {
			$map[ $post->post_name ] = $post->ID;
		}

		return $map;
	}

	/*
	 * -------------------------------------------------------------------------
	 * Export
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Stream the current glossary as a CSV download.
	 *
	 * Runs on admin_post before any HTML is sent. Exports every term in the
	 * same six-column format the importer reads, all fields quoted, UTF-8 with
	 * no BOM. If the glossary is empty, exports a header-only sample file.
	 *
	 * @return void
	 */
	public function handle_export() {
		// Capability and nonce.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export the glossary.', 'apotheca-glossary' ) );
		}
		check_admin_referer( self::NONCE_EXPORT );

		$rows   = array();
		$rows[] = self::COLUMNS; // Header.

		$posts = get_posts(
			array(
				'post_type'      => APGLOS_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			// One category per term; take the first assigned.
			$terms    = get_the_terms( $post->ID, APGLOS_TAXONOMY );
			$category = ( ! empty( $terms ) && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';

			$rows[] = array(
				$post->post_title,
				$post->post_name,
				$category,
				(string) get_post_meta( $post->ID, 'also_known_as', true ),
				(string) get_post_meta( $post->ID, 'related_terms', true ),
				// Decode entities so the export round-trips back to plain text.
				html_entity_decode( wp_strip_all_tags( $post->post_content ), ENT_QUOTES, 'UTF-8' ),
			);
		}

		// Build the CSV string with every field quoted.
		$csv = '';
		foreach ( $rows as $row ) {
			$csv .= $this->csv_line( $row );
		}

		$filename = 'apotheca-glossary-export-' . gmdate( 'Y-m-d' ) . '.csv';

		// Clear any buffered output so the file is not polluted.
		if ( ob_get_length() ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV is escaped field by field in csv_line().
		exit;
	}

	/**
	 * Format one CSV line with every field quoted and internal quotes doubled.
	 *
	 * @param array $fields The field values for this line.
	 * @return string The CSV line, terminated with CRLF.
	 */
	private function csv_line( $fields ) {
		$quoted = array();
		foreach ( $fields as $field ) {
			// Escape embedded double quotes by doubling them, per CSV convention.
			$quoted[] = '"' . str_replace( '"', '""', (string) $field ) . '"';
		}
		// CRLF line ending is the safest for Excel across platforms.
		return implode( ',', $quoted ) . "\r\n";
	}

	/*
	 * -------------------------------------------------------------------------
	 * View helpers
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Human-readable label for an import mode.
	 *
	 * @param string $mode The mode key.
	 * @return string
	 */
	public function mode_label( $mode ) {
		switch ( $mode ) {
			case 'create':
				return __( 'Create only', 'apotheca-glossary' );
			case 'create_update':
				return __( 'Create and update', 'apotheca-glossary' );
			case 'replace_all':
				return __( 'Replace all', 'apotheca-glossary' );
		}
		return $mode;
	}

	/**
	 * The URL of the importer admin page.
	 *
	 * @return string
	 */
	public function page_url() {
		return admin_url( 'edit.php?post_type=' . APGLOS_POST_TYPE . '&page=' . self::PAGE_SLUG );
	}
}
