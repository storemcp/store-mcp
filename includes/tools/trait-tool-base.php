<?php
/**
 * Shared trait used by every tool module.
 *
 * Provides validation, sanitisation, pagination and response-shaping helpers
 * so module classes stay focused on business logic.
 *
 * @package StoreMCP
 */

namespace StoreMCP;

defined( 'ABSPATH' ) || exit;

trait Tool_Base {

	protected static function arg( array $args, string $key, $default = null ) {
		return array_key_exists( $key, $args ) ? $args[ $key ] : $default;
	}

	protected static function require_arg( array $args, string $key ) {
		if ( ! array_key_exists( $key, $args ) || '' === $args[ $key ] || null === $args[ $key ] ) {
			throw new Tool_Exception(
				esc_html( sprintf( /* translators: %s: argument name */ __( 'Missing required argument: %s', 'store-mcp' ), $key ) ),
				Server::RPC_INVALID_PARAMS
			);
		}
		return $args[ $key ];
	}

	protected static function arg_int( array $args, string $key, ?int $default = null, ?int $min = null, ?int $max = null ): ?int {
		if ( ! array_key_exists( $key, $args ) || '' === $args[ $key ] || null === $args[ $key ] ) {
			return $default;
		}
		$value = (int) $args[ $key ];
		if ( null !== $min && $value < $min ) {
			$value = $min;
		}
		if ( null !== $max && $value > $max ) {
			$value = $max;
		}
		return $value;
	}

	protected static function arg_bool( array $args, string $key, ?bool $default = null ): ?bool {
		if ( ! array_key_exists( $key, $args ) ) {
			return $default;
		}
		return (bool) filter_var( $args[ $key ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}

	protected static function arg_string( array $args, string $key, string $default = '' ): string {
		if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] ) {
			return $default;
		}
		return sanitize_text_field( (string) $args[ $key ] );
	}

	protected static function arg_textarea( array $args, string $key, string $default = '' ): string {
		if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] ) {
			return $default;
		}
		return sanitize_textarea_field( (string) $args[ $key ] );
	}

	protected static function arg_html( array $args, string $key, string $default = '' ): string {
		if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] ) {
			return $default;
		}
		return wp_kses_post( (string) $args[ $key ] );
	}

	protected static function arg_enum( array $args, string $key, array $allowed, ?string $default = null ): ?string {
		$value = self::arg( $args, $key, $default );
		if ( null === $value || '' === $value ) {
			return $default;
		}
		$value = (string) $value;
		if ( ! in_array( $value, $allowed, true ) ) {
			throw new Tool_Exception(
				esc_html( sprintf( /* translators: 1: argument name, 2: allowed values */ __( 'Invalid value for %1$s. Allowed: %2$s', 'store-mcp' ), $key, implode( ', ', $allowed ) ) ),
				Server::RPC_INVALID_PARAMS
			);
		}
		return $value;
	}

	protected static function arg_array( array $args, string $key, array $default = [] ): array {
		$value = self::arg( $args, $key, $default );
		if ( ! is_array( $value ) ) {
			return $default;
		}
		return $value;
	}

	protected static function arg_int_list( array $args, string $key ): array {
		$value = self::arg_array( $args, $key, [] );
		return array_values( array_filter( array_map( 'intval', $value ) ) );
	}

	protected static function arg_float( array $args, string $key, ?float $default = null ): ?float {
		if ( ! array_key_exists( $key, $args ) || '' === $args[ $key ] || null === $args[ $key ] ) {
			return $default;
		}
		return (float) $args[ $key ];
	}

	protected static function pagination( array $args, int $default_per_page = 20, int $max_per_page = 100 ): array {
		return [
			'page'     => self::arg_int( $args, 'page', 1, 1 ),
			'per_page' => self::arg_int( $args, 'per_page', $default_per_page, 1, $max_per_page ),
		];
	}

	protected static function paginated( array $items, int $total, int $page, int $per_page ): array {
		return [
			'items'    => array_values( $items ),
			'total'    => $total,
			'pages'    => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'page'     => $page,
			'per_page' => $per_page,
		];
	}

	protected static function format_date( $datetime ): ?string {
		if ( empty( $datetime ) ) {
			return null;
		}
		if ( is_numeric( $datetime ) ) {
			return gmdate( 'c', (int) $datetime );
		}
		$ts = strtotime( (string) $datetime );
		return $ts ? gmdate( 'c', $ts ) : null;
	}

	protected static function ok( $data, ?array $meta = null ): array {
		$payload = [ 'success' => true, 'data' => $data ];
		if ( null !== $meta ) {
			$payload['meta'] = $meta;
		}
		return $payload;
	}

	protected static function format_post( \WP_Post $post, bool $include_content = true ): array {
		$thumb_id = (int) get_post_thumbnail_id( $post );
		return [
			'id'             => (int) $post->ID,
			'title'          => get_the_title( $post ),
			'slug'           => $post->post_name,
			'status'         => $post->post_status,
			'type'           => $post->post_type,
			'date_created'   => self::format_date( $post->post_date_gmt ),
			'date_modified'  => self::format_date( $post->post_modified_gmt ),
			'author_id'      => (int) $post->post_author,
			'parent_id'      => (int) $post->post_parent,
			'menu_order'     => (int) $post->menu_order,
			'permalink'      => get_permalink( $post ),
			'excerpt'        => has_excerpt( $post ) ? $post->post_excerpt : null,
			'featured_media' => $thumb_id ?: null,
			'template'       => get_page_template_slug( $post ) ?: null,
			'comment_status' => $post->comment_status,
			'content'        => $include_content ? $post->post_content : null,
		];
	}

	protected static function format_term( \WP_Term $term ): array {
		return [
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'slug'        => $term->slug,
			'description' => $term->description,
			'parent'      => (int) $term->parent,
			'count'       => (int) $term->count,
			'taxonomy'    => $term->taxonomy,
		];
	}

	protected static function format_user( \WP_User $user, bool $include_meta = false ): array {
		$out = [
			'id'           => (int) $user->ID,
			'username'     => $user->user_login,
			'email'        => $user->user_email,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'roles'        => array_values( $user->roles ),
			'date_created' => self::format_date( $user->user_registered ),
			'avatar_url'   => get_avatar_url( $user->ID ),
		];
		if ( $include_meta && function_exists( 'wc_get_customer_total_spent' ) ) {
			$out['orders_count'] = (int) wc_get_customer_order_count( $user->ID );
			$out['total_spent']  = (float) wc_get_customer_total_spent( $user->ID );
		}
		return $out;
	}

	protected static function format_attachment( \WP_Post $att ): array {
		$meta = wp_get_attachment_metadata( $att->ID );
		return [
			'id'           => (int) $att->ID,
			'title'        => get_the_title( $att ),
			'slug'         => $att->post_name,
			'mime_type'    => $att->post_mime_type,
			'source_url'   => wp_get_attachment_url( $att->ID ),
			'alt_text'     => (string) get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
			'caption'      => $att->post_excerpt,
			'description'  => $att->post_content,
			'date_created' => self::format_date( $att->post_date_gmt ),
			'width'        => isset( $meta['width'] ) ? (int) $meta['width'] : null,
			'height'       => isset( $meta['height'] ) ? (int) $meta['height'] : null,
			'file_size'    => file_exists( get_attached_file( $att->ID ) ) ? filesize( get_attached_file( $att->ID ) ) : null,
		];
	}

	protected static function format_product( \WC_Product $product, bool $full = false ): array {
		$base = [
			'id'                 => $product->get_id(),
			'name'               => $product->get_name(),
			'slug'               => $product->get_slug(),
			'permalink'          => $product->get_permalink(),
			'type'               => $product->get_type(),
			'status'             => $product->get_status(),
			'featured'           => $product->is_featured(),
			'sku'                => $product->get_sku(),
			'price'              => (float) $product->get_price(),
			'regular_price'      => (float) $product->get_regular_price(),
			'sale_price'         => '' !== $product->get_sale_price() ? (float) $product->get_sale_price() : null,
			'on_sale'            => $product->is_on_sale(),
			'stock_status'       => $product->get_stock_status(),
			'stock_quantity'     => $product->get_stock_quantity(),
			'manage_stock'       => $product->get_manage_stock(),
			'short_description'  => $product->get_short_description(),
			'categories'         => self::format_term_refs( $product->get_category_ids(), 'product_cat' ),
			'tags'               => self::format_term_refs( $product->get_tag_ids(), 'product_tag' ),
			'images'             => self::format_product_images( $product ),
			'date_created'       => self::format_date( $product->get_date_created() ),
			'date_modified'      => self::format_date( $product->get_date_modified() ),
		];

		if ( ! $full ) {
			return $base;
		}

		return array_merge( $base, [
			'description'         => $product->get_description(),
			'sale_price_dates_from' => self::format_date( $product->get_date_on_sale_from() ),
			'sale_price_dates_to'   => self::format_date( $product->get_date_on_sale_to() ),
			'price_html'          => $product->get_price_html(),
			'purchasable'         => $product->is_purchasable(),
			'total_sales'         => (int) $product->get_total_sales(),
			'tax_status'          => $product->get_tax_status(),
			'tax_class'           => $product->get_tax_class(),
			'backorders'          => $product->get_backorders(),
			'weight'              => $product->get_weight() !== '' ? (float) $product->get_weight() : null,
			'dimensions'          => [
				'length' => $product->get_length() !== '' ? (float) $product->get_length() : null,
				'width'  => $product->get_width() !== '' ? (float) $product->get_width() : null,
				'height' => $product->get_height() !== '' ? (float) $product->get_height() : null,
			],
			'attributes'          => self::format_product_attributes( $product ),
			'default_attributes'  => self::format_product_default_attributes( $product ),
			'variations'          => $product->is_type( 'variable' ) ? $product->get_children() : [],
			'related_ids'         => wc_get_related_products( $product->get_id() ),
			'upsell_ids'          => $product->get_upsell_ids(),
			'cross_sell_ids'      => $product->get_cross_sell_ids(),
			'meta_data'           => array_map(
				static fn( $m ) => [ 'key' => $m->key, 'value' => $m->value ],
				$product->get_meta_data()
			),
		] );
	}

	protected static function format_term_refs( array $ids, string $taxonomy ): array {
		$out = [];
		foreach ( $ids as $id ) {
			$term = get_term( (int) $id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$out[] = [ 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug ];
			}
		}
		return $out;
	}

	protected static function format_product_images( \WC_Product $product ): array {
		$ids = array_filter( array_merge( [ $product->get_image_id() ], $product->get_gallery_image_ids() ) );
		$out = [];
		foreach ( $ids as $id ) {
			$out[] = [
				'id'   => (int) $id,
				'src'  => wp_get_attachment_url( $id ),
				'name' => get_the_title( $id ),
				'alt'  => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			];
		}
		return $out;
	}

	protected static function format_product_attributes( \WC_Product $product ): array {
		$out = [];
		foreach ( $product->get_attributes() as $attr ) {
			/** @var \WC_Product_Attribute $attr */
			$out[] = [
				'id'        => $attr->get_id(),
				'name'      => wc_attribute_label( $attr->get_name() ),
				'slug'      => $attr->get_name(),
				'options'   => $attr->is_taxonomy() ? wp_list_pluck( $attr->get_terms() ?: [], 'name' ) : $attr->get_options(),
				'visible'   => $attr->get_visible(),
				'variation' => $attr->get_variation(),
			];
		}
		return $out;
	}

	protected static function format_product_default_attributes( \WC_Product $product ): array {
		if ( ! $product->is_type( 'variable' ) ) {
			return [];
		}
		$out = [];
		foreach ( (array) $product->get_default_attributes() as $name => $value ) {
			$out[] = [ 'name' => $name, 'option' => $value ];
		}
		return $out;
	}
}
