<?php
/**
 * Import / Export admin page view.
 *
 * Rendered by Apglos_Importer::render_page(). The following variables are in
 * scope when this file is included:
 *
 * @var Apglos_Importer $this    The importer instance (for helper methods).
 * @var string          $step    'form' | 'preview' | 'confirm_replace' | 'result'.
 * @var string          $mode    The selected import mode.
 * @var string          $token   The transient token tying preview to commit.
 * @var array|null      $preview Preview data, when $step is preview/confirm.
 * @var array|null      $result  Result counts, when $step is result.
 * @var array           $notices Notices to show at the top of the page.
 *
 * @package Apotheca_Glossary
 */

// Stop anyone loading this file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap apglos-import">
	<h1><?php esc_html_e( 'Glossary Import / Export', 'apotheca-glossary' ); ?></h1>

	<?php
	// Any error or info notices from processing this request.
	foreach ( $notices as $notice ) {
		printf(
			'<div class="notice notice-%1$s"><p>%2$s</p></div>',
			esc_attr( $notice['type'] ),
			esc_html( $notice['text'] )
		);
	}
	?>

	<?php if ( 'result' === $step && $result ) : ?>

		<?php // ---------- Step 4: import finished, show the outcome ---------- ?>
		<div class="notice notice-success">
			<p><strong><?php esc_html_e( 'Import complete.', 'apotheca-glossary' ); ?></strong></p>
		</div>

		<div class="apglos-card">
			<h2><?php esc_html_e( 'Results', 'apotheca-glossary' ); ?></h2>
			<ul class="apglos-result-list">
				<li>
					<span class="apglos-badge apglos-badge--create"><?php echo esc_html( $result['created'] ); ?></span>
					<?php esc_html_e( 'terms created', 'apotheca-glossary' ); ?>
				</li>
				<li>
					<span class="apglos-badge apglos-badge--update"><?php echo esc_html( $result['updated'] ); ?></span>
					<?php esc_html_e( 'terms updated', 'apotheca-glossary' ); ?>
				</li>
				<li>
					<span class="apglos-badge apglos-badge--skip"><?php echo esc_html( $result['skipped'] ); ?></span>
					<?php esc_html_e( 'terms skipped', 'apotheca-glossary' ); ?>
				</li>
				<?php if ( 'replace_all' === $result['mode'] ) : ?>
					<li>
						<span class="apglos-badge apglos-badge--delete"><?php echo esc_html( $result['deleted'] ); ?></span>
						<?php esc_html_e( 'terms deleted', 'apotheca-glossary' ); ?>
					</li>
				<?php endif; ?>
			</ul>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $this->page_url() ); ?>">
					<?php esc_html_e( 'Import another file', 'apotheca-glossary' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . APGLOS_POST_TYPE ) ); ?>">
					<?php esc_html_e( 'View all terms', 'apotheca-glossary' ); ?>
				</a>
			</p>
		</div>

	<?php elseif ( 'confirm_replace' === $step && $preview ) : ?>

		<?php // ---------- Step 3: replace-all second confirmation ---------- ?>
		<div class="apglos-card apglos-card--danger">
			<h2><?php esc_html_e( 'Please confirm: Replace all', 'apotheca-glossary' ); ?></h2>
			<p class="apglos-danger-text">
				<?php esc_html_e( 'Replace all will make the glossary match this file exactly. Any term not in the file will be permanently deleted. This cannot be undone.', 'apotheca-glossary' ); ?>
			</p>

			<table class="apglos-summary">
				<tbody>
					<tr>
						<th><?php esc_html_e( 'Will be created', 'apotheca-glossary' ); ?></th>
						<td><?php echo esc_html( $preview['counts']['create'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Will be updated', 'apotheca-glossary' ); ?></th>
						<td><?php echo esc_html( $preview['counts']['update'] ); ?></td>
					</tr>
					<tr class="apglos-summary__danger">
						<th><?php esc_html_e( 'Will be deleted', 'apotheca-glossary' ); ?></th>
						<td><strong><?php echo esc_html( $preview['counts']['delete'] ); ?></strong></td>
					</tr>
				</tbody>
			</table>

			<?php if ( ! empty( $preview['delete_slugs'] ) ) : ?>
				<details class="apglos-details">
					<summary><?php esc_html_e( 'Show the terms that will be deleted', 'apotheca-glossary' ); ?></summary>
					<ul class="apglos-delete-list">
						<?php foreach ( $preview['delete_slugs'] as $slug ) : ?>
							<li><code><?php echo esc_html( $slug ); ?></code></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>">
				<?php wp_nonce_field( Apglos_Importer::NONCE_COMMIT, 'apglos_commit_nonce' ); ?>
				<input type="hidden" name="apglos_action" value="commit" />
				<input type="hidden" name="apglos_token" value="<?php echo esc_attr( $preview['token'] ); ?>" />
				<input type="hidden" name="apglos_confirm_replace" value="1" />

				<p>
					<label>
						<input type="checkbox" name="apglos_ack" value="1" required />
						<?php
						printf(
							/* translators: %d: number of terms to be deleted */
							esc_html__( 'I understand that %d existing term(s) will be permanently deleted.', 'apotheca-glossary' ),
							(int) $preview['counts']['delete']
						);
						?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary apglos-button-danger">
						<?php esc_html_e( 'Yes, replace the entire glossary', 'apotheca-glossary' ); ?>
					</button>
					<a class="button" href="<?php echo esc_url( $this->page_url() ); ?>">
						<?php esc_html_e( 'Cancel', 'apotheca-glossary' ); ?>
					</a>
				</p>
			</form>
		</div>

	<?php elseif ( 'preview' === $step && $preview ) : ?>

		<?php // ---------- Step 2: preview before committing ---------- ?>
		<div class="apglos-card">
			<h2><?php esc_html_e( 'Preview', 'apotheca-glossary' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: file name, 2: mode label */
					esc_html__( 'File: %1$s. Mode: %2$s.', 'apotheca-glossary' ),
					'<strong>' . esc_html( $preview['filename'] ) . '</strong>',
					'<strong>' . esc_html( $this->mode_label( $preview['mode'] ) ) . '</strong>'
				);
				?>
			</p>

			<table class="apglos-summary">
				<tbody>
					<tr>
						<th><span class="apglos-badge apglos-badge--create"></span><?php esc_html_e( 'Create', 'apotheca-glossary' ); ?></th>
						<td><?php echo esc_html( $preview['counts']['create'] ); ?></td>
					</tr>
					<tr>
						<th><span class="apglos-badge apglos-badge--update"></span><?php esc_html_e( 'Update', 'apotheca-glossary' ); ?></th>
						<td><?php echo esc_html( $preview['counts']['update'] ); ?></td>
					</tr>
					<tr>
						<th><span class="apglos-badge apglos-badge--skip"></span><?php esc_html_e( 'Skip', 'apotheca-glossary' ); ?></th>
						<td><?php echo esc_html( $preview['counts']['skip'] ); ?></td>
					</tr>
					<?php if ( $preview['counts']['invalid'] > 0 ) : ?>
						<tr>
							<th><span class="apglos-badge apglos-badge--invalid"></span><?php esc_html_e( 'Invalid (will be ignored)', 'apotheca-glossary' ); ?></th>
							<td><?php echo esc_html( $preview['counts']['invalid'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( 'replace_all' === $preview['mode'] ) : ?>
						<tr class="apglos-summary__danger">
							<th><span class="apglos-badge apglos-badge--delete"></span><?php esc_html_e( 'Delete', 'apotheca-glossary' ); ?></th>
							<td><?php echo esc_html( $preview['counts']['delete'] ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php // Validation report: rows that could not be imported. ?>
			<?php if ( ! empty( $preview['errors'] ) ) : ?>
				<div class="apglos-report apglos-report--error">
					<h3><?php esc_html_e( 'Rows that will be ignored', 'apotheca-glossary' ); ?></h3>
					<ul>
						<?php foreach ( $preview['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php // Validation report: related terms that will not resolve. ?>
			<?php if ( ! empty( $preview['unresolved_related'] ) ) : ?>
				<div class="apglos-report apglos-report--warning">
					<h3><?php esc_html_e( 'Related terms that do not resolve', 'apotheca-glossary' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'These related terms do not match any term in the file or the existing glossary. The import will still run, but these links will not appear until the missing terms exist.', 'apotheca-glossary' ); ?>
					</p>
					<ul>
						<?php foreach ( $preview['unresolved_related'] as $item ) : ?>
							<li>
								<strong><?php echo esc_html( $item['term'] ); ?></strong>:
								<?php echo esc_html( implode( ', ', $item['missing'] ) ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php // The full per-row table. ?>
			<h3><?php esc_html_e( 'Every row', 'apotheca-glossary' ); ?></h3>
			<div class="apglos-table-scroll">
				<table class="widefat striped apglos-preview-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Line', 'apotheca-glossary' ); ?></th>
							<th><?php esc_html_e( 'Term', 'apotheca-glossary' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'apotheca-glossary' ); ?></th>
							<th><?php esc_html_e( 'Category', 'apotheca-glossary' ); ?></th>
							<th><?php esc_html_e( 'Action', 'apotheca-glossary' ); ?></th>
							<th><?php esc_html_e( 'Notes', 'apotheca-glossary' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $preview['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['_line'] ); ?></td>
								<td><?php echo esc_html( $row['term'] ); ?></td>
								<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
								<td><?php echo esc_html( $row['category'] ); ?></td>
								<td>
									<span class="apglos-badge apglos-badge--<?php echo esc_attr( $row['action'] ); ?>">
										<?php echo esc_html( ucfirst( $row['action'] ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( ! empty( $row['unresolved'] ) ) : ?>
										<span class="apglos-note-warning">
											<?php
											printf(
												/* translators: %s: comma separated related term names */
												esc_html__( 'Unresolved related: %s', 'apotheca-glossary' ),
												esc_html( implode( ', ', $row['unresolved'] ) )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php // Commit form. Replace-all routes through a second screen. ?>
			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>" class="apglos-commit-form">
				<?php wp_nonce_field( Apglos_Importer::NONCE_COMMIT, 'apglos_commit_nonce' ); ?>
				<input type="hidden" name="apglos_action" value="commit" />
				<input type="hidden" name="apglos_token" value="<?php echo esc_attr( $preview['token'] ); ?>" />

				<p>
					<button type="submit" class="button button-primary button-hero">
						<?php
						if ( 'replace_all' === $preview['mode'] ) {
							esc_html_e( 'Continue to replace all', 'apotheca-glossary' );
						} else {
							esc_html_e( 'Confirm and import', 'apotheca-glossary' );
						}
						?>
					</button>
					<a class="button" href="<?php echo esc_url( $this->page_url() ); ?>">
						<?php esc_html_e( 'Cancel', 'apotheca-glossary' ); ?>
					</a>
				</p>
			</form>
		</div>

	<?php else : ?>

		<?php // ---------- Step 1: the upload form and the export ---------- ?>
		<div class="apglos-card">
			<h2><?php esc_html_e( 'Import terms', 'apotheca-glossary' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload a CSV with the columns: term, slug, category, also_known_as, related_terms, definition. Nothing is written until you have seen the preview and confirmed.', 'apotheca-glossary' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( $this->page_url() ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( Apglos_Importer::NONCE_PREVIEW, 'apglos_preview_nonce' ); ?>
				<input type="hidden" name="apglos_action" value="preview" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="apglos_csv"><?php esc_html_e( 'CSV file', 'apotheca-glossary' ); ?></label>
							</th>
							<td>
								<input type="file" id="apglos_csv" name="apglos_csv" accept=".csv,text/csv" required />
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Import mode', 'apotheca-glossary' ); ?></th>
							<td>
								<fieldset>
									<label class="apglos-mode">
										<input type="radio" name="apglos_mode" value="create" <?php checked( $mode, 'create' ); ?> />
										<strong><?php esc_html_e( 'Create only', 'apotheca-glossary' ); ?></strong>
										<span class="description"><?php esc_html_e( 'Add new terms. Skip any whose slug already exists. Never changes an existing term.', 'apotheca-glossary' ); ?></span>
									</label>
									<label class="apglos-mode">
										<input type="radio" name="apglos_mode" value="create_update" <?php checked( $mode, 'create_update' ); ?> />
										<strong><?php esc_html_e( 'Create and update', 'apotheca-glossary' ); ?></strong>
										<span class="description"><?php esc_html_e( 'Add new terms and update existing ones matched by slug. The usual choice.', 'apotheca-glossary' ); ?></span>
									</label>
									<label class="apglos-mode">
										<input type="radio" name="apglos_mode" value="replace_all" <?php checked( $mode, 'replace_all' ); ?> />
										<strong><?php esc_html_e( 'Replace all', 'apotheca-glossary' ); ?></strong>
										<span class="description"><?php esc_html_e( 'Make the glossary match the file exactly. Any term not in the file is deleted. Asks twice before doing anything.', 'apotheca-glossary' ); ?></span>
									</label>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Upload and preview', 'apotheca-glossary' ); ?>
					</button>
				</p>
			</form>
		</div>

		<div class="apglos-card">
			<h2><?php esc_html_e( 'Export current glossary', 'apotheca-glossary' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Download every term currently in the glossary as a CSV, in the same format the importer reads. Use it as a backup, a template, or to edit in a spreadsheet and re-import.', 'apotheca-glossary' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( Apglos_Importer::NONCE_EXPORT ); ?>
				<input type="hidden" name="action" value="apglos_glossary_export" />
				<button type="submit" class="button">
					<?php esc_html_e( 'Download sample CSV', 'apotheca-glossary' ); ?>
				</button>
			</form>
		</div>

	<?php endif; ?>
</div>
