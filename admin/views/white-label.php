<?php
/**
 * White Label view (Agency only).
 *
 * @var \StoreMCP\Plugin $plugin
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$wl = wp_parse_args( (array) get_option( 'store_mcp_white_label', [] ), [
	'name'        => '',
	'logo'        => '',
	'icon'        => '',
	'support_url' => '',
] );
?>
<div class="wrap store-mcp-wrap">
	<h1><?php esc_html_e( 'White Label', 'store-mcp' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Replace the StoreMCP brand throughout the admin panel and the MCP server identification.', 'store-mcp' ); ?></p>

	<form id="store-mcp-white-label-form">
		<div class="store-mcp-card">
			<table class="form-table">
				<tr>
					<th><label for="wl-name"><?php esc_html_e( 'Plugin name', 'store-mcp' ); ?></label></th>
					<td><input type="text" id="wl-name" name="name" value="<?php echo esc_attr( $wl['name'] ); ?>" class="regular-text" placeholder="My Brand AI"></td>
				</tr>
				<tr>
					<th><label for="wl-logo"><?php esc_html_e( 'Logo URL', 'store-mcp' ); ?></label></th>
					<td>
						<input type="url" id="wl-logo" name="logo" value="<?php echo esc_attr( $wl['logo'] ); ?>" class="regular-text" placeholder="https://example.com/logo.png">
						<p class="description"><?php esc_html_e( 'Shown on the dashboard. PNG or SVG, ~256 px wide.', 'store-mcp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="wl-icon"><?php esc_html_e( 'Menu icon URL', 'store-mcp' ); ?></label></th>
					<td>
						<input type="url" id="wl-icon" name="icon" value="<?php echo esc_attr( $wl['icon'] ); ?>" class="regular-text" placeholder="https://example.com/icon.svg">
						<p class="description"><?php esc_html_e( 'SVG recommended (16×16 or 20×20). Leave empty to use the default dashicon.', 'store-mcp' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="wl-support"><?php esc_html_e( 'Support URL', 'store-mcp' ); ?></label></th>
					<td><input type="url" id="wl-support" name="support_url" value="<?php echo esc_attr( $wl['support_url'] ); ?>" class="regular-text" placeholder="https://example.com/support"></td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save white label', 'store-mcp' ); ?></button>
			<span id="store-mcp-white-label-result" class="description"></span>
		</p>
	</form>
</div>
