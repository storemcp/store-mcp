<?php
/**
 * OAuth consent screen — rendered standalone (no wp-admin chrome).
 *
 * @var object $client
 * @var array  $params
 * @var \WP_User $user
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$wl         = (array) get_option( 'store_mcp_white_label', [] );
$is_agency  = \StoreMCP\Plugin::instance()->license->is_agency();
$brand      = ( $is_agency && ! empty( $wl['name'] ) ) ? $wl['name'] : 'StoreMCP';
$brand_logo = ( $is_agency && ! empty( $wl['logo'] ) ) ? $wl['logo'] : '';
$site_name  = get_bloginfo( 'name' );
$site_host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );

$client_name = $client->client_name ?: __( 'Unknown MCP client', 'store-mcp' );
$logo_uri    = esc_url_raw( $client->logo_uri ?? '' );
$client_uri  = esc_url_raw( $client->client_uri ?? '' );
$action_url  = \StoreMCP\OAuth::issuer() . \StoreMCP\OAuth::AUTHORIZE_PATH;
?><!doctype html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( sprintf( /* translators: 1: MCP client name, 2: site brand name */ __( 'Authorize %1$s · %2$s', 'store-mcp' ), $client_name, $brand ) ); ?></title>
<style>
	:root {
		--bg: #f5f6f8;
		--card: #ffffff;
		--border: #e1e4ea;
		--text: #1d2327;
		--muted: #596170;
		--accent: #2271b1;
		--accent-hover: #135e96;
		--danger: #b32d2e;
		--success: #00a32a;
		--radius: 12px;
	}
	* { box-sizing: border-box; }
	html, body { margin: 0; padding: 0; height: 100%; }
	body {
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, Helvetica, Arial, sans-serif;
		background: var(--bg);
		color: var(--text);
		display: flex;
		align-items: center;
		justify-content: center;
		min-height: 100vh;
		padding: 24px;
		line-height: 1.5;
	}
	.card {
		width: 100%;
		max-width: 440px;
		background: var(--card);
		border: 1px solid var(--border);
		border-radius: var(--radius);
		padding: 32px;
		box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 12px 32px -12px rgba(0,0,0,0.08);
	}
	.brand {
		display: flex;
		align-items: center;
		gap: 8px;
		font-size: 13px;
		color: var(--muted);
		margin-bottom: 28px;
	}
	.brand img { height: 20px; width: auto; }
	.header-icons {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 16px;
		margin-bottom: 20px;
	}
	.icon-box {
		width: 56px;
		height: 56px;
		border-radius: 14px;
		background: #f0f2f6;
		border: 1px solid var(--border);
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
		flex-shrink: 0;
	}
	.icon-box img { width: 100%; height: 100%; object-fit: contain; padding: 8px; }
	.icon-box .letter {
		font-size: 22px;
		font-weight: 600;
		color: var(--muted);
	}
	.arrow {
		color: var(--muted);
		font-size: 18px;
		line-height: 1;
	}
	h1 {
		font-size: 20px;
		font-weight: 600;
		margin: 0 0 8px;
		text-align: center;
		letter-spacing: -0.01em;
	}
	h1 strong { font-weight: 700; }
	.subtitle {
		text-align: center;
		color: var(--muted);
		font-size: 14px;
		margin: 0 0 24px;
	}
	.user-chip {
		background: #f0f2f6;
		border: 1px solid var(--border);
		border-radius: 999px;
		padding: 6px 12px;
		display: inline-flex;
		align-items: center;
		gap: 8px;
		font-size: 13px;
		color: var(--muted);
		margin-bottom: 20px;
	}
	.user-chip .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--success); }
	.scope-box {
		background: #fafbfc;
		border: 1px solid var(--border);
		border-radius: 8px;
		padding: 16px;
		margin-bottom: 24px;
	}
	.scope-box h2 {
		font-size: 12px;
		text-transform: uppercase;
		color: var(--muted);
		margin: 0 0 10px;
		font-weight: 600;
		letter-spacing: 0.04em;
	}
	.scope-box ul {
		list-style: none;
		padding: 0;
		margin: 0;
		font-size: 14px;
	}
	.scope-box li {
		padding: 4px 0;
		display: flex;
		align-items: start;
		gap: 8px;
	}
	.scope-box li::before {
		content: "✓";
		color: var(--success);
		font-weight: 700;
		margin-top: 1px;
	}
	.buttons {
		display: flex;
		gap: 8px;
		margin-top: 8px;
	}
	button {
		flex: 1;
		padding: 11px 16px;
		border-radius: 8px;
		font-size: 14px;
		font-weight: 600;
		cursor: pointer;
		border: 1px solid transparent;
		font-family: inherit;
		transition: background 0.12s ease, border-color 0.12s ease;
	}
	.btn-allow {
		background: var(--accent);
		color: #fff;
	}
	.btn-allow:hover { background: var(--accent-hover); }
	.btn-deny {
		background: #fff;
		color: var(--text);
		border-color: var(--border);
	}
	.btn-deny:hover { background: #f5f6f8; }
	.footer {
		margin-top: 20px;
		text-align: center;
		font-size: 12px;
		color: var(--muted);
	}
	.footer code {
		background: #f0f2f6;
		padding: 2px 6px;
		border-radius: 4px;
		font-size: 11px;
	}
</style>
</head>
<body>
<main class="card">
	<div class="brand">
		<?php if ( $brand_logo ) : ?>
			<img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand ); ?>">
		<?php else : ?>
			<span><?php echo esc_html( $brand ); ?></span>
		<?php endif; ?>
	</div>

	<div class="header-icons">
		<div class="icon-box" title="<?php echo esc_attr( $client_name ); ?>">
			<?php if ( $logo_uri ) : ?>
				<img src="<?php echo esc_url( $logo_uri ); ?>" alt="">
			<?php else : ?>
				<span class="letter"><?php echo esc_html( mb_strtoupper( mb_substr( $client_name, 0, 1 ) ) ); ?></span>
			<?php endif; ?>
		</div>
		<span class="arrow">→</span>
		<div class="icon-box" title="<?php echo esc_attr( $site_name ); ?>">
			<span class="letter"><?php echo esc_html( mb_strtoupper( mb_substr( $site_name ?: 'S', 0, 1 ) ) ); ?></span>
		</div>
	</div>

	<h1><?php
	printf(
		/* translators: 1: client name 2: site name */
		esc_html__( 'Allow %1$s to access %2$s?', 'store-mcp' ),
		'<strong>' . esc_html( $client_name ) . '</strong>',
		'<strong>' . esc_html( $site_name ) . '</strong>'
	);
	?></h1>
	<p class="subtitle">
		<?php if ( $client_uri ) : ?>
			<a href="<?php echo esc_url( $client_uri ); ?>" target="_blank" rel="noopener noreferrer" style="color:var(--muted)"><?php echo esc_html( (string) wp_parse_url( $client_uri, PHP_URL_HOST ) ); ?></a> ·
		<?php endif; ?>
		<?php echo esc_html( $site_host ); ?>
	</p>

	<div style="text-align:center;">
		<span class="user-chip">
			<span class="dot"></span>
			<?php echo esc_html( sprintf( /* translators: %s: WordPress user login */ __( 'Signed in as %s', 'store-mcp' ), $user->user_login ) ); ?>
		</span>
	</div>

	<div class="scope-box">
		<h2><?php esc_html_e( 'This will allow the app to', 'store-mcp' ); ?></h2>
		<ul>
			<li><?php esc_html_e( 'Read and manage content, products and settings via MCP tools', 'store-mcp' ); ?></li>
			<li><?php echo esc_html( sprintf( /* translators: %s: WordPress user login */ __( 'Act on behalf of %s, limited to their existing permissions', 'store-mcp' ), $user->user_login ) ); ?></li>
			<li><?php esc_html_e( 'Be revoked any time from Settings → Connected apps', 'store-mcp' ); ?></li>
		</ul>
	</div>

	<form method="post" action="<?php echo esc_url( $action_url ); ?>">
		<?php wp_nonce_field( 'store_mcp_oauth_consent' ); ?>
		<?php foreach ( $params as $key => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php endforeach; ?>
		<div class="buttons">
			<button type="submit" name="decision" value="deny" class="btn-deny"><?php esc_html_e( 'Deny', 'store-mcp' ); ?></button>
			<button type="submit" name="decision" value="allow" class="btn-allow"><?php esc_html_e( 'Allow', 'store-mcp' ); ?></button>
		</div>
	</form>

	<p class="footer">
		<?php esc_html_e( 'Only allow apps you trust.', 'store-mcp' ); ?>
	</p>
</main>
</body>
</html>
