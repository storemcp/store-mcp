<?php
/**
 * Tools view — module toggles + tool catalogue with per-tool explainers.
 *
 * @var \StoreMCP\Plugin $plugin
 * @package StoreMCP\Admin
 */

defined( 'ABSPATH' ) || exit;

$disabled_modules = (array) get_option( 'store_mcp_disabled_modules', [] );
$is_pro           = $plugin->license->is_pro();
$wc_active        = class_exists( 'WooCommerce' );

$module_catalog = [
	'class-pages-tools' => [
		'label'       => __( 'Pages', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'Full CRUD for WordPress pages: list, read, create, update and delete.', 'store-mcp' ),
		'examples'    => [
			__( '"Create a draft page titled About Us with this content…"', 'store-mcp' ),
			__( '"List all published pages modified in the last week."', 'store-mcp' ),
		],
	],
	'class-posts-tools' => [
		'label'       => __( 'Posts', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'Blog posts, categories and tags. Create, update, publish or schedule articles.', 'store-mcp' ),
		'examples'    => [
			__( '"Publish a new post in the News category tagged ai, wordpress."', 'store-mcp' ),
			__( '"List my 20 most recent posts with their status."', 'store-mcp' ),
		],
	],
	'class-media-tools' => [
		'label'       => __( 'Media', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'Media library: list attachments, upload new images/files, edit alt text and delete.', 'store-mcp' ),
		'examples'    => [
			__( '"Upload this image and set it as featured image of post #42."', 'store-mcp' ),
			__( '"List media items larger than 2 MB uploaded this year."', 'store-mcp' ),
		],
	],
	'class-menus-tools' => [
		'label'       => __( 'Menus', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'Navigation menus: read menu structure and add, move or remove menu items.', 'store-mcp' ),
		'examples'    => [
			__( '"Add a Contact link to the main navigation menu."', 'store-mcp' ),
			__( '"Remove all menu items pointing to deleted pages."', 'store-mcp' ),
		],
	],
	'class-widgets-tools' => [
		'label'       => __( 'Widgets', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'Sidebar widgets: read every sidebar and create, update or delete widget blocks.', 'store-mcp' ),
		'examples'    => [
			__( '"Add a newsletter signup block to the footer sidebar."', 'store-mcp' ),
			__( '"Remove the recent-comments widget from every sidebar."', 'store-mcp' ),
		],
	],
	'class-users-tools' => [
		'label'       => __( 'Users', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'User accounts. Free: list and read. Pro: create, update roles and delete.', 'store-mcp' ),
		'examples'    => [
			__( '"List all editors who have not logged in for 90 days."', 'store-mcp' ),
			__( '"Promote user @ana to editor role."', 'store-mcp' ),
		],
	],
	'class-site-tools' => [
		'label'       => __( 'Site', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => false,
		'description' => __( 'Site-wide operations: general info, site-health checks, cache flush and URL search & replace.', 'store-mcp' ),
		'examples'    => [
			__( '"Give me a health report of this site and flush caches."', 'store-mcp' ),
			__( '"Search and replace the old domain by the new one in all content."', 'store-mcp' ),
		],
	],
	'class-products-tools' => [
		'label'       => __( 'Products', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => true,
		'description' => __( 'WooCommerce products. Free: list and read. Pro: full CRUD and bulk update.', 'store-mcp' ),
		'examples'    => [
			__( '"Create a simple product Red T-Shirt priced at 29.90 EUR, stock 50."', 'store-mcp' ),
			__( '"Bulk-apply 20% discount to every product in the Summer category."', 'store-mcp' ),
		],
	],
	'class-categories-tools' => [
		'label'       => __( 'Product categories', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => true,
		'description' => __( 'WooCommerce product categories. Organize your catalog taxonomy.', 'store-mcp' ),
		'examples'    => [
			__( '"Create a new Featured category as child of Home."', 'store-mcp' ),
			__( '"Rename the Sale category to Outlet."', 'store-mcp' ),
		],
	],
	'class-tags-tools' => [
		'label'       => __( 'Product tags', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => true,
		'description' => __( 'WooCommerce product tags for cross-category grouping and search.', 'store-mcp' ),
		'examples'    => [
			__( '"List the 10 most-used product tags."', 'store-mcp' ),
			__( '"Delete every tag with zero products."', 'store-mcp' ),
		],
	],
	'class-orders-tools' => [
		'label'       => __( 'Orders', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => true,
		'description' => __( 'WooCommerce orders. Free: totals only. Pro: full CRUD and order notes.', 'store-mcp' ),
		'examples'    => [
			__( '"Show me today\'s orders and total revenue."', 'store-mcp' ),
			__( '"Mark order #1204 as completed and add a note to the customer."', 'store-mcp' ),
		],
	],
	'class-reports-tools' => [
		'label'       => __( 'Reports', 'store-mcp' ),
		'tier'        => 'free',
		'requires_wc' => true,
		'description' => __( 'Sales reports, top sellers and earners, low- and out-of-stock alerts.', 'store-mcp' ),
		'examples'    => [
			__( '"Give me the sales report for last month grouped by day."', 'store-mcp' ),
			__( '"List products that are out of stock right now."', 'store-mcp' ),
		],
	],
	'class-variations-tools' => [
		'label'       => __( 'Product variations', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Variable product variations: sizes, colors, stocks and prices.', 'store-mcp' ),
		'examples'    => [
			__( '"Add a Large / Black variation to product #210 priced at 39.99 EUR."', 'store-mcp' ),
			__( '"Update stock of every variation of product #55 to 20."', 'store-mcp' ),
		],
	],
	'class-attributes-tools' => [
		'label'       => __( 'Attributes', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Global attributes and their terms, used by variable products and filters.', 'store-mcp' ),
		'examples'    => [
			__( '"Create a global attribute Size with terms S, M, L, XL."', 'store-mcp' ),
			__( '"Add color Emerald to the global Color attribute."', 'store-mcp' ),
		],
	],
	'class-customers-tools' => [
		'label'       => __( 'Customers', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'WooCommerce customers with full profile, addresses and purchase history.', 'store-mcp' ),
		'examples'    => [
			__( '"Find customers who spent more than 500 EUR this year."', 'store-mcp' ),
			__( '"Update shipping address for customer #88."', 'store-mcp' ),
		],
	],
	'class-coupons-tools' => [
		'label'       => __( 'Coupons', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Discount coupons: percentages, fixed amounts, usage limits and expiry dates.', 'store-mcp' ),
		'examples'    => [
			__( '"Create a 15% coupon BLACKFRIDAY valid for the next 7 days."', 'store-mcp' ),
			__( '"Disable every coupon that has already been used 50 times."', 'store-mcp' ),
		],
	],
	'class-shipping-tools' => [
		'label'       => __( 'Shipping', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Shipping zones and their methods (flat rate, free shipping, local pickup).', 'store-mcp' ),
		'examples'    => [
			__( '"Add a new EU zone with flat rate of 7.90 EUR."', 'store-mcp' ),
			__( '"Offer free shipping on orders over 60 EUR in the Local zone."', 'store-mcp' ),
		],
	],
	'class-tax-tools' => [
		'label'       => __( 'Tax', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Tax rates and tax classes applied by country, state or postal code.', 'store-mcp' ),
		'examples'    => [
			__( '"Add a 21% VAT rate for Spain in the Standard class."', 'store-mcp' ),
			__( '"List every tax rate configured for France."', 'store-mcp' ),
		],
	],
	'class-settings-tools' => [
		'label'       => __( 'WC Settings', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'WooCommerce settings and payment gateways (Stripe, PayPal, bank transfer, COD).', 'store-mcp' ),
		'examples'    => [
			__( '"Change the store currency to USD."', 'store-mcp' ),
			__( '"Enable Stripe gateway and hide bank transfer."', 'store-mcp' ),
		],
	],
	'class-reviews-tools' => [
		'label'       => __( 'Reviews', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Moderate product reviews: list, approve, spam, edit or delete.', 'store-mcp' ),
		'examples'    => [
			__( '"Show me all unapproved reviews from the last 24 hours."', 'store-mcp' ),
			__( '"Approve every 5-star review for product #101."', 'store-mcp' ),
		],
	],
	'class-seo-tools' => [
		'label'       => __( 'SEO & meta', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => false,
		'description' => __( 'Post meta plus Yoast/RankMath SEO title, description and focus keyword.', 'store-mcp' ),
		'examples'    => [
			__( '"Write SEO title and meta description for each product in Outlet."', 'store-mcp' ),
			__( '"Set focus keyword \'wordpress plugin\' on post #301 in Yoast."', 'store-mcp' ),
		],
	],
	'class-plugins-tools' => [
		'label'       => __( 'Plugins', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => false,
		'description' => __( 'List installed plugins and activate or deactivate them remotely.', 'store-mcp' ),
		'examples'    => [
			__( '"Deactivate every plugin whose name contains \'debug\'."', 'store-mcp' ),
			__( '"Activate WPML and tell me its version."', 'store-mcp' ),
		],
	],
	'class-themes-tools' => [
		'label'       => __( 'Themes', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => false,
		'description' => __( 'List installed themes, read active theme info and switch active theme.', 'store-mcp' ),
		'examples'    => [
			__( '"Switch active theme to Twenty Twenty-Four."', 'store-mcp' ),
			__( '"Which themes are installed and which one is active?"', 'store-mcp' ),
		],
	],
	'class-webhooks-tools' => [
		'label'       => __( 'Webhooks', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'WooCommerce webhooks to push order, product or customer events to external services.', 'store-mcp' ),
		'examples'    => [
			__( '"Create a webhook that posts every completed order to my Zapier URL."', 'store-mcp' ),
			__( '"Pause all webhooks pointing to staging.example.com."', 'store-mcp' ),
		],
	],
	'class-refunds-tools' => [
		'label'       => __( 'Refunds', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => true,
		'description' => __( 'Create partial or full refunds on orders, with optional gateway restock.', 'store-mcp' ),
		'examples'    => [
			__( '"Refund 20 EUR of order #1502 because of a discount adjustment."', 'store-mcp' ),
			__( '"Fully refund order #1610 and restock its items."', 'store-mcp' ),
		],
	],
	'class-system-tools' => [
		'label'       => __( 'System', 'store-mcp' ),
		'tier'        => 'pro',
		'requires_wc' => false,
		'description' => __( 'System status and WooCommerce maintenance tools (regenerate lookup tables, recount terms, clear sessions).', 'store-mcp' ),
		'examples'    => [
			__( '"Give me the WooCommerce system status report."', 'store-mcp' ),
			__( '"Regenerate product lookup tables and clear customer sessions."', 'store-mcp' ),
		],
	],
];

/**
 * Per-tool plain-language explainers. Each entry tells the user WHY they\'d call this tool.
 * The registered-tools table will prefer these over the raw technical description when present.
 */
$tool_explainers = [
	// Pages
	'store_mcp_list_pages'            => __( 'Browse WordPress pages with filters by status, author, parent or search term.', 'store-mcp' ),
	'store_mcp_get_page'              => __( 'Read a single page including its full content and meta fields.', 'store-mcp' ),
	'store_mcp_create_page'           => __( 'Create a new page as draft, published or scheduled, with template and parent.', 'store-mcp' ),
	'store_mcp_update_page'           => __( 'Edit an existing page: title, content, status, slug, parent, template or meta.', 'store-mcp' ),
	'store_mcp_delete_page'           => __( 'Trash a page or delete it permanently with force=true.', 'store-mcp' ),
	// Posts
	'store_mcp_list_posts'            => __( 'List blog posts with filters by category, tag, author, status and date range.', 'store-mcp' ),
	'store_mcp_get_post'              => __( 'Read a post including its content, categories, tags and meta.', 'store-mcp' ),
	'store_mcp_create_post'           => __( 'Publish a new post (draft, pending, scheduled or public) with categories and tags.', 'store-mcp' ),
	'store_mcp_update_post'           => __( 'Edit a post: content, taxonomies, featured image or status.', 'store-mcp' ),
	'store_mcp_delete_post'           => __( 'Move a post to trash or delete it permanently.', 'store-mcp' ),
	'store_mcp_list_post_categories'  => __( 'List all blog post categories with hierarchy and counts.', 'store-mcp' ),
	'store_mcp_create_post_category'  => __( 'Create a new blog category with optional parent and description.', 'store-mcp' ),
	'store_mcp_list_post_tags'        => __( 'List blog tags ordered by name, count or date.', 'store-mcp' ),
	'store_mcp_create_post_tag'       => __( 'Create a new blog tag.', 'store-mcp' ),
	// Media
	'store_mcp_list_media'            => __( 'Browse the media library filtered by type, author or upload date.', 'store-mcp' ),
	'store_mcp_get_media'             => __( 'Read a media item with full metadata, sizes and source URL.', 'store-mcp' ),
	'store_mcp_upload_media'          => __( 'Upload a new image or file to the library from a URL or base64 payload.', 'store-mcp' ),
	'store_mcp_update_media'          => __( 'Edit media metadata: title, alt text, caption, description.', 'store-mcp' ),
	'store_mcp_delete_media'          => __( 'Delete a media attachment permanently.', 'store-mcp' ),
	// Menus
	'store_mcp_list_menus'            => __( 'List navigation menus with their assigned theme locations.', 'store-mcp' ),
	'store_mcp_get_menu'              => __( 'Read a menu including its items and hierarchy.', 'store-mcp' ),
	'store_mcp_create_menu_item'      => __( 'Add a new item to a menu (page, post, category, custom URL).', 'store-mcp' ),
	'store_mcp_update_menu_item'      => __( 'Edit a menu item: label, link, position or parent.', 'store-mcp' ),
	'store_mcp_delete_menu_item'      => __( 'Remove an item from a menu.', 'store-mcp' ),
	// Widgets
	'store_mcp_list_sidebars'         => __( 'List all registered widget areas (sidebars) of the theme.', 'store-mcp' ),
	'store_mcp_list_widgets'          => __( 'List widget instances inside a given sidebar.', 'store-mcp' ),
	'store_mcp_create_widget'         => __( 'Add a new widget block to a sidebar.', 'store-mcp' ),
	'store_mcp_update_widget'         => __( 'Edit the content or settings of an existing widget.', 'store-mcp' ),
	'store_mcp_delete_widget'         => __( 'Remove a widget from its sidebar.', 'store-mcp' ),
	// Users
	'store_mcp_list_users'            => __( 'List users with filters by role, search or registration date.', 'store-mcp' ),
	'store_mcp_get_user'              => __( 'Read a user profile including roles and metadata.', 'store-mcp' ),
	'store_mcp_create_user'           => __( 'Create a new user with email, role and password (Pro).', 'store-mcp' ),
	'store_mcp_update_user'           => __( 'Edit user profile, role or password (Pro).', 'store-mcp' ),
	'store_mcp_delete_user'           => __( 'Delete a user and optionally reassign their content (Pro).', 'store-mcp' ),
	// Site
	'store_mcp_get_site_info'         => __( 'Read site title, tagline, URL, language, theme and plugin summary.', 'store-mcp' ),
	'store_mcp_get_site_health'       => __( 'Run the WordPress site-health suite and get pass/fail checks.', 'store-mcp' ),
	'store_mcp_flush_cache'           => __( 'Flush object-cache, rewrite rules and transients.', 'store-mcp' ),
	'store_mcp_search_replace'        => __( 'Safely search and replace strings across posts and options (Pro).', 'store-mcp' ),
	// Products
	'store_mcp_list_products'         => __( 'Browse WooCommerce products with filters by category, status, price or stock.', 'store-mcp' ),
	'store_mcp_get_product'           => __( 'Read a product with attributes, images, variations and meta.', 'store-mcp' ),
	'store_mcp_create_product'        => __( 'Create a simple, grouped, external or variable product (Pro).', 'store-mcp' ),
	'store_mcp_update_product'        => __( 'Edit product fields, price, stock, images or taxonomies (Pro).', 'store-mcp' ),
	'store_mcp_delete_product'        => __( 'Trash or permanently delete a product (Pro).', 'store-mcp' ),
	'store_mcp_bulk_update_products'  => __( 'Apply the same change (price, stock, category) to many products at once (Pro).', 'store-mcp' ),
	// Categories
	'store_mcp_list_product_categories'  => __( 'List product categories with hierarchy and product counts.', 'store-mcp' ),
	'store_mcp_get_product_category'     => __( 'Read a product category with image and description.', 'store-mcp' ),
	'store_mcp_create_product_category'  => __( 'Create a new product category, optionally nested under a parent.', 'store-mcp' ),
	'store_mcp_update_product_category'  => __( 'Edit a product category: name, slug, parent, image or description.', 'store-mcp' ),
	'store_mcp_delete_product_category'  => __( 'Delete a product category.', 'store-mcp' ),
	// Product tags
	'store_mcp_list_product_tags'    => __( 'List product tags with usage counts.', 'store-mcp' ),
	'store_mcp_create_product_tag'   => __( 'Create a new product tag.', 'store-mcp' ),
	'store_mcp_update_product_tag'   => __( 'Edit a product tag.', 'store-mcp' ),
	'store_mcp_delete_product_tag'   => __( 'Delete a product tag.', 'store-mcp' ),
	// Orders
	'store_mcp_list_orders'          => __( 'List WooCommerce orders with filters by status, customer, date range.', 'store-mcp' ),
	'store_mcp_get_order'            => __( 'Read an order with items, billing, shipping and totals.', 'store-mcp' ),
	'store_mcp_create_order'         => __( 'Create an order programmatically (Pro).', 'store-mcp' ),
	'store_mcp_update_order'         => __( 'Change order status, notes, billing or items (Pro).', 'store-mcp' ),
	'store_mcp_delete_order'         => __( 'Trash or permanently delete an order (Pro).', 'store-mcp' ),
	'store_mcp_list_order_notes'     => __( 'Read the internal and customer-facing notes on an order (Pro).', 'store-mcp' ),
	'store_mcp_create_order_note'    => __( 'Add an internal or customer-facing note to an order (Pro).', 'store-mcp' ),
	// Reports
	'store_mcp_report_orders_totals'   => __( 'Totals by order status across the entire store.', 'store-mcp' ),
	'store_mcp_report_products_totals' => __( 'Totals by product type and stock status.', 'store-mcp' ),
	'store_mcp_report_sales'           => __( 'High-level sales report: revenue, orders, average order value.', 'store-mcp' ),
	'store_mcp_report_sales_by_date'   => __( 'Sales breakdown by day, week or month in a date range.', 'store-mcp' ),
	'store_mcp_report_top_sellers'     => __( 'Top-selling products by units sold.', 'store-mcp' ),
	'store_mcp_report_top_earners'     => __( 'Top-earning products by revenue.', 'store-mcp' ),
	'store_mcp_report_customers_totals'=> __( 'Customer totals: paying vs. non-paying, new vs. returning.', 'store-mcp' ),
	'store_mcp_report_stock_low'       => __( 'Products close to running out of stock.', 'store-mcp' ),
	'store_mcp_report_stock_out'       => __( 'Products that are currently out of stock.', 'store-mcp' ),
	// Variations
	'store_mcp_list_variations'      => __( 'List variations of a variable product with prices and stocks.', 'store-mcp' ),
	'store_mcp_get_variation'        => __( 'Read a single variation with its attribute combination.', 'store-mcp' ),
	'store_mcp_create_variation'     => __( 'Add a new variation with its attribute values and price.', 'store-mcp' ),
	'store_mcp_update_variation'     => __( 'Edit a variation: price, stock, SKU, dimensions, image.', 'store-mcp' ),
	'store_mcp_delete_variation'     => __( 'Delete a variation from its parent product.', 'store-mcp' ),
	// Attributes
	'store_mcp_list_attributes'      => __( 'List global product attributes (Size, Color, Material…).', 'store-mcp' ),
	'store_mcp_create_attribute'     => __( 'Create a new global attribute.', 'store-mcp' ),
	'store_mcp_update_attribute'     => __( 'Edit a global attribute: name, slug, order or type.', 'store-mcp' ),
	'store_mcp_delete_attribute'     => __( 'Delete a global attribute and its terms.', 'store-mcp' ),
	'store_mcp_list_attribute_terms' => __( 'List terms of an attribute (e.g. values inside Color).', 'store-mcp' ),
	'store_mcp_create_attribute_term'=> __( 'Add a new term to an attribute.', 'store-mcp' ),
	'store_mcp_update_attribute_term'=> __( 'Edit a term inside an attribute.', 'store-mcp' ),
	'store_mcp_delete_attribute_term'=> __( 'Delete a term from an attribute.', 'store-mcp' ),
	// Customers
	'store_mcp_list_customers'       => __( 'Browse WooCommerce customers with filters by role, search and registration date.', 'store-mcp' ),
	'store_mcp_get_customer'         => __( 'Read a customer profile, addresses and order stats.', 'store-mcp' ),
	'store_mcp_create_customer'      => __( 'Create a new WooCommerce customer.', 'store-mcp' ),
	'store_mcp_update_customer'      => __( 'Edit a customer profile, billing or shipping address.', 'store-mcp' ),
	'store_mcp_delete_customer'      => __( 'Delete a customer and optionally reassign their orders.', 'store-mcp' ),
	// Coupons
	'store_mcp_list_coupons'         => __( 'List discount coupons with usage count and expiry.', 'store-mcp' ),
	'store_mcp_get_coupon'           => __( 'Read a single coupon including restrictions.', 'store-mcp' ),
	'store_mcp_create_coupon'        => __( 'Create a percentage or fixed-amount coupon with limits and expiry.', 'store-mcp' ),
	'store_mcp_update_coupon'        => __( 'Edit a coupon: amount, limits, product/category scope.', 'store-mcp' ),
	'store_mcp_delete_coupon'        => __( 'Delete a coupon permanently.', 'store-mcp' ),
	// Shipping
	'store_mcp_list_shipping_zones'  => __( 'List shipping zones and their regions.', 'store-mcp' ),
	'store_mcp_get_shipping_zone'    => __( 'Read a shipping zone with its regions and methods.', 'store-mcp' ),
	'store_mcp_create_shipping_zone' => __( 'Create a new shipping zone for a set of countries or states.', 'store-mcp' ),
	'store_mcp_update_shipping_zone' => __( 'Edit a zone: name, regions, order.', 'store-mcp' ),
	'store_mcp_delete_shipping_zone' => __( 'Delete a shipping zone.', 'store-mcp' ),
	'store_mcp_list_zone_methods'    => __( 'List shipping methods attached to a zone.', 'store-mcp' ),
	'store_mcp_create_zone_method'   => __( 'Add a flat rate, free shipping or local pickup method to a zone.', 'store-mcp' ),
	// Tax
	'store_mcp_list_tax_rates'       => __( 'List configured tax rates with country, state and percentage.', 'store-mcp' ),
	'store_mcp_create_tax_rate'      => __( 'Create a new tax rate for a country/region and class.', 'store-mcp' ),
	'store_mcp_update_tax_rate'      => __( 'Edit a tax rate: percentage, scope, priority.', 'store-mcp' ),
	'store_mcp_delete_tax_rate'      => __( 'Delete a tax rate.', 'store-mcp' ),
	'store_mcp_list_tax_classes'     => __( 'List tax classes (Standard, Reduced, Zero…).', 'store-mcp' ),
	// WC Settings
	'store_mcp_get_settings'         => __( 'Read WooCommerce settings (general, products, tax, emails…).', 'store-mcp' ),
	'store_mcp_update_setting'       => __( 'Update a single WooCommerce setting by ID.', 'store-mcp' ),
	'store_mcp_list_payment_gateways'=> __( 'List enabled and disabled payment gateways.', 'store-mcp' ),
	'store_mcp_update_payment_gateway'=> __( 'Enable, disable or reconfigure a payment gateway.', 'store-mcp' ),
	// Reviews
	'store_mcp_list_reviews'         => __( 'List product reviews with filters by status and rating.', 'store-mcp' ),
	'store_mcp_get_review'           => __( 'Read a single review including author and content.', 'store-mcp' ),
	'store_mcp_create_review'        => __( 'Post a review on a product programmatically.', 'store-mcp' ),
	'store_mcp_update_review'        => __( 'Approve, unapprove, spam or edit a review.', 'store-mcp' ),
	'store_mcp_delete_review'        => __( 'Delete a review permanently.', 'store-mcp' ),
	// SEO
	'store_mcp_get_post_meta'        => __( 'Read every public meta field of a post or product.', 'store-mcp' ),
	'store_mcp_update_post_meta'     => __( 'Create or update an arbitrary meta field on a post or product.', 'store-mcp' ),
	'store_mcp_delete_post_meta'     => __( 'Delete a meta field from a post or product.', 'store-mcp' ),
	'store_mcp_get_yoast_meta'       => __( 'Read Yoast/RankMath SEO title, description and focus keyword.', 'store-mcp' ),
	'store_mcp_update_yoast_meta'    => __( 'Update SEO title, description and focus keyword for Yoast or RankMath.', 'store-mcp' ),
	'store_mcp_get_site_options'     => __( 'Read safe site options (branding, SEO defaults, analytics IDs).', 'store-mcp' ),
	'store_mcp_update_site_option'   => __( 'Update a site option from the allow-list.', 'store-mcp' ),
	// Plugins
	'store_mcp_list_plugins'         => __( 'List every installed plugin with version and active status.', 'store-mcp' ),
	'store_mcp_activate_plugin'      => __( 'Activate a plugin by its file path.', 'store-mcp' ),
	'store_mcp_deactivate_plugin'    => __( 'Deactivate a plugin.', 'store-mcp' ),
	// Themes
	'store_mcp_get_active_theme'     => __( 'Read info about the currently active theme.', 'store-mcp' ),
	'store_mcp_list_themes'          => __( 'List every installed theme.', 'store-mcp' ),
	'store_mcp_switch_theme'         => __( 'Switch the active theme by stylesheet.', 'store-mcp' ),
	// Webhooks
	'store_mcp_list_webhooks'        => __( 'List WooCommerce webhooks and their delivery URLs.', 'store-mcp' ),
	'store_mcp_get_webhook'          => __( 'Read a webhook with topic, status and secret hint.', 'store-mcp' ),
	'store_mcp_create_webhook'       => __( 'Create a new webhook for order, product or customer events.', 'store-mcp' ),
	'store_mcp_update_webhook'       => __( 'Edit a webhook: URL, topic, status.', 'store-mcp' ),
	'store_mcp_delete_webhook'       => __( 'Delete a webhook.', 'store-mcp' ),
	// Refunds
	'store_mcp_list_refunds'         => __( 'List refunds attached to an order.', 'store-mcp' ),
	'store_mcp_create_refund'        => __( 'Create a partial or full refund, optionally restocking items.', 'store-mcp' ),
	'store_mcp_delete_refund'        => __( 'Delete a refund record.', 'store-mcp' ),
	// System
	'store_mcp_system_status'        => __( 'Get the full WooCommerce system status report.', 'store-mcp' ),
	'store_mcp_list_system_tools'    => __( 'List the maintenance actions available in WC System → Tools.', 'store-mcp' ),
	'store_mcp_run_system_tool'      => __( 'Run a maintenance tool: clear sessions, regenerate lookups, recount terms.', 'store-mcp' ),
];

$tools_grouped = $plugin->tools->modules();
?>
<div class="wrap store-mcp-wrap">
	<h1><?php esc_html_e( 'Tools', 'store-mcp' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Toggle entire modules on/off and explore what each one does. Disabled modules are not loaded — useful to reduce surface area.', 'store-mcp' ); ?></p>

	<div class="store-mcp-card">
		<table class="widefat striped store-mcp-modules-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Module', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Tier', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Status', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'Tools', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'What it does & example prompts', 'store-mcp' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $module_catalog as $slug => $info ) :
				$is_disabled  = in_array( $slug, $disabled_modules, true );
				$blocked_pro  = 'pro' === $info['tier'] && ! $is_pro;
				$blocked_wc   = $info['requires_wc'] && ! $wc_active;
				$tool_count   = isset( $tools_grouped[ $slug ] ) ? count( $tools_grouped[ $slug ] ) : 0;
				?>
				<tr>
					<td><strong><?php echo esc_html( $info['label'] ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
					<td><span class="store-mcp-pill <?php echo 'pro' === $info['tier'] ? 'pro' : 'free'; ?>"><?php echo esc_html( strtoupper( $info['tier'] ) ); ?></span></td>
					<td>
						<?php if ( $blocked_wc ) : ?>
							<span class="store-mcp-pill muted"><?php esc_html_e( 'Needs WooCommerce', 'store-mcp' ); ?></span>
						<?php elseif ( $blocked_pro ) : ?>
							<span class="store-mcp-pill warn"><?php esc_html_e( 'Needs Pro', 'store-mcp' ); ?></span>
						<?php elseif ( $is_disabled ) : ?>
							<span class="store-mcp-pill muted"><?php esc_html_e( 'Disabled', 'store-mcp' ); ?></span>
						<?php else : ?>
							<span class="store-mcp-pill ok"><?php esc_html_e( 'Active', 'store-mcp' ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( (string) $tool_count ); ?></td>
					<td>
						<div class="store-mcp-module-desc"><?php echo esc_html( $info['description'] ); ?></div>
						<?php if ( ! empty( $info['examples'] ) ) : ?>
							<ul class="store-mcp-examples">
								<?php foreach ( $info['examples'] as $ex ) : ?>
									<li><?php echo esc_html( $ex ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</td>
					<td>
						<label class="store-mcp-switch">
							<input type="checkbox" class="store-mcp-toggle-module" data-module="<?php echo esc_attr( $slug ); ?>" <?php checked( ! $is_disabled ); ?> <?php disabled( $blocked_wc || $blocked_pro ); ?>>
							<span></span>
						</label>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<div class="store-mcp-card">
		<h2><?php esc_html_e( 'Registered tools', 'store-mcp' ); ?></h2>
		<p class="description"><?php
			/* translators: %d: count */
			printf( esc_html__( '%d tools currently exposed to MCP clients. Each tool below includes a plain-language explainer of what it does.', 'store-mcp' ), count( $plugin->tools->all() ) );
		?></p>
		<details open>
			<summary style="cursor:pointer"><?php esc_html_e( 'Show full catalogue', 'store-mcp' ); ?></summary>
			<table class="widefat striped" style="margin-top:1em">
				<thead><tr>
					<th style="width:28%"><?php esc_html_e( 'Tool', 'store-mcp' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'store-mcp' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $plugin->tools->all() as $name => $tool ) :
					$explainer = $tool_explainers[ $name ] ?? $tool['description'];
					?>
					<tr>
						<td><code><?php echo esc_html( $name ); ?></code></td>
						<td><?php echo esc_html( $explainer ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</details>
	</div>
</div>
