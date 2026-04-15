<?php
/**
 * Activity log view.
 *
 * @var \StoreMCP\Plugin $plugin
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$args = [
	'page'      => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
	'per_page'  => 50,
	'tool'      => isset( $_GET['tool'] ) ? sanitize_text_field( wp_unslash( $_GET['tool'] ) ) : '',
	'status'    => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
	'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
	'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
	'search'    => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
];

$result = $plugin->activity_log->query( $args );

$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=store_mcp_export_log&' . http_build_query( array_diff_key( $args, [ 'page' => 1, 'per_page' => 1 ] ) ) ), 'store_mcp_export_log' );

$base_url = admin_url( 'admin.php?page=store-mcp-log' );
?>
<div class="wrap store-mcp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Activity log', 'store-mcp' ); ?></h1>
	<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'store-mcp' ); ?></a>
	<button type="button" class="page-title-action" id="store-mcp-clear-log"><?php esc_html_e( 'Clear log', 'store-mcp' ); ?></button>

	<form method="get" class="store-mcp-card store-mcp-filters">
		<input type="hidden" name="page" value="store-mcp-log">
		<input type="text" name="tool" placeholder="<?php esc_attr_e( 'Tool', 'store-mcp' ); ?>" value="<?php echo esc_attr( $args['tool'] ); ?>">
		<select name="status">
			<option value=""><?php esc_html_e( 'Any status', 'store-mcp' ); ?></option>
			<option value="success" <?php selected( $args['status'], 'success' ); ?>><?php esc_html_e( 'Success', 'store-mcp' ); ?></option>
			<option value="error" <?php selected( $args['status'], 'error' ); ?>><?php esc_html_e( 'Error', 'store-mcp' ); ?></option>
		</select>
		<input type="date" name="date_from" value="<?php echo esc_attr( $args['date_from'] ); ?>">
		<input type="date" name="date_to" value="<?php echo esc_attr( $args['date_to'] ); ?>">
		<input type="search" name="search" placeholder="<?php esc_attr_e( 'Search params/error', 'store-mcp' ); ?>" value="<?php echo esc_attr( $args['search'] ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'store-mcp' ); ?></button>
		<a href="<?php echo esc_url( $base_url ); ?>" class="button-link"><?php esc_html_e( 'Reset', 'store-mcp' ); ?></a>
	</form>

	<table class="widefat striped store-mcp-log-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'store-mcp' ); ?></th>
				<th><?php esc_html_e( 'Method / tool', 'store-mcp' ); ?></th>
				<th><?php esc_html_e( 'Status', 'store-mcp' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'store-mcp' ); ?></th>
				<th><?php esc_html_e( 'Key / user', 'store-mcp' ); ?></th>
				<th><?php esc_html_e( 'IP', 'store-mcp' ); ?></th>
				<th><?php esc_html_e( 'Details', 'store-mcp' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $result['rows'] ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No entries match the filter.', 'store-mcp' ); ?></td></tr>
		<?php else : foreach ( $result['rows'] as $row ) :
			$user = $row->user_id ? get_userdata( (int) $row->user_id ) : null;
		?>
			<tr>
				<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $row->created_at ) ); ?></td>
				<td><code><?php echo esc_html( $row->method ); ?></code><?php if ( $row->tool_name ) : ?><br><small><?php echo esc_html( $row->tool_name ); ?></small><?php endif; ?></td>
				<td><?php echo 'success' === $row->status ? '<span class="store-mcp-pill ok">' . esc_html( $row->status ) . '</span>' : '<span class="store-mcp-pill warn">' . esc_html( $row->status ) . '</span>'; ?></td>
				<td><?php echo esc_html( (string) $row->duration_ms ); ?> ms</td>
				<td><?php echo esc_html( $row->key_id ?: '—' ); ?><?php if ( $user ) : ?><br><small><?php echo esc_html( $user->user_login ); ?></small><?php endif; ?></td>
				<td><?php echo esc_html( $row->ip ?: '—' ); ?></td>
				<td>
					<?php if ( $row->error_message ) : ?>
						<details><summary><?php esc_html_e( 'Error', 'store-mcp' ); ?></summary><pre><?php echo esc_html( $row->error_message ); ?></pre></details>
					<?php endif; ?>
					<?php if ( $row->params ) : ?>
						<details><summary><?php esc_html_e( 'Params', 'store-mcp' ); ?></summary><pre><?php echo esc_html( $row->params ); ?></pre></details>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody>
	</table>

	<?php if ( $result['pages'] > 1 ) :
		$current = $args['page'];
		$paginate = paginate_links( [
			'base'      => add_query_arg( 'paged', '%#%', $base_url . '&' . http_build_query( $args ) ),
			'format'    => '',
			'current'   => $current,
			'total'     => $result['pages'],
			'prev_text' => '‹',
			'next_text' => '›',
		] );
		?>
		<div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post( $paginate ); ?></div></div>
	<?php endif; ?>
</div>
