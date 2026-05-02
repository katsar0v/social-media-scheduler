<?php
/**
 * API Keys settings card.
 *
 * @package KatsarovDesign\SocialMediaScheduler
 */

declare(strict_types=1);

$api_keys          = isset( $api_keys ) && is_array( $api_keys ) ? $api_keys : array();
$total_items       = isset( $total_items ) ? (int) $total_items : 0;
$current_page      = isset( $current_page ) ? (int) $current_page : 1;
$items_per_page    = isset( $per_page ) ? (int) $per_page : 20;
$status_filter     = isset( $status_filter ) ? (string) $status_filter : '';
$valid_statuses    = isset( $valid_statuses ) && is_array( $valid_statuses ) ? $valid_statuses : array();
$permission_groups = isset( $permission_groups ) && is_array( $permission_groups ) ? $permission_groups : array();
$nonce             = isset( $nonce ) && is_string( $nonce ) ? $nonce : wp_create_nonce( 'sms_api_key_action' );
$status_labels     = array(
	'active'   => __( 'Active', 'social-media-scheduler' ),
	'inactive' => __( 'Inactive', 'social-media-scheduler' ),
	'revoked'  => __( 'Revoked', 'social-media-scheduler' ),
);
?>
<section class="sms-panel sms-api-keys-card">
	<div class="sms-card-header">
		<div>
			<h2><?php esc_html_e( 'API Keys', 'social-media-scheduler' ); ?></h2>
		</div>
		<button type="button" class="button button-primary sms-api-key-create">
			<?php esc_html_e( 'Add New', 'social-media-scheduler' ); ?>
		</button>
	</div>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="sms-api-key-filter-form">
		<input type="hidden" name="page" value="sms-settings" />
		<div class="sms-filter-row">
			<div class="sms-filter-group">
				<label for="sms-api-key-status-filter"><?php esc_html_e( 'Status', 'social-media-scheduler' ); ?></label>
				<select id="sms-api-key-status-filter" name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'social-media-scheduler' ); ?></option>
					<?php foreach ( $valid_statuses as $status_option ) : ?>
						<option value="<?php echo esc_attr( $status_option ); ?>" <?php selected( $status_filter, $status_option ); ?>>
							<?php echo esc_html( $status_labels[ $status_option ] ?? ucfirst( $status_option ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="sms-filter-group sms-filter-actions">
				<button type="submit" class="button">
					<?php esc_html_e( 'Filter', 'social-media-scheduler' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sms-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'social-media-scheduler' ); ?>
				</a>
			</div>
		</div>
	</form>

	<table class="wp-list-table widefat fixed striped sms-api-keys-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'social-media-scheduler' ); ?></th>
				<th><?php esc_html_e( 'Status', 'social-media-scheduler' ); ?></th>
				<th><?php esc_html_e( 'Permissions', 'social-media-scheduler' ); ?></th>
				<th><?php esc_html_e( 'Last Used', 'social-media-scheduler' ); ?></th>
				<th><?php esc_html_e( 'Created', 'social-media-scheduler' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'social-media-scheduler' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $api_keys ) ) : ?>
				<tr>
					<td colspan="6">
						<?php esc_html_e( 'No API keys found.', 'social-media-scheduler' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $api_keys as $key ) : ?>
					<tr data-key-id="<?php echo esc_attr( (string) $key['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( (string) $key['name'] ); ?></strong>
							<div class="sms-key-id">
								<?php
								printf(
									/* translators: %d: API key ID. */
									esc_html__( 'ID: %d', 'social-media-scheduler' ),
									(int) $key['id']
								);
								?>
							</div>
						</td>
						<td>
							<span class="sms-badge sms-badge--<?php echo esc_attr( (string) $key['status'] ); ?>">
								<?php echo esc_html( $status_labels[ (string) $key['status'] ] ?? ucfirst( (string) $key['status'] ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( ! empty( $key['permissions'] ) && is_array( $key['permissions'] ) ) : ?>
								<div class="sms-permission-list">
									<?php foreach ( $key['permissions'] as $permission ) : ?>
										<span class="sms-permission-tag" title="<?php echo esc_attr( (string) $permission ); ?>">
											<?php echo esc_html( (string) $permission ); ?>
										</span>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<span class="sms-text-muted">
									<?php esc_html_e( 'No permissions', 'social-media-scheduler' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $key['last_used_at'] ) ) : ?>
								<?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( (string) $key['last_used_at'] ) ) ); ?>
							<?php else : ?>
								<span class="sms-text-muted">
									<?php esc_html_e( 'Never', 'social-media-scheduler' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( gmdate( 'Y-m-d H:i', strtotime( (string) $key['created_at'] ) ) ); ?>
						</td>
						<td>
							<div class="sms-key-actions">
								<button type="button" class="button button-link sms-edit-key" data-key-id="<?php echo esc_attr( (string) $key['id'] ); ?>">
									<?php esc_html_e( 'Edit', 'social-media-scheduler' ); ?>
								</button>
								<button type="button" class="button button-link sms-delete-key" data-key-id="<?php echo esc_attr( (string) $key['id'] ); ?>">
									<?php esc_html_e( 'Delete', 'social-media-scheduler' ); ?>
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_items > $items_per_page ) : ?>
		<div class="sms-pagination">
			<?php
			$total_pages = (int) ceil( $total_items / $items_per_page );
			for ( $i = 1; $i <= $total_pages; $i++ ) :
				$page_url = add_query_arg(
					array(
						'page'     => 'sms-settings',
						'page_num' => $i,
						'status'   => $status_filter,
					),
					admin_url( 'admin.php' )
				);
				?>
				<a href="<?php echo esc_url( $page_url ); ?>" class="button <?php echo esc_attr( $i === $current_page ? 'button-primary' : '' ); ?>">
					<?php echo esc_html( (string) $i ); ?>
				</a>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</section>

<div id="sms-api-key-modal" class="sms-modal" style="display: none;">
	<div class="sms-modal-content">
		<div class="sms-modal-header">
			<h2 id="sms-modal-title"><?php esc_html_e( 'Create API Key', 'social-media-scheduler' ); ?></h2>
			<button type="button" class="sms-modal-close" aria-label="<?php esc_attr_e( 'Close', 'social-media-scheduler' ); ?>">&times;</button>
		</div>
		<form id="sms-api-key-form" method="post">
			<?php wp_nonce_field( 'sms_api_key_action', 'sms_api_key_nonce' ); ?>
			<input type="hidden" name="key_id" id="sms-key-id" value="0" />

			<div class="sms-form-group">
				<label for="sms-key-name"><?php esc_html_e( 'Key Name', 'social-media-scheduler' ); ?> *</label>
				<input type="text" id="sms-key-name" name="name" required />
			</div>

			<div class="sms-form-group">
				<label for="sms-key-status"><?php esc_html_e( 'Status', 'social-media-scheduler' ); ?></label>
				<select id="sms-key-status" name="status">
					<?php foreach ( $valid_statuses as $status_option ) : ?>
						<option value="<?php echo esc_attr( $status_option ); ?>">
							<?php echo esc_html( $status_labels[ $status_option ] ?? ucfirst( $status_option ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="sms-form-group">
				<label><?php esc_html_e( 'Permissions', 'social-media-scheduler' ); ?></label>
				<div class="sms-permission-checkboxes">
					<?php foreach ( $permission_groups as $group ) : ?>
						<div class="sms-permission-group">
							<h4><?php echo esc_html( (string) $group['label'] ); ?></h4>
							<?php foreach ( $group['permissions'] as $permission ) : ?>
								<label class="sms-checkbox">
									<input type="checkbox" name="permissions[]" value="<?php echo esc_attr( (string) $permission ); ?>" />
									<span><?php echo esc_html( (string) $permission ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="sms-modal-actions">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save', 'social-media-scheduler' ); ?>
				</button>
				<button type="button" class="button sms-modal-cancel">
					<?php esc_html_e( 'Cancel', 'social-media-scheduler' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>

<div id="sms-key-plaintext-modal" class="sms-modal" style="display: none;">
	<div class="sms-modal-content sms-modal-narrow">
		<div class="sms-modal-header">
			<h2><?php esc_html_e( 'API Key Created', 'social-media-scheduler' ); ?></h2>
			<button type="button" class="sms-modal-close" aria-label="<?php esc_attr_e( 'Close', 'social-media-scheduler' ); ?>">&times;</button>
		</div>
		<div class="sms-modal-body">
			<p class="sms-warning">
				<?php esc_html_e( 'Copy this key now! It will not be shown again.', 'social-media-scheduler' ); ?>
			</p>
			<div class="sms-plaintext-key-container">
				<code id="sms-plaintext-key"></code>
				<button type="button" class="button sms-copy-key">
					<?php esc_html_e( 'Copy Key', 'social-media-scheduler' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(function($) {
	var reloadAfterPlaintextModal = false;

	$('.sms-api-key-create').on('click', function(e) {
		e.preventDefault();
		$('#sms-api-key-form')[0].reset();
		$('#sms-key-id').val('0');
		$('#sms-modal-title').text('<?php echo esc_js( __( 'Create API Key', 'social-media-scheduler' ) ); ?>');
		$('#sms-api-key-modal').css('display', 'flex');
	});

	$('.sms-modal-close, .sms-modal-cancel').on('click', function() {
		var modal = $(this).closest('.sms-modal');
		modal.hide();
		if ('sms-key-plaintext-modal' === modal.attr('id') && reloadAfterPlaintextModal) {
			location.reload();
		}
	});

	$(document).on('click', '.sms-edit-key', function() {
		var keyId = $(this).data('key-id');
		var row = $(this).closest('tr');
		var name = row.find('strong').text().trim();
		var status = row.find('.sms-badge').text().trim().toLowerCase();
		var permissions = [];
		row.find('.sms-permission-tag').each(function() {
			permissions.push($(this).attr('title'));
		});

		$('#sms-key-id').val(keyId);
		$('#sms-key-name').val(name);
		$('#sms-key-status').val(status);
		$('#sms-api-key-form input[type="checkbox"]').prop('checked', false);
		permissions.forEach(function(permission) {
			$('#sms-api-key-form input[value="' + permission + '"]').prop('checked', true);
		});
		$('#sms-modal-title').text('<?php echo esc_js( __( 'Edit API Key', 'social-media-scheduler' ) ); ?>');
		$('#sms-api-key-modal').css('display', 'flex');
	});

	$(document).on('click', '.sms-delete-key', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete this API key? This action cannot be undone.', 'social-media-scheduler' ) ); ?>')) {
			return;
		}

		var keyId = $(this).data('key-id');
		var row = $(this).closest('tr');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'sms_delete_api_key',
				key_id: keyId,
				_ajax_nonce: '<?php echo esc_js( $nonce ); ?>'
			},
			success: function(response) {
				if (response.success) {
					row.fadeOut(300, function() { $(this).remove(); });
				}
			},
			error: function() {
				alert('<?php echo esc_js( __( 'Failed to delete API key.', 'social-media-scheduler' ) ); ?>');
			}
		});
	});

	$('#sms-api-key-form').on('submit', function(e) {
		e.preventDefault();

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: $(this).serialize() + '&action=sms_save_api_key&_ajax_nonce=' + $('#sms_api_key_nonce').val(),
			success: function(response) {
				if (response.success) {
					$('#sms-api-key-modal').hide();
					if (response.data.plaintext_key) {
						$('#sms-plaintext-key').text(response.data.plaintext_key);
						reloadAfterPlaintextModal = true;
						$('#sms-key-plaintext-modal').css('display', 'flex');
					} else {
						location.reload();
					}
				}
			},
			error: function(xhr) {
				var msg = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : '<?php echo esc_js( __( 'Failed to save API key.', 'social-media-scheduler' ) ); ?>';
				alert(msg);
			}
		});
	});

	$('.sms-copy-key').on('click', function() {
		var key = $('#sms-plaintext-key').text();
		navigator.clipboard.writeText(key).then(function() {
			alert('<?php echo esc_js( __( 'API key copied to clipboard!', 'social-media-scheduler' ) ); ?>');
		});
	});
});
</script>
