<?php
/**
 * Media tools — attachments CRUD plus base64 upload (FREE).
 *
 * @package StoreMCP\Tools
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

final class Media_Tools {
	use Tool_Base;

	public static function register( Tools_Registry $registry ): void {
		$registry->register( [
			'name'        => 'store_mcp_list_media',
			'description' => 'List media library attachments.',
			'tier'        => 'free',
			'module'      => 'class-media-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'search'      => [ 'type' => 'string' ],
					'media_type'  => [ 'type' => 'string', 'enum' => [ 'image', 'video', 'audio', 'application' ] ],
					'mime_type'   => [ 'type' => 'string' ],
					'date_after'  => [ 'type' => 'string', 'description' => 'ISO 8601.' ],
					'date_before' => [ 'type' => 'string' ],
					'page'        => [ 'type' => 'integer', 'minimum' => 1, 'default' => 1 ],
					'per_page'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20 ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'list_media' ] );

		$registry->register( [
			'name'        => 'store_mcp_get_media',
			'description' => 'Get a single attachment with metadata.',
			'tier'        => 'free',
			'module'      => 'class-media-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id' => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'get_media' ] );

		$registry->register( [
			'name'        => 'store_mcp_upload_media',
			'description' => 'Upload a new media file. Provide EITHER `file` (base64-encoded binary, requires `filename`) OR `url` (remote URL to side-load).',
			'tier'        => 'free',
			'module'      => 'class-media-tools',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'file'        => [ 'type' => 'string', 'description' => 'Base64-encoded binary content (without data: prefix). Requires filename.' ],
					'url'         => [ 'type' => 'string', 'description' => 'Remote URL to download.' ],
					'filename'    => [ 'type' => 'string', 'description' => 'File name with extension (required when uploading via base64).' ],
					'title'       => [ 'type' => 'string' ],
					'alt_text'    => [ 'type' => 'string' ],
					'caption'     => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
					'parent'      => [ 'type' => 'integer' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'upload_media' ] );

		$registry->register( [
			'name'        => 'store_mcp_update_media',
			'description' => 'Update attachment metadata (title, alt, caption, description).',
			'tier'        => 'free',
			'module'      => 'class-media-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'          => [ 'type' => 'integer' ],
					'title'       => [ 'type' => 'string' ],
					'alt_text'    => [ 'type' => 'string' ],
					'caption'     => [ 'type' => 'string' ],
					'description' => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'update_media' ] );

		$registry->register( [
			'name'        => 'store_mcp_delete_media',
			'description' => 'Delete an attachment. force=true permanently removes the file.',
			'tier'        => 'free',
			'module'      => 'class-media-tools',
			'inputSchema' => [
				'type'       => 'object',
				'required'   => [ 'id' ],
				'properties' => [
					'id'    => [ 'type' => 'integer' ],
					'force' => [ 'type' => 'boolean', 'default' => true ],
				],
				'additionalProperties' => false,
			],
		], [ self::class, 'delete_media' ] );
	}

	public static function list_media( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'upload_files' );

		$pagination = self::pagination( $args );

		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $pagination['per_page'],
			'paged'          => $pagination['page'],
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		$search = self::arg_string( $args, 'search', '' );
		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$mime_type = self::arg_string( $args, 'mime_type', '' );
		if ( '' !== $mime_type ) {
			$query_args['post_mime_type'] = $mime_type;
		} elseif ( $type = self::arg_enum( $args, 'media_type', [ 'image', 'video', 'audio', 'application' ] ) ) {
			$query_args['post_mime_type'] = $type . '/*';
		}

		$after  = self::arg_string( $args, 'date_after', '' );
		$before = self::arg_string( $args, 'date_before', '' );
		if ( $after || $before ) {
			$clause = [];
			if ( $after )  $clause['after']  = $after;
			if ( $before ) $clause['before'] = $before;
			$query_args['date_query'] = [ $clause ];
		}

		$query = new \WP_Query( $query_args );
		$items = array_map( static fn( \WP_Post $a ) => self::format_attachment( $a ), $query->posts );

		return self::paginated( $items, (int) $query->found_posts, $pagination['page'], $pagination['per_page'] );
	}

	public static function get_media( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'upload_files' );

		$id  = (int) self::require_arg( $args, 'id' );
		$att = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			throw new Tool_Exception( esc_html__( 'Attachment not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}
		return self::format_attachment( $att );
	}

	public static function upload_media( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'upload_files' );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$id = 0;

		if ( ! empty( $args['url'] ) ) {
			$id = self::sideload_url( (string) $args['url'], (int) self::arg_int( $args, 'parent', 0 ) );
		} elseif ( ! empty( $args['file'] ) ) {
			$filename = self::arg_string( $args, 'filename', '' );
			if ( '' === $filename ) {
				throw new Tool_Exception( esc_html__( 'filename is required when uploading via base64', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
			}
			$id = self::sideload_base64( (string) $args['file'], $filename, (int) self::arg_int( $args, 'parent', 0 ) );
		} else {
			throw new Tool_Exception( esc_html__( 'Provide either url or file (base64).', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$update = [];
		if ( ! empty( $args['title'] ) ) {
			$update['ID']         = $id;
			$update['post_title'] = sanitize_text_field( (string) $args['title'] );
		}
		if ( isset( $args['caption'] ) ) {
			$update['ID']           = $id;
			$update['post_excerpt'] = sanitize_textarea_field( (string) $args['caption'] );
		}
		if ( isset( $args['description'] ) ) {
			$update['ID']           = $id;
			$update['post_content'] = wp_kses_post( (string) $args['description'] );
		}
		if ( ! empty( $update ) ) {
			wp_update_post( $update );
		}
		if ( isset( $args['alt_text'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
		}

		return self::format_attachment( get_post( $id ) );
	}

	public static function update_media( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'upload_files' );

		$id  = (int) self::require_arg( $args, 'id' );
		$att = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			throw new Tool_Exception( esc_html__( 'Attachment not found', 'store-mcp' ), Server::ERR_RESOURCE_NOT_FOUND );
		}

		$update = [ 'ID' => $id ];
		if ( array_key_exists( 'title', $args ) )       $update['post_title']   = self::arg_string( $args, 'title' );
		if ( array_key_exists( 'caption', $args ) )     $update['post_excerpt'] = self::arg_textarea( $args, 'caption' );
		if ( array_key_exists( 'description', $args ) ) $update['post_content'] = self::arg_html( $args, 'description' );
		wp_update_post( $update );

		if ( array_key_exists( 'alt_text', $args ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $args['alt_text'] ) );
		}

		return self::format_attachment( get_post( $id ) );
	}

	public static function delete_media( array $args, AuthContext $context ): array {
		Permissions::require_cap( $context, 'delete_posts' );

		$id    = (int) self::require_arg( $args, 'id' );
		$force = (bool) self::arg_bool( $args, 'force', true );
		$ok    = wp_delete_attachment( $id, $force );

		if ( ! $ok ) {
			throw new Tool_Exception( esc_html__( 'Could not delete attachment', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		return [ 'id' => $id, 'deleted' => true, 'force' => $force ];
	}

	private static function sideload_url( string $url, int $parent ): int {
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			throw new Tool_Exception( esc_html( $tmp->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		$file_array = [
			'name'     => wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ?: 'remote' ),
			'tmp_name' => $tmp,
		];
		$id = media_handle_sideload( $file_array, $parent );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return (int) $id;
	}

	private static function sideload_base64( string $base64, string $filename, int $parent ): int {
		if ( str_contains( $base64, ',' ) ) {
			$base64 = substr( $base64, strpos( $base64, ',' ) + 1 );
		}
		$decoded = base64_decode( $base64, true );
		if ( false === $decoded ) {
			throw new Tool_Exception( esc_html__( 'Invalid base64 payload', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$filename = sanitize_file_name( $filename );
		$check    = wp_check_filetype( $filename );
		if ( ! $check['type'] ) {
			throw new Tool_Exception( esc_html__( 'Disallowed file type', 'store-mcp' ), Server::RPC_INVALID_PARAMS );
		}

		$tmp = wp_tempnam( $filename );
		if ( ! $tmp ) {
			throw new Tool_Exception( esc_html__( 'Could not create temp file', 'store-mcp' ), Server::ERR_TOOL_FAILED );
		}
		file_put_contents( $tmp, $decoded );

		$file_array = [ 'name' => $filename, 'tmp_name' => $tmp ];
		$id = media_handle_sideload( $file_array, $parent );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			throw new Tool_Exception( esc_html( $id->get_error_message() ), Server::ERR_TOOL_FAILED );
		}
		return (int) $id;
	}
}

add_action( 'store_mcp_register_tools', [ Media_Tools::class, 'register' ] );
