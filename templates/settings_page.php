<div id="cjCfi_page" class="wrap">
	<h1><?php _e( 'Admin Search By Meta', 'cj-asm' ); ?></h1>

	<form method="POST" action="">
		<?php wp_nonce_field( 'cj_asm_settings' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><?php _e( 'Connect search for', 'cj-asm' ); ?></th>
				<td>
					<fieldset>
					<?php
						$post_types = get_post_types( array( 'show_ui' => 1 ), 'objects' );

						foreach ($post_types as $post_type) {
							?>
							<label><input name="cj_asm_settings[post_types][]" type="checkbox"  value="<?php echo $post_type->name; ?>" <?php if (in_array($post_type->name, $this->settings['post_types'])) echo 'checked'; ?> />
								<?php echo $post_type->label; ?></label><br />
							<?php
						}
					?>
					</fieldset>
				</td>
			</tr>
		</table>
		<p class="submit"><input type="submit" name="submit" class="button button-primary" value="<?php _e( 'Save settings', 'cj-asm' ); ?>"></p>

		<table class="form-table">
			<?php $fields = $this->getAllFields($this->settings['post_types']); ?>
			<?php foreach ($this->settings['post_types'] as $post_type) { ?>
				<?php $type = get_post_type_object( $post_type ); ?>
			<tr>
				<th scope="row"><?php echo $type->label; ?></th>
				<td>
					<?php if (isset($fields[$post_type])) { ?>
						<fieldset>
							<?php foreach ($fields[$post_type] as $field) { ?>
								<label><input name="cj_asm_settings[meta_keys][<?php echo $post_type; ?>][]>" type="checkbox"  value="<?php echo $field; ?>" <?php if (in_array($field, $this->settings['meta_keys'][$post_type])) echo 'checked'; ?> />
									<?php echo $field; ?></label><br />
							<?php } ?>
						</fieldset>
					<?php } ?>
				</td>
			</tr>
			<?php } ?>
		</table>
		<p class="submit"><input type="submit" name="submit" class="button button-primary" value="<?php _e( 'Save settings', 'cj-asm' ); ?>"></p>
	</form>
</div>