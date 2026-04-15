<?php
/**
 * Settings view — API keys, auth, rate limits, CORS.
 *
 * @var \StoreMCP\Plugin $plugin
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$keys                = $plugin->auth->list_api_keys();
$enabled             = (bool) get_option( 'store_mcp_enabled', true );
$rate_free           = (int) get_option( 'store_mcp_rate_limit_free', 30 );
$rate_pro            = (int) get_option( 'store_mcp_rate_limit_pro', 120 );
$retention           = (int) get_option( 'store_mcp_log_retention_days', 30 );
$cache_ttl           = (int) get_option( 'store_mcp_cache_ttl_seconds', 300 );
$allow_app_passwords = (bool) get_option( 'store_mcp_allow_app_passwords', true );
$cors                = (array) get_option( 'store_mcp_cors_allowed', [] );
$cors_text           = implode( "\n", $cors );
$is_pro              = $plugin->license->is_pro();
$oauth_cap           = (string) get_option( 'store_mcp_oauth_min_capability', 'manage_options' );

$capability_options = [
	'manage_options'    => __( 'Administrator (manage_options)', 'store-mcp' ),
	'edit_others_posts' => __( 'Editor (edit_others_posts)', 'store-mcp' ),
	'publish_posts'     => __( 'Author (publish_posts)', 'store-mcp' ),
	'edit_posts'        => __( 'Contributor (edit_posts)', 'store-mcp' ),
	'read'              => __( 'Any logged-in user (read)', 'store-mcp' ),
];
?>
<div class="wrap store-mcp-wrap">
	<h1><?php esc_html_e( 'StoreMCP — Settings', 'store-mcp' ); ?></h1>

	<div id="oauth" class="store-mcp-card">
		<h2><?php esc_html_e( 'OAuth authorization', 'store-mcp' ); ?></h2>
		<p class="description"><?php esc_html_e( 'MCP clients like Claude and ChatGPT authenticate via OAuth 2.1. Choose who on your site is allowed to approve a connection request.', 'store-mcp' ); ?></p>

		<table class="form-table">
			<tr>
				<th><label for="oauth_min_capability"><?php esc_html_e( 'Required capability to authorize', 'store-mcp' ); ?></label></th>
				<td>
					<select name="oauth_min_capability" id="oauth_min_capability" form="store-mcp-settings-form">
						<?php foreach ( $capability_options as $cap => $label ) : ?>
							<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( $oauth_cap, $cap ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Only users with this capability see the "Allow" button on the consent screen. The granted token inherits the approving user\'s capabilities.', 'store-mcp' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<div id="api-keys" class="store-mcp-card">
		<h2><?php esc_html_e( 'API keys (advanced)', 'store-mcp' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Most users should use OAuth — just paste the MCP URL in their AI client. API keys are useful for scripts, n8n, or clients that do not support OAuth. Keys are hashed: copy each one when you generate it.', 'store-mcp' ); ?></p>

		<form id="store-mcp-key-form" class="store-mcp-inline-form">
			<input type="text" name="label" placeholder="<?php esc_attr_e( 'Key label (e.g. Claude desktop)', 'store-mcp' ); ?>" required>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate API key', 'store-mcp' ); ?></button>
		</form>

		<div id="store-mcp-new-key" class="store-mcp-new-key" style="display:none">
			<strong><?php esc_html_e( 'New API key', 'store-mcp' ); ?></strong>
			<div class="store-mcp-copyable">
				<code id="store-mcp-new-key-value"></code>
				<button type="button" class="button button-small store-mcp-copy" data-target="#store-mcp-new-key-value"><?php esc_html_e( 'Copy', 'store-mcp' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Save it now — it will not be shown again.', 'store-mcp' ); ?></p>
		</div>

		<table class="widefat striped store-mcp-keys-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Label', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Key id', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'User', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Created', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Last used', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Status', 'store-mcp' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody id="store-mcp-keys-tbody">
			<?php if ( empty( $keys ) ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No keys yet.', 'store-mcp' ); ?></td></tr>
			<?php else : foreach ( $keys as $row ) :
				$user = get_userdata( (int) $row->user_id );
				$revoked = ! empty( $row->revoked_at );
				?>
				<tr data-id="<?php echo esc_attr( (string) $row->id ); ?>">
					<td><?php echo esc_html( $row->label ?: '—' ); ?></td>
					<td><code>smcp_<?php echo esc_html( $row->key_id ); ?>_…</code></td>
					<td><?php echo esc_html( $user ? $user->user_login : '—' ); ?></td>
					<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row->created_at ) ); ?></td>
					<td><?php echo $row->last_used_at ? esc_html( mysql2date( 'Y-m-d H:i', $row->last_used_at ) ) : '—'; ?></td>
					<td><?php echo $revoked ? '<span class="store-mcp-pill warn">' . esc_html__( 'Revoked', 'store-mcp' ) . '</span>' : '<span class="store-mcp-pill ok">' . esc_html__( 'Active', 'store-mcp' ) . '</span>'; ?></td>
					<td>
						<?php if ( ! $revoked ) : ?>
							<button type="button" class="button-link store-mcp-revoke-key" data-id="<?php echo esc_attr( (string) $row->id ); ?>"><?php esc_html_e( 'Revoke', 'store-mcp' ); ?></button>
						<?php endif; ?>
						<button type="button" class="button-link store-mcp-delete-key" data-id="<?php echo esc_attr( (string) $row->id ); ?>" style="color:#a00"><?php esc_html_e( 'Delete', 'store-mcp' ); ?></button>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>

	<form id="store-mcp-settings-form">
		<div class="store-mcp-card">
			<h2><?php esc_html_e( 'General', 'store-mcp' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label><?php esc_html_e( 'MCP server enabled', 'store-mcp' ); ?></label></th>
					<td><label class="store-mcp-switch"><input type="checkbox" name="enabled" value="1" <?php checked( $enabled ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'When off, all MCP endpoints return 503.', 'store-mcp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label><?php esc_html_e( 'Allow Application Passwords', 'store-mcp' ); ?></label></th>
					<td><label class="store-mcp-switch"><input type="checkbox" name="allow_app_passwords" value="1" <?php checked( $allow_app_passwords ); ?>><span></span></label>
						<p class="description"><?php esc_html_e( 'In addition to StoreMCP API keys, accept native WordPress Application Passwords.', 'store-mcp' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="store-mcp-card">
			<h2><?php esc_html_e( 'Rate limits', 'store-mcp' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="rate_limit_free"><?php esc_html_e( 'Free tier (req/min)', 'store-mcp' ); ?></label></th>
					<td><input type="number" min="1" name="rate_limit_free" id="rate_limit_free" value="<?php echo esc_attr( (string) $rate_free ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="rate_limit_pro"><?php esc_html_e( 'Pro tier (req/min)', 'store-mcp' ); ?></label></th>
					<td><input type="number" min="1" name="rate_limit_pro" id="rate_limit_pro" value="<?php echo esc_attr( (string) $rate_pro ); ?>" class="small-text" <?php disabled( ! $is_pro ); ?>>
						<?php if ( ! $is_pro ) : ?><p class="description"><?php esc_html_e( 'Activate Pro to raise this limit.', 'store-mcp' ); ?></p><?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="cache_ttl_seconds"><?php esc_html_e( 'Report cache (seconds)', 'store-mcp' ); ?></label></th>
					<td><input type="number" min="0" name="cache_ttl_seconds" id="cache_ttl_seconds" value="<?php echo esc_attr( (string) $cache_ttl ); ?>" class="small-text"></td>
				</tr>
			</table>
		</div>

		<div class="store-mcp-card">
			<h2><?php esc_html_e( 'CORS', 'store-mcp' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="cors_allowed"><?php esc_html_e( 'Allowed origins', 'store-mcp' ); ?></label></th>
					<td>
						<textarea name="cors_allowed" id="cors_allowed" rows="4" class="large-text code" placeholder="https://claude.ai&#10;https://chat.openai.com"><?php echo esc_textarea( $cors_text ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One origin per line. Use * to allow any (not recommended).', 'store-mcp' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="store-mcp-card">
			<h2><?php esc_html_e( 'Activity log', 'store-mcp' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="log_retention_days"><?php esc_html_e( 'Retention (days)', 'store-mcp' ); ?></label></th>
					<td><input type="number" min="1" name="log_retention_days" id="log_retention_days" value="<?php echo esc_attr( (string) $retention ); ?>" class="small-text"></td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'store-mcp' ); ?></button>
			<span id="store-mcp-settings-result" class="description"></span>
		</p>
	</form>
</div>
