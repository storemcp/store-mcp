/* global StoreMCPAdmin, jQuery */
( function ( $ ) {
	'use strict';

	const cfg = window.StoreMCPAdmin || {};
	const i18n = cfg.i18n || {};

	function ajax( action, data ) {
		const payload = Object.assign( { action: 'store_mcp_' + action, nonce: cfg.nonce }, data || {} );
		return $.post( cfg.ajaxUrl, payload );
	}

	function flash( $el, message, isError ) {
		if ( ! $el || ! $el.length ) return;
		$el.text( message ).css( 'color', isError ? '#d63638' : '#2271b1' );
		setTimeout( () => $el.text( '' ), 4000 );
	}

	function copyToClipboard( text ) {
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text );
			return;
		}
		const $tmp = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
		try { document.execCommand( 'copy' ); } catch ( e ) { /* noop */ }
		$tmp.remove();
	}

	$( document ).on( 'click', '.store-mcp-copy', function () {
		const target = $( this ).data( 'target' );
		const text = target ? $( target ).text() : $( this ).data( 'copy' );
		if ( ! text ) return;
		copyToClipboard( String( text ) );
		const $btn = $( this );
		const original = $btn.text();
		$btn.text( i18n.copied || 'Copied' );
		setTimeout( () => $btn.text( original ), 1500 );
	} );

	// Generate API key.
	$( '#store-mcp-key-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		const label = $( this ).find( 'input[name="label"]' ).val();
		ajax( 'generate_key', { label: label } )
			.done( function ( res ) {
				if ( ! res.success ) {
					alert( ( res.data && res.data.message ) || i18n.error );
					return;
				}
				$( '#store-mcp-new-key-value' ).text( res.data.key );
				$( '#store-mcp-new-key' ).show();
				location.reload();
			} )
			.fail( () => alert( i18n.error ) );
	} );

	// Revoke API key.
	$( document ).on( 'click', '.store-mcp-revoke-key', function () {
		if ( ! confirm( i18n.confirmRevoke ) ) return;
		const id = $( this ).data( 'id' );
		ajax( 'revoke_key', { id: id } ).done( function ( res ) {
			if ( res.success ) location.reload();
			else alert( ( res.data && res.data.message ) || i18n.error );
		} );
	} );

	// Delete API key.
	$( document ).on( 'click', '.store-mcp-delete-key', function () {
		if ( ! confirm( i18n.confirmDelete ) ) return;
		const id = $( this ).data( 'id' );
		ajax( 'delete_key', { id: id } ).done( function ( res ) {
			if ( res.success ) location.reload();
			else alert( ( res.data && res.data.message ) || i18n.error );
		} );
	} );

	// Save settings.
	$( '#store-mcp-settings-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		const data = {
			enabled: $( '[name="enabled"]' ).is( ':checked' ) ? 1 : 0,
			allow_app_passwords: $( '[name="allow_app_passwords"]' ).is( ':checked' ) ? 1 : 0,
			rate_limit_free: $( '[name="rate_limit_free"]' ).val(),
			rate_limit_pro: $( '[name="rate_limit_pro"]' ).val(),
			cache_ttl_seconds: $( '[name="cache_ttl_seconds"]' ).val(),
			log_retention_days: $( '[name="log_retention_days"]' ).val(),
			cors_allowed: $( '[name="cors_allowed"]' ).val(),
			oauth_min_capability: $( '[name="oauth_min_capability"]' ).val(),
		};
		ajax( 'save_settings', data )
			.done( function ( res ) {
				flash( $( '#store-mcp-settings-result' ), ( res.data && res.data.message ) || i18n.saved, ! res.success );
			} )
			.fail( () => flash( $( '#store-mcp-settings-result' ), i18n.error, true ) );
	} );

	// Toggle module.
	$( document ).on( 'change', '.store-mcp-toggle-module', function () {
		const $cb = $( this );
		const module = $cb.data( 'module' );
		const enabled = $cb.is( ':checked' ) ? 1 : 0;
		ajax( 'toggle_module', { module: module, enabled: enabled } )
			.done( function ( res ) {
				if ( ! res.success ) {
					$cb.prop( 'checked', ! enabled );
					alert( ( res.data && res.data.message ) || i18n.error );
				}
			} )
			.fail( function () {
				$cb.prop( 'checked', ! enabled );
				alert( i18n.error );
			} );
	} );

	// Activate license.
	$( '#store-mcp-license-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		const key = $( this ).find( '[name="license_key"]' ).val();
		ajax( 'activate_license', { license_key: key } )
			.done( function ( res ) {
				alert( ( res.data && res.data.message ) || ( res.success ? i18n.saved : i18n.error ) );
				location.reload();
			} )
			.fail( () => alert( i18n.error ) );
	} );

	$( '#store-mcp-deactivate-license' ).on( 'click', function () {
		if ( ! confirm( 'Deactivate the license?' ) ) return;
		ajax( 'deactivate_license' ).done( () => location.reload() );
	} );

	$( '#store-mcp-recheck-license' ).on( 'click', function () {
		const $btn = $( this );
		$btn.prop( 'disabled', true );
		ajax( 'recheck_license' )
			.done( function ( res ) {
				flash( $( '#store-mcp-license-result' ), ( res.data && res.data.message ) || i18n.saved, ! res.success );
				setTimeout( () => location.reload(), 800 );
			} )
			.always( () => $btn.prop( 'disabled', false ) );
	} );

	// Clear log.
	$( '#store-mcp-clear-log' ).on( 'click', function () {
		if ( ! confirm( i18n.confirmClearLog ) ) return;
		ajax( 'clear_log', { days: 0 } ).done( () => location.reload() );
	} );

	// White label.
	$( '#store-mcp-white-label-form' ).on( 'submit', function ( e ) {
		e.preventDefault();
		const data = {
			name: $( '#wl-name' ).val(),
			logo: $( '#wl-logo' ).val(),
			icon: $( '#wl-icon' ).val(),
			support_url: $( '#wl-support' ).val(),
		};
		ajax( 'save_white_label', data )
			.done( function ( res ) {
				flash( $( '#store-mcp-white-label-result' ), ( res.data && res.data.message ) || i18n.saved, ! res.success );
			} )
			.fail( () => flash( $( '#store-mcp-white-label-result' ), i18n.error, true ) );
	} );

	// Toggle Connect-card steps.
	$( document ).on( 'click', '.store-mcp-toggle-steps', function () {
		const $btn = $( this );
		const $card = $btn.closest( '.store-mcp-connect-card' );
		const $steps = $card.find( 'ol.store-mcp-steps' );
		const open = ! $steps.prop( 'hidden' );
		$steps.prop( 'hidden', open );
		$card.toggleClass( 'is-open', ! open );
		$btn.text( open ? ( i18n.showSteps || 'Show steps' ) : ( i18n.hideSteps || 'Hide steps' ) );
	} );

	// Revoke connected OAuth client.
	$( document ).on( 'click', '.store-mcp-revoke-client', function () {
		if ( ! confirm( i18n.confirmRevokeClient || 'Disconnect this app? It will need to authorize again to reconnect.' ) ) return;
		const clientId = $( this ).data( 'client' );
		ajax( 'revoke_client', { client_id: clientId } ).done( function ( res ) {
			if ( res.success ) location.reload();
			else alert( ( res.data && res.data.message ) || i18n.error );
		} );
	} );

	// Test connection.
	$( '#store-mcp-test-connection' ).on( 'click', function () {
		const $btn = $( this );
		const $out = $( '#store-mcp-test-result' );
		$btn.prop( 'disabled', true );
		$out.text( '…' );
		ajax( 'test_connection' )
			.done( function ( res ) {
				if ( res.success ) {
					$out.css( 'color', '#00712f' ).text( '✓ ' + ( res.data.message || 'OK' ) );
				} else {
					$out.css( 'color', '#d63638' ).text( '✗ ' + ( res.data && res.data.message || i18n.error ) );
				}
			} )
			.fail( () => $out.css( 'color', '#d63638' ).text( '✗ ' + i18n.error ) )
			.always( () => $btn.prop( 'disabled', false ) );
	} );

} )( jQuery );
