<?php
/**
 * License view.
 *
 * @var \StoreMCP\Plugin $plugin
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$key   = $plugin->license->key();
$state = $plugin->license->state();
$tier  = $plugin->license->tier();
?>
<div class="wrap store-mcp-wrap">
	<h1><?php esc_html_e( 'License', 'store-mcp' ); ?></h1>

	<div class="store-mcp-card">
		<h2><?php esc_html_e( 'Current plan', 'store-mcp' ); ?>: <span class="store-mcp-tier store-mcp-tier-<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( strtoupper( $tier ) ); ?></span></h2>

		<?php if ( ! empty( $state ) ) : ?>
			<table class="widefat striped" style="max-width:680px">
				<tbody>
					<tr><th><?php esc_html_e( 'Status', 'store-mcp' ); ?></th><td><?php echo ! empty( $state['valid'] ) ? '<span class="store-mcp-pill ok">' . esc_html__( 'Valid', 'store-mcp' ) . '</span>' : '<span class="store-mcp-pill warn">' . esc_html__( 'Inactive', 'store-mcp' ) . '</span>'; ?></td></tr>
					<?php if ( ! empty( $state['expires_at'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Expires', 'store-mcp' ); ?></th><td><?php echo esc_html( $state['expires_at'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( isset( $state['sites_used'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Sites', 'store-mcp' ); ?></th><td><?php echo esc_html( $state['sites_used'] . ' / ' . ( $state['sites_max'] ?? 1 ) ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $state['customer'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Customer', 'store-mcp' ); ?></th><td><?php echo esc_html( $state['customer'] ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $state['checked_at'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Last checked', 'store-mcp' ); ?></th><td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $state['checked_at'] ) ); ?></td></tr>
					<?php endif; ?>
					<?php if ( ! empty( $state['last_error'] ) ) : ?>
						<tr><th><?php esc_html_e( 'Last error', 'store-mcp' ); ?></th><td><?php echo esc_html( $state['last_error'] ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="store-mcp-card">
		<?php if ( '' === $key ) : ?>
			<h2><?php esc_html_e( 'Activate license', 'store-mcp' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Enter your StoreMCP license key. Get one at storemcp.io/pricing.', 'store-mcp' ); ?></p>
			<form id="store-mcp-license-form" class="store-mcp-inline-form">
				<input type="text" name="license_key" placeholder="smcp-license-xxxxxxxx" required style="min-width:340px">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Activate', 'store-mcp' ); ?></button>
			</form>
		<?php else : ?>
			<h2><?php esc_html_e( 'License key', 'store-mcp' ); ?></h2>
			<p><code><?php echo esc_html( substr( $key, 0, 6 ) . '…' . substr( $key, -4 ) ); ?></code></p>
			<p>
				<button type="button" class="button" id="store-mcp-recheck-license"><?php esc_html_e( 'Re-check now', 'store-mcp' ); ?></button>
				<button type="button" class="button" id="store-mcp-deactivate-license"><?php esc_html_e( 'Deactivate', 'store-mcp' ); ?></button>
				<span id="store-mcp-license-result" class="description"></span>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( 'free' === $tier ) : ?>
		<div class="store-mcp-card store-mcp-upgrade">
			<h2><?php esc_html_e( 'Upgrade to Pro', 'store-mcp' ); ?></h2>
			<div class="store-mcp-grid store-mcp-grid-3">
				<div>
					<h3>Free</h3>
					<p class="store-mcp-price">$0</p>
					<ul>
						<li><?php esc_html_e( 'WP core CRUD', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( 'WC read-only', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( '30 req/min', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( '1 site', 'store-mcp' ); ?></li>
					</ul>
				</div>
				<div class="featured">
					<h3>Pro</h3>
					<p class="store-mcp-price">$99<small>/year</small></p>
					<ul>
						<li><?php esc_html_e( 'Full WC control', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( 'Bulk operations', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( '120 req/min', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( 'Up to 5 sites', 'store-mcp' ); ?></li>
					</ul>
					<a href="https://storemcp.io/pricing" target="_blank" class="button button-primary"><?php esc_html_e( 'Get Pro', 'store-mcp' ); ?></a>
				</div>
				<div>
					<h3>Agency</h3>
					<p class="store-mcp-price">$299<small>/year</small></p>
					<ul>
						<li><?php esc_html_e( 'Unlimited sites', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( 'White label', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( 'Custom roles', 'store-mcp' ); ?></li>
						<li><?php esc_html_e( 'SLA support', 'store-mcp' ); ?></li>
					</ul>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
