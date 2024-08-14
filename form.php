<div id="edit-form-<?php echo $file_id; ?>" class="inline-edit-wrapper" aria-labelledby="quick-edit-legend">
	<form method="post" enctype="multipart/form-data">
		<input type="hidden" name="update_file" value="1">
		<input type="hidden" name="file_id" value="<?php echo esc_attr( $file_id ); ?>">
		<?php wp_nonce_field( 'edit_file_' . $file_id, 'edit_file_nonce' ); ?>
		<fieldset class="inline-edit-col-left">
			<legend class="inline-edit-legend">Edit File</legend>
			<div class="inline-edit-col">
				<label>
					<span class="title">Name</span>
					<span class="input-text-wrap">
						<?php echo esc_html( $item['file_name'] ); ?>
					</span>
				</label>
				<label>
					<span class="title">Upload</span>
					<span class="input-text-wrap">
						<input type="file" name="new_file" id="new_file_<?php echo $file_id; ?>">
					</span>
				</label>
				<label>
					<span class="title">Version</span>
					<span class="input-text-wrap">
						<input type="text" name="version" id="version_<?php echo $file_id; ?>"
							value="<?php echo esc_attr( $item['version'] ); ?>">
					</span>
				</label>
			</div>
		</fieldset>
		<div class="submit inline-edit-save">
			<input type="submit" name="submit" value="Update" class="button button-primary">
			<button type="button" class="button cancel-edit">Cancel</button>
		</div>
	</form>
</div>