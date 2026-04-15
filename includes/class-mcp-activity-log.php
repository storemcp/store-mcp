<?php
/**
 * Activity log — append-only audit trail for every MCP call.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Activity_Log {

	/**
	 * Append-only audit insert. Uses $wpdb->insert() with a fully bound payload
	 * (no untrusted SQL) and is intentionally uncached: every call must persist.
	 *
	 * @param array $entry
	 */
	public function record( array $entry ): void {
		global $wpdb;

		$entry = wp_parse_args( $entry, [
			'user_id'       => 0,
			'key_id'        => '',
			'ip'            => '',
			'method'        => '',
			'tool_name'     => '',
			'params'        => null,
			'status'        => 'success',
			'http_code'     => 200,
			'duration_ms'   => 0,
			'error_message' => '',
		] );

		$params_json = '';
		if ( null !== $entry['params'] ) {
			$encoded = wp_json_encode( $this->redact( $entry['params'] ) );
			if ( is_string( $encoded ) && strlen( $encoded ) > 8000 ) {
				$encoded = substr( $encoded, 0, 8000 ) . '…';
			}
			$params_json = (string) $encoded;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin table; audit logs intentionally bypass cache.
		$wpdb->insert(
			$wpdb->prefix . 'storemcp_activity',
			[
				'created_at'    => current_time( 'mysql', true ),
				'user_id'       => (int) $entry['user_id'],
				'key_id'        => substr( (string) $entry['key_id'], 0, 32 ),
				'ip'            => substr( (string) $entry['ip'], 0, 45 ),
				'method'        => substr( (string) $entry['method'], 0, 64 ),
				'tool_name'     => substr( (string) $entry['tool_name'], 0, 128 ),
				'params'        => $params_json,
				'status'        => substr( (string) $entry['status'], 0, 16 ),
				'http_code'     => (int) $entry['http_code'],
				'duration_ms'   => (int) $entry['duration_ms'],
				'error_message' => substr( (string) $entry['error_message'], 0, 1000 ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ]
		);
	}

	public function query( array $args = [] ): array {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'page'      => 1,
			'per_page'  => 50,
			'tool'      => '',
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'search'    => '',
		] );

		$where = [ '1=1' ];
		$prep  = [];
		if ( $args['tool'] ) {
			$where[] = 'tool_name = %s';
			$prep[]  = $args['tool'];
		}
		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$prep[]  = $args['status'];
		}
		if ( $args['date_from'] ) {
			$where[] = 'created_at >= %s';
			$prep[]  = $args['date_from'] . ' 00:00:00';
		}
		if ( $args['date_to'] ) {
			$where[] = 'created_at <= %s';
			$prep[]  = $args['date_to'] . ' 23:59:59';
		}
		if ( $args['search'] ) {
			$where[] = '(tool_name LIKE %s OR error_message LIKE %s OR params LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$prep[]  = $like;
			$prep[]  = $like;
			$prep[]  = $like;
		}

		$page     = max( 1, (int) $args['page'] );
		$per_page = max( 1, min( 200, (int) $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;
		$where_sql = implode( ' AND ', $where );

		$table = $wpdb->prefix . 'storemcp_activity';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; $where_sql is composed only of literal SQL fragments with %s/%d placeholders bound through prepare().
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $prep
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$prep ) )
			: $wpdb->get_var( $count_sql )
		);

		$select_sql       = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$prep_with_paging = array_merge( $prep, [ $per_page, $offset ] );
		$rows = $wpdb->get_results( $wpdb->prepare( $select_sql, ...$prep_with_paging ) );
		// phpcs:enable

		return [
			'rows'     => $rows ?: [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		];
	}

	public function stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'storemcp_activity';
		$today = current_time( 'Y-m-d' );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; only literal SQL + bound placeholders.
		$stats = [
			'today'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", $today . ' 00:00:00' ) ),
			'last_7_days'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" ),
			'last_30_days' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" ),
			'errors_today' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status = 'error'", $today . ' 00:00:00' ) ),
			'top_tools'    => $wpdb->get_results(
				"SELECT tool_name, COUNT(*) AS calls FROM {$table} WHERE tool_name <> '' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY tool_name ORDER BY calls DESC LIMIT 10",
				ARRAY_A
			) ?: [],
		];
		// phpcs:enable

		return $stats;
	}

	public function prune( int $days ): int {
		global $wpdb;
		$days  = max( 1, $days );
		$table = $wpdb->prefix . 'storemcp_activity';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; $days bound via prepare().
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", $days ) );
	}

	public function export_csv( array $args = [] ): string {
		$args['per_page'] = 10000;
		$res              = $this->query( $args );

		$lines   = [];
		$lines[] = self::csv_line( [ 'id', 'created_at', 'ip', 'user_id', 'key_id', 'method', 'tool_name', 'status', 'http_code', 'duration_ms', 'error_message', 'params' ] );
		foreach ( $res['rows'] as $row ) {
			$lines[] = self::csv_line( [
				$row->id,
				$row->created_at,
				$row->ip,
				$row->user_id,
				$row->key_id,
				$row->method,
				$row->tool_name,
				$row->status,
				$row->http_code,
				$row->duration_ms,
				$row->error_message,
				$row->params,
			] );
		}
		return implode( "\n", $lines ) . "\n";
	}

	private static function csv_line( array $fields ): string {
		$out = [];
		foreach ( $fields as $field ) {
			$value = (string) $field;
			if ( false !== strpbrk( $value, ",\"\n\r" ) ) {
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			}
			$out[] = $value;
		}
		return implode( ',', $out );
	}

	private function redact( $value ) {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$lk = strtolower( (string) $k );
				if ( in_array( $lk, [ 'password', 'pass', 'secret', 'token', 'api_key', 'apikey' ], true ) ) {
					$out[ $k ] = '[redacted]';
				} else {
					$out[ $k ] = $this->redact( $v );
				}
			}
			return $out;
		}
		return $value;
	}
}
