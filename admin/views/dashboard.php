<?php
/**
 * Dashboard — Connect-first UX.
 *
 * @var \StoreMCP\Plugin $plugin
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$stats        = $plugin->activity_log->stats();
$tier         = $plugin->license->tier();
$state        = $plugin->license->state();
$wl           = (array) get_option( 'store_mcp_white_label', [] );
$is_agency    = $plugin->license->is_agency();
$brand_name   = ( $is_agency && ! empty( $wl['name'] ) ) ? $wl['name'] : 'StoreMCP';
$endpoint     = rest_url( STORE_MCP_REST_NAMESPACE . '/mcp' );
$wc_active    = class_exists( 'WooCommerce' );
$tools_count  = count( $plugin->tools->all() );
$clients      = $plugin->oauth->list_clients();
?>
<div class="wrap store-mcp-wrap">
	<h1 class="wp-heading-inline">
		<?php echo esc_html( $brand_name ); ?>
		<span class="store-mcp-tier store-mcp-tier-<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( strtoupper( $tier ) ); ?></span>
	</h1>
	<p class="description"><?php esc_html_e( 'Connect your site to Claude, ChatGPT, Cursor, and any MCP-compatible AI client.', 'store-mcp' ); ?></p>

	<div class="store-mcp-hero-card">
		<div class="store-mcp-hero-head">
			<h2><?php esc_html_e( 'Your MCP server URL', 'store-mcp' ); ?></h2>
			<p><?php esc_html_e( 'Paste this URL into any MCP-compatible AI client. It handles authentication automatically.', 'store-mcp' ); ?></p>
		</div>
		<div class="store-mcp-endpoint-row">
			<input type="text" readonly value="<?php echo esc_attr( $endpoint ); ?>" id="store-mcp-endpoint-field" onclick="this.select()">
			<button type="button" class="button button-primary store-mcp-copy" data-copy="<?php echo esc_attr( $endpoint ); ?>">
				<span class="dashicons dashicons-clipboard"></span>
				<?php esc_html_e( 'Copy URL', 'store-mcp' ); ?>
			</button>
		</div>
	</div>

	<div class="store-mcp-connect-grid">
		<div class="store-mcp-connect-card" data-client="claude">
			<div class="store-mcp-connect-head">
				<div class="store-mcp-connect-logo">
					<svg viewBox="0 0 120 120" width="40" height="40" aria-hidden="true"><path fill="#D97757" d="M60 10c27.6 0 50 22.4 50 50s-22.4 50-50 50S10 87.6 10 60 32.4 10 60 10zm-8 30l-16 40h8l3-8h14l3 8h8L56 40h-4zm2 8l5 14h-10l5-14z"/></svg>
				</div>
				<div>
					<h3><?php esc_html_e( 'Claude Desktop / Claude.ai', 'store-mcp' ); ?></h3>
					<p><?php esc_html_e( 'Custom connector with OAuth', 'store-mcp' ); ?></p>
				</div>
				<button type="button" class="button button-small store-mcp-toggle-steps"><?php esc_html_e( 'Show steps', 'store-mcp' ); ?></button>
			</div>
			<ol class="store-mcp-steps" hidden>
				<li><?php esc_html_e( 'Open Claude → Settings → Connectors.', 'store-mcp' ); ?></li>
				<li><?php esc_html_e( 'Scroll down and click "Add custom connector".', 'store-mcp' ); ?></li>
				<li>
					<?php esc_html_e( 'Paste the URL above in the Server URL field and click Add.', 'store-mcp' ); ?>
					<button type="button" class="button button-small store-mcp-copy" data-copy="<?php echo esc_attr( $endpoint ); ?>"><?php esc_html_e( 'Copy URL', 'store-mcp' ); ?></button>
				</li>
				<li><?php esc_html_e( 'Click Connect — a window opens on this site. Log in if asked and click Allow.', 'store-mcp' ); ?></li>
				<li><?php esc_html_e( 'Done. All MCP tools are now available to Claude.', 'store-mcp' ); ?></li>
			</ol>
		</div>

		<div class="store-mcp-connect-card" data-client="chatgpt">
			<div class="store-mcp-connect-head">
				<div class="store-mcp-connect-logo">
					<svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path fill="#10A37F" d="M22.28 9.82a5.87 5.87 0 0 0-.5-4.82 5.94 5.94 0 0 0-6.42-2.85A5.94 5.94 0 0 0 5.06 4.4a5.9 5.9 0 0 0-3.94 2.86 5.94 5.94 0 0 0 .73 6.95 5.87 5.87 0 0 0 .5 4.82 5.94 5.94 0 0 0 6.42 2.85 5.94 5.94 0 0 0 10.3-2.25 5.9 5.9 0 0 0 3.94-2.85 5.94 5.94 0 0 0-.73-6.96zm-8.87 12.4a4.41 4.41 0 0 1-2.83-1.03l.14-.08 4.7-2.72a.77.77 0 0 0 .39-.67v-6.63l1.99 1.15a.07.07 0 0 1 .04.05v5.5a4.44 4.44 0 0 1-4.43 4.43zm-9.53-4.07a4.41 4.41 0 0 1-.53-2.96l.14.08 4.71 2.72a.75.75 0 0 0 .77 0l5.75-3.32v2.3a.07.07 0 0 1-.03.06L10.66 19.8a4.44 4.44 0 0 1-6.05-1.62l-.02-.03zm-1.24-10.29a4.42 4.42 0 0 1 2.31-1.94v5.6a.75.75 0 0 0 .38.66l5.72 3.3-1.99 1.15a.07.07 0 0 1-.06 0L4.25 13.9a4.44 4.44 0 0 1-1.61-6.05zm16.33 3.8l-5.75-3.32 1.99-1.14a.07.07 0 0 1 .06 0L20 9.92a4.43 4.43 0 0 1-.66 8l-.01-.17V12.1a.76.76 0 0 0-.38-.65l-.01.2zm1.98-2.98l-.14-.08L16.1 5.88a.76.76 0 0 0-.78 0l-5.75 3.32V6.9a.07.07 0 0 1 .03-.06l4.71-2.72a4.44 4.44 0 0 1 6.58 4.6zm-12.45 4.1l-1.99-1.15a.07.07 0 0 1-.04-.06V6.08a4.44 4.44 0 0 1 7.27-3.41l-.14.08-4.71 2.72a.76.76 0 0 0-.38.67v6.63zm1.08-2.33L12.04 9.92l2.56 1.48v2.96L12.04 15.85 9.48 14.37z"/></svg>
				</div>
				<div>
					<h3><?php esc_html_e( 'ChatGPT', 'store-mcp' ); ?></h3>
					<p><?php esc_html_e( 'Developer Mode / Custom connector', 'store-mcp' ); ?></p>
				</div>
				<button type="button" class="button button-small store-mcp-toggle-steps"><?php esc_html_e( 'Show steps', 'store-mcp' ); ?></button>
			</div>
			<ol class="store-mcp-steps" hidden>
				<li><?php esc_html_e( 'In ChatGPT → Settings → Connectors → Advanced → enable Developer mode.', 'store-mcp' ); ?></li>
				<li><?php esc_html_e( 'Click Create, name it StoreMCP, and paste the URL above as MCP Server URL.', 'store-mcp' ); ?></li>
				<li><?php esc_html_e( 'Choose OAuth as authentication. Save. Authorize on this site when prompted.', 'store-mcp' ); ?></li>
			</ol>
		</div>

		<div class="store-mcp-connect-card" data-client="cursor">
			<div class="store-mcp-connect-head">
				<div class="store-mcp-connect-logo">
					<svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true"><path fill="#1d1d1f" d="M3 3l9 5v13L3 16V3zm9 5l9-5v13l-9 5V8z"/></svg>
				</div>
				<div>
					<h3><?php esc_html_e( 'Cursor / Windsurf / VS Code', 'store-mcp' ); ?></h3>
					<p><?php esc_html_e( 'mcp.json — remote server with OAuth', 'store-mcp' ); ?></p>
				</div>
				<button type="button" class="button button-small store-mcp-toggle-steps"><?php esc_html_e( 'Show steps', 'store-mcp' ); ?></button>
			</div>
			<ol class="store-mcp-steps" hidden>
				<li><?php esc_html_e( 'Open your mcp.json file (project or global).', 'store-mcp' ); ?></li>
				<li>
					<?php esc_html_e( 'Add the server URL:', 'store-mcp' ); ?>
					<pre class="store-mcp-code"><code>{
  "mcpServers": {
    "<?php echo esc_html( sanitize_title( $brand_name ) ); ?>": {
      "url": "<?php echo esc_html( $endpoint ); ?>"
    }
  }
}</code></pre>
				</li>
				<li><?php esc_html_e( 'Reload MCP — your client will open a browser window to authorize.', 'store-mcp' ); ?></li>
			</ol>
		</div>
	</div>

	<div class="store-mcp-grid store-mcp-grid-2">
		<div class="store-mcp-card">
			<h2><?php esc_html_e( 'Connected apps', 'store-mcp' ); ?></h2>
			<?php if ( empty( $clients ) ) : ?>
				<p class="description"><?php esc_html_e( 'No apps are connected yet. Connect Claude or ChatGPT using the steps above.', 'store-mcp' ); ?></p>
			<?php else : ?>
				<table class="widefat striped store-mcp-clients-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'App', 'store-mcp' ); ?></th>
							<th><?php esc_html_e( 'Connected', 'store-mcp' ); ?></th>
							<th><?php esc_html_e( 'Last used', 'store-mcp' ); ?></th>
							<th><?php esc_html_e( 'Active tokens', 'store-mcp' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $clients as $c ) : ?>
						<tr data-client="<?php echo esc_attr( $c->client_id ); ?>">
							<td>
								<strong><?php echo esc_html( $c->client_name ?: __( 'Unnamed client', 'store-mcp' ) ); ?></strong>
							</td>
							<td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $c->created_at ) ); ?></td>
							<td><?php echo $c->last_used_at ? esc_html( mysql2date( 'Y-m-d H:i', $c->last_used_at ) ) : '—'; ?></td>
							<td><?php echo esc_html( (string) $c->active_tokens ); ?></td>
							<td>
								<button type="button" class="button-link store-mcp-revoke-client" data-client="<?php echo esc_attr( $c->client_id ); ?>" style="color:#a00"><?php esc_html_e( 'Revoke', 'store-mcp' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="store-mcp-card">
			<h2><?php esc_html_e( 'Status', 'store-mcp' ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<tr><th><?php esc_html_e( 'Plugin enabled', 'store-mcp' ); ?></th><td><?php echo get_option( 'store_mcp_enabled', true ) ? '<span class="store-mcp-pill ok">' . esc_html__( 'On', 'store-mcp' ) . '</span>' : '<span class="store-mcp-pill warn">' . esc_html__( 'Off', 'store-mcp' ) . '</span>'; ?></td></tr>
					<tr><th><?php esc_html_e( 'WooCommerce', 'store-mcp' ); ?></th><td><?php echo $wc_active ? '<span class="store-mcp-pill ok">' . esc_html__( 'Active', 'store-mcp' ) . '</span>' : '<span class="store-mcp-pill muted">' . esc_html__( 'Not active', 'store-mcp' ) . '</span>'; ?></td></tr>
					<tr><th><?php esc_html_e( 'License', 'store-mcp' ); ?></th><td><?php echo esc_html( strtoupper( $tier ) ); ?><?php if ( ! empty( $state['expires_at'] ) ) : ?> — <?php echo esc_html( $state['expires_at'] ); ?><?php endif; ?></td></tr>
					<tr><th><?php esc_html_e( 'Tools available', 'store-mcp' ); ?></th><td><?php echo esc_html( (string) $tools_count ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Requests today', 'store-mcp' ); ?></th><td><?php echo esc_html( number_format_i18n( $stats['today'] ) ); ?> <?php if ( $stats['errors_today'] > 0 ) : ?><span class="store-mcp-pill warn"><?php echo esc_html( sprintf( __( '%d errors', 'store-mcp' ), $stats['errors_today'] ) ); ?></span><?php endif; ?></td></tr>
					<tr><th><?php esc_html_e( 'Protocol', 'store-mcp' ); ?></th><td><code><?php echo esc_html( STORE_MCP_PROTOCOL_VERSION ); ?></code></td></tr>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="store-mcp-test-connection"><?php esc_html_e( 'Test connection', 'store-mcp' ); ?></button>
				<span id="store-mcp-test-result" class="description"></span>
			</p>
		</div>
	</div>

	<?php if ( ! empty( $stats['top_tools'] ) ) : ?>
	<div class="store-mcp-card">
		<h2><?php esc_html_e( 'Top tools (last 7 days)', 'store-mcp' ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Tool', 'store-mcp' ); ?></th><th><?php esc_html_e( 'Calls', 'store-mcp' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $stats['top_tools'] as $row ) : ?>
				<tr><td><code><?php echo esc_html( $row['tool_name'] ); ?></code></td><td><?php echo esc_html( number_format_i18n( $row['calls'] ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<?php if ( ! $is_agency || empty( $wl['name'] ) ) : ?>
		<p class="store-mcp-branding"><?php
			printf(
				/* translators: %s: brand link */
				esc_html__( 'Powered by %s', 'store-mcp' ),
				'<a href="https://storemcp.io" target="_blank" rel="noopener">StoreMCP</a>'
			);
		?></p>
	<?php endif; ?>
</div>
