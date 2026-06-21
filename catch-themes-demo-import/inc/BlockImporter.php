<?php
/**
 * Demo importer for Block (FSE) themes.
 *
 * Classic themes store their design in the Customizer (theme_mods) and widgets.
 * Block themes don't: their design lives in theme files (theme.json, /templates,
 * /parts, /patterns) plus a handful of database CPTs that hold Site-Editor
 * customizations:
 *
 *   - wp_global_styles  -> the Customizer replacement (colors, typography, spacing)
 *   - wp_navigation     -> menus, referenced by the Navigation block's "ref"
 *   - wp_template        -> customized full templates
 *   - wp_template_part   -> customized header/footer/etc.
 *   - wp_block           -> synced patterns, referenced by "ref"
 *
 * This class runs *in place of* the classic widget/customizer steps when a block
 * theme is active. It is hooked onto the same import lifecycle the classic flow
 * uses, so it inherits the AJAX chunking, resume transient, downloader and logger.
 *
 * Status: scaffold. Global Styles + Navigation + the block-ID remap pass are
 * implemented; templates/parts, synced patterns and reading settings are stubbed
 * with TODOs for the export-side data format to be finalised.
 *
 * @package ctdi
 */

namespace CTDI;

// Exit if accessed directly.
if (! defined('ABSPATH')) exit;

class BlockImporter
{
	/**
	 * Register the block-theme import steps, but only when a block theme is active.
	 * Hook this on `after_setup_theme` so wp_is_block_theme() is reliable.
	 */
	public static function maybe_register()
	{
		if (! function_exists('wp_is_block_theme') || ! wp_is_block_theme()) {
			return;
		}

		// Runs at the very end of the import. This hook fires in both the browser
		// (AJAX) and WP-CLI flows, and the content importer's ID maps are restored by
		// this point, so the block-ID remap pass has what it needs.
		add_action('cp-ctdi/after_all_import_execution', array(__CLASS__, 'run'), 20, 3); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the plugin's established public hook prefix.
	}

	/**
	 * Orchestrate the block-theme import.
	 *
	 * @param array $selected_import_files Resolved file paths (content, ...).
	 * @param array $import_files          All predefined demos from cp-ctdi/import_files.
	 * @param int   $selected_index        Index of the selected demo.
	 */
	public static function run($selected_import_files, $import_files, $selected_index)
	{
		$info = isset($import_files[$selected_index]) ? $import_files[$selected_index] : array();

		self::log('--- Block theme (FSE) import started ---');

		// 1. Navigation first, so the remap pass knows the new wp_navigation IDs.
		$nav_map = self::import_navigation($info);

		// 2. Global Styles (the Customizer replacement).
		self::import_global_styles($info);

		// 3. Customized templates / template parts (returns the new post IDs).
		$template_ids = self::import_templates($info);

		// 4. Rewrite IDs embedded in block markup across the imported content
		//    (posts/pages from the WXR import, navigation, and templates/parts),
		//    and replace the demo's source site URL with this site's home URL so
		//    menu links etc. point locally instead of back at the demo.
		$source_url = self::source_base_url($selected_import_files);
		self::remap_block_ids($nav_map, $template_ids, $source_url);

		// 5. Front page / posts page / site logo.
		self::import_reading_settings($info);

		// 6. WooCommerce Shop / Cart / Checkout / My Account page assignment.
		self::import_woocommerce_pages($info);

		// 7. Make the WooCommerce store browsable (coming-soon off + product lookup).
		self::prepare_woocommerce_store();

		self::log('--- Block theme (FSE) import finished ---');
	}


	/* --------------------------------------------------------------------- *
	 *  Navigation (wp_navigation)
	 * --------------------------------------------------------------------- */

	/**
	 * Import navigation menus as wp_navigation posts.
	 *
	 * Expected file: a JSON array of objects, each:
	 *   { "id": <old id>, "title": "Primary", "content": "<!-- wp:navigation-link ... -->" }
	 * (this is essentially the /wp/v2/navigation REST response trimmed down).
	 *
	 * @param array $info Selected demo's file info.
	 * @return array Map of old wp_navigation ID => new wp_navigation ID.
	 */
	protected static function import_navigation($info)
	{
		$map  = array();
		$file = self::download($info, 'import_navigation_file_url', 'demo-navigation_');

		if ('' === $file) {
			return $map;
		}

		$items = json_decode(self::read($file), true);

		if (empty($items) || ! is_array($items)) {
			return $map;
		}

		foreach ($items as $item) {
			if (empty($item['content'])) {
				continue;
			}

			$new_id = self::save_post_safely(
				array(
					'post_type'    => 'wp_navigation',
					'post_status'  => 'publish',
					'post_title'   => isset($item['title']) ? sanitize_text_field($item['title']) : __('Navigation', 'catch-themes-demo-import'),
					// wp_insert_post() unslashes, so slash to preserve block markup escapes (e.g. -- for "--" inside HTML comments).
					'post_content' => wp_slash($item['content']),
				)
			);

			if (! is_wp_error($new_id) && ! empty($item['id'])) {
				$map[(int) $item['id']] = (int) $new_id;
			}
		}

		self::log(sprintf('Imported %d navigation menu(s).', count($map)));

		return $map;
	}


	/* --------------------------------------------------------------------- *
	 *  Global Styles (wp_global_styles)
	 * --------------------------------------------------------------------- */

	/**
	 * Import Global Styles into the active theme's single wp_global_styles post.
	 *
	 * Expected file: the theme.json "user" data, i.e.
	 *   { "version": 2, "settings": { ... }, "styles": { ... } }
	 *
	 * @param array $info Selected demo's file info.
	 */
	protected static function import_global_styles($info)
	{
		if (! class_exists('\WP_Theme_JSON_Resolver') || ! method_exists('\WP_Theme_JSON_Resolver', 'get_user_global_styles_post_id')) {
			return;
		}

		$file = self::download($info, 'import_global_styles_file_url', 'demo-global-styles_');

		if ('' === $file) {
			return;
		}

		$data = json_decode(self::read($file), true);

		if (empty($data) || ! is_array($data)) {
			return;
		}

		$content = wp_json_encode(
			array(
				'version'  => isset($data['version']) ? $data['version'] : 2,
				'isGlobalStylesUserThemeJSON' => true,
				'settings' => isset($data['settings']) ? $data['settings'] : array(),
				'styles'   => isset($data['styles']) ? $data['styles'] : array(),
			)
		);

		// Remap attachment URLs referenced inside the styles (e.g. background images,
		// gradients pointing at uploaded files) using the content importer's URL map.
		foreach (self::content_url_map() as $old_url => $new_url) {
			if ('' !== (string) $old_url) {
				$content = str_replace(wp_json_encode($old_url), wp_json_encode($new_url), $content);
			}
		}

		// The active theme's user global styles live in exactly one post; this
		// helper creates it if it doesn't exist yet.
		$post_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

		if ($post_id) {
			self::save_post_safely(
				array(
					'ID'           => $post_id,
					'post_type'    => 'wp_global_styles',
					'post_content' => wp_slash($content),
				)
			);

			if (function_exists('wp_clean_theme_json_cache')) {
				wp_clean_theme_json_cache();
			}

			self::log('Imported Global Styles.');
		}
	}


	/* --------------------------------------------------------------------- *
	 *  Templates / Template parts (Tier 2 - stub)
	 * --------------------------------------------------------------------- */

	/**
	 * Import customized wp_template / wp_template_part posts.
	 *
	 * Only needed when the demo customizes templates in the Site Editor beyond the
	 * theme's shipped /templates and /parts files. Each imported post MUST be tagged
	 * with the `wp_theme` taxonomy = active stylesheet, and `wp_template_part` posts
	 * also need the `wp_template_part_area` term (header/footer/uncategorized), or
	 * they will not override the file versions.
	 *
	 * Upsert by (post_name + theme) so re-imports don't create duplicates.
	 *
	 * @param array $info Selected demo's file info.
	 */
	protected static function import_templates($info)
	{
		$ids  = array();
		$file = self::download($info, 'import_templates_file_url', 'demo-templates_');

		if ('' === $file) {
			return $ids;
		}

		$items = json_decode(self::read($file), true);

		if (empty($items) || ! is_array($items)) {
			return $ids;
		}

		$theme = get_stylesheet();

		foreach ($items as $item) {
			$type = isset($item['type']) ? $item['type'] : 'wp_template';

			if (! in_array($type, array('wp_template', 'wp_template_part'), true) || empty($item['slug'])) {
				continue;
			}

			$slug    = sanitize_title($item['slug']);
			$postarr = array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => isset($item['title']) ? sanitize_text_field($item['title']) : $slug,
				// wp_insert_post() unslashes, so slash to preserve block markup escapes (e.g. -- for "--" inside HTML comments).
				'post_content' => isset($item['content']) ? wp_slash($item['content']) : '',
			);

			// Upsert by (slug + theme) so re-imports don't pile up duplicates.
			$existing = self::find_template_post($type, $slug, $theme);

			if ($existing) {
				$postarr['ID'] = $existing;
			}

			$id = self::save_post_safely($postarr);

			if (is_wp_error($id) || ! $id) {
				continue;
			}

			// Tag with the active theme, or it won't override the file version.
			wp_set_object_terms($id, $theme, 'wp_theme');

			// Template parts also need their area (header / footer / uncategorized).
			if ('wp_template_part' === $type) {
				$area = isset($item['area']) ? sanitize_title($item['area']) : 'uncategorized';
				wp_set_object_terms($id, $area, 'wp_template_part_area');
			}

			$ids[] = (int) $id;
		}

		self::log(sprintf('Imported %d template/part(s).', count($ids)));

		return $ids;
	}

	/**
	 * Find an existing wp_template / wp_template_part for a slug within the theme.
	 *
	 * @param string $type  wp_template | wp_template_part.
	 * @param string $slug  Template slug.
	 * @param string $theme Stylesheet slug.
	 * @return int Post ID, or 0 if none.
	 */
	protected static function find_template_post($type, $slug, $theme)
	{
		$posts = get_posts(
			array(
				'post_type'      => $type,
				'name'           => $slug,
				'post_status'    => array('publish', 'draft', 'auto-draft'),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one-off lookup during import, not a front-end query.
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $theme,
					),
				),
			)
		);

		return ! empty($posts) ? (int) $posts[0] : 0;
	}


	/* --------------------------------------------------------------------- *
	 *  Block ID remapping
	 * --------------------------------------------------------------------- */

	/**
	 * Rewrite DB IDs embedded in block markup across the imported content.
	 *
	 * Block attributes carry IDs that all change on import:
	 *   wp:image/cover/...  {"id":123}   -> attachment (post) id
	 *   wp:navigation       {"ref":456}  -> wp_navigation id
	 *   wp:block            {"ref":789}  -> wp_block (synced pattern) id
	 *
	 * The WXR importer remaps attachment *URLs* but not these embedded *IDs*, so we
	 * do a dedicated pass, limited to the posts we just imported.
	 *
	 * @param array $nav_map Old => new wp_navigation IDs.
	 */
	protected static function remap_block_ids($nav_map, $extra_ids = array(), $source_url = '')
	{
		$post_map = self::content_post_map(); // old => new for posts AND attachments.

		// Limit the rewrite to content we imported (never touch pre-existing posts):
		// WXR posts/attachments, the navigation we created, and templates/parts.
		$imported_ids = array_values($post_map);
		$imported_ids = array_merge($imported_ids, array_values($nav_map), (array) $extra_ids);
		$imported_ids = array_unique(array_filter(array_map('intval', $imported_ids)));

		// Demo source URL -> this site's home URL (menu links, hrefs, etc.).
		$dest_url = untrailingslashit(home_url());
		$do_url   = ('' !== $source_url && $source_url !== $dest_url);

		$count = 0;

		foreach ($imported_ids as $pid) {
			$post = get_post($pid);

			if (! $post || '' === (string) $post->post_content) {
				continue;
			}

			$has_blocks = false !== strpos($post->post_content, '<!-- wp:');
			$has_url    = $do_url && false !== strpos($post->post_content, $source_url);

			if (! $has_blocks && ! $has_url) {
				continue;
			}

			$changed = false;
			$content = $post->post_content;

			if ($has_blocks) {
				$blocks = self::walk_blocks(parse_blocks($content), $post_map, $nav_map, $changed);

				if ($changed) {
					$content = serialize_blocks($blocks);

					// Secondary touch-up: the "wp-image-<id>" class lives in innerHTML,
					// not in the block comment, so rewrite it by string with a boundary.
					foreach ($post_map as $old => $new) {
						$content = preg_replace('/\bwp-image-' . (int) $old . '\b/', 'wp-image-' . (int) $new, $content);
					}
				}
			}

			// Replace the demo's absolute source URL so links resolve on this site.
			if ($do_url && false !== strpos($content, $source_url)) {
				$content = str_replace($source_url, $dest_url, $content);
				$changed = true;
			}

			if ($changed) {
				wp_update_post(array('ID' => $pid, 'post_content' => wp_slash($content)));
				$count++;
			}
		}

		self::log(sprintf('Remapped block IDs / URLs in %d imported item(s).', $count));
	}

	/**
	 * Determine the demo's source site URL, so absolute links baked into the demo
	 * (menus, buttons, etc.) can be rewritten to this site's home URL on import.
	 *
	 * Resolves, in order: the `cp-ctdi/demo_source_url` filter, then the WXR's
	 * <wp:base_blog_url> (read from the already-downloaded content file head).
	 *
	 * @param array $files Downloaded import files (expects a 'content' path).
	 * @return string Untrailingslashed source URL, or '' if undeterminable.
	 */
	protected static function source_base_url($files)
	{
		$url = (string) apply_filters('cp-ctdi/demo_source_url', '', $files);

		if ('' === $url && ! empty($files['content']) && is_readable($files['content'])) {
			// base_blog_url sits in the WXR header, so only the file head is needed.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a bounded head of a local temp file.
			$head = file_get_contents($files['content'], false, null, 0, 16384);

			if (false !== $head && preg_match('#<wp:base_blog_url>\s*(.*?)\s*</wp:base_blog_url>#', $head, $m)) {
				$url = $m[1];
			}
		}

		return untrailingslashit(trim($url));
	}

	/**
	 * Recursively rewrite id/ref attributes in a parsed block tree.
	 *
	 * @param array $blocks   Parsed blocks.
	 * @param array $post_map old => new post/attachment IDs.
	 * @param array $nav_map  old => new wp_navigation IDs.
	 * @param bool  $changed  Set true (by ref) if anything was rewritten.
	 * @return array Updated blocks.
	 */
	protected static function walk_blocks($blocks, $post_map, $nav_map, &$changed)
	{
		$media_blocks = array('core/image', 'core/cover', 'core/media-text', 'core/video', 'core/audio');

		foreach ($blocks as &$block) {
			$name = isset($block['blockName']) ? $block['blockName'] : '';

			if (! empty($block['attrs']) || isset($block['attrs'])) {
				// Media blocks -> attachment id.
				if (in_array($name, $media_blocks, true) && isset($block['attrs']['id']) && isset($post_map[(int) $block['attrs']['id']])) {
					$block['attrs']['id'] = (int) $post_map[(int) $block['attrs']['id']];
					$changed              = true;
				}

				// Navigation block -> wp_navigation ref.
				if ('core/navigation' === $name && isset($block['attrs']['ref']) && isset($nav_map[(int) $block['attrs']['ref']])) {
					$block['attrs']['ref'] = (int) $nav_map[(int) $block['attrs']['ref']];
					$changed               = true;
				}

				// Synced pattern / reusable block -> wp_block ref (covered by post_map).
				if ('core/block' === $name && isset($block['attrs']['ref']) && isset($post_map[(int) $block['attrs']['ref']])) {
					$block['attrs']['ref'] = (int) $post_map[(int) $block['attrs']['ref']];
					$changed               = true;
				}
			}

			if (! empty($block['innerBlocks'])) {
				$block['innerBlocks'] = self::walk_blocks($block['innerBlocks'], $post_map, $nav_map, $changed);
			}
		}
		unset($block);

		return $blocks;
	}


	/* --------------------------------------------------------------------- *
	 *  Reading settings (front page / posts page / logo) - stub
	 * --------------------------------------------------------------------- */

	/**
	 * Apply the demo's front-page / posts-page / site-logo settings.
	 *
	 * The demo declares which imported pages are the front and blog pages (by slug,
	 * resolved to the new IDs after import).
	 *
	 * @param array $info Selected demo's file info.
	 */
	protected static function import_reading_settings($info)
	{
		if (! empty($info['front_page_slug'])) {
			$front = get_page_by_path(sanitize_title($info['front_page_slug']));
			if ($front) {
				update_option('show_on_front', 'page');
				update_option('page_on_front', $front->ID);
			}
		}

		if (! empty($info['posts_page_slug'])) {
			$blog = get_page_by_path(sanitize_title($info['posts_page_slug']));
			if ($blog) {
				update_option('page_for_posts', $blog->ID);
			}
		}

		// TODO: site_logo option + remap to the new attachment id (block themes use
		// the `site_logo` option, not the classic custom_logo theme_mod).
	}


	/* --------------------------------------------------------------------- *
	 *  WooCommerce page assignment
	 * --------------------------------------------------------------------- */

	/**
	 * Point WooCommerce's Shop / Cart / Checkout / My Account pages at the demo's
	 * imported pages.
	 *
	 * WooCommerce auto-creates these pages on activation, so its page options keep
	 * pointing at those defaults while the demo's own versions (often imported under
	 * a "-2" / custom slug because the default slug was taken) sit unused. The demo
	 * declares each page by slug in its `cp-ctdi/import_files` entry, e.g.
	 *   'woocommerce_shop_page_slug' => 'shop-now',
	 * and we resolve the slug to the imported page ID and update the option.
	 *
	 * @param array $info Selected demo's file info.
	 */
	protected static function import_woocommerce_pages($info)
	{
		if (! class_exists('WooCommerce')) {
			return;
		}

		$pages = array(
			'woocommerce_shop_page_slug'      => 'woocommerce_shop_page_id',
			'woocommerce_cart_page_slug'      => 'woocommerce_cart_page_id',
			'woocommerce_checkout_page_slug'  => 'woocommerce_checkout_page_id',
			'woocommerce_myaccount_page_slug' => 'woocommerce_myaccount_page_id',
		);

		$assigned = 0;

		foreach ($pages as $slug_key => $option) {
			if (empty($info[$slug_key])) {
				continue;
			}

			$page = get_page_by_path(sanitize_title($info[$slug_key]));

			if ($page) {
				update_option($option, $page->ID);
				$assigned++;
			}
		}

		if ($assigned > 0) {
			// The shop base slug may have changed, so refresh permalinks.
			flush_rewrite_rules(false);
			self::log(sprintf('Assigned %d WooCommerce page(s) from the demo.', $assigned));
		}
	}

	/**
	 * Make the imported WooCommerce store browsable.
	 *
	 * Two things leave a freshly demo-imported store looking empty:
	 *  - Recent WooCommerce versions ship "Coming soon" mode enabled, hiding the
	 *    storefront behind a placeholder.
	 *  - The WXR importer inserts products with wp_insert_post(), bypassing
	 *    WooCommerce's data store, so wp_wc_product_meta_lookup (which the Shop /
	 *    Product Collection block queries) is never populated and the shop reads empty.
	 *
	 * @return void
	 */
	protected static function prepare_woocommerce_store()
	{
		if (! class_exists('WooCommerce')) {
			return;
		}

		// Take the store out of "Coming soon" mode so the demo is browsable.
		if (apply_filters('cp-ctdi/woocommerce_disable_coming_soon', true)) {
			update_option('woocommerce_coming_soon', 'no');
		}

		self::regenerate_product_lookup();
	}

	/**
	 * Rebuild the WooCommerce product lookup table for imported products.
	 *
	 * Mirrors WooCommerce's "Regenerate product lookup tables" tool, but runs
	 * synchronously (the importer can't wait for Action Scheduler): seed a row per
	 * product/variation, then fill each column from product meta.
	 *
	 * @return void
	 */
	protected static function regenerate_product_lookup()
	{
		global $wpdb;

		if (! function_exists('wc_update_product_lookup_tables_column')) {
			return;
		}

		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one-off lookup-table seed during import using internal table names only.
		$wpdb->query("INSERT IGNORE INTO {$lookup} (product_id) SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation') AND post_status NOT IN ('trash','auto-draft')");

		foreach (array('min_max_price', 'stock_quantity', 'sku', 'stock_status', 'average_rating', 'total_sales', 'downloadable', 'virtual') as $column) {
			wc_update_product_lookup_tables_column($column);
		}

		self::log('Rebuilt WooCommerce product lookup table.');
	}


	/* --------------------------------------------------------------------- *
	 *  Helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Old => new ID map for imported posts and attachments, from the WXR importer.
	 *
	 * @return array
	 */
	protected static function content_post_map()
	{
		$map  = array();
		$ctdi = CatchThemesDemoImport::get_instance();

		if (! empty($ctdi->importer)) {
			$data = $ctdi->importer->get_importer_data();
			if (! empty($data['mapping']['post']) && is_array($data['mapping']['post'])) {
				foreach ($data['mapping']['post'] as $old => $new) {
					$map[(int) $old] = (int) $new;
				}
			}
		}

		return $map;
	}

	/**
	 * Old => new attachment URL map, from the WXR importer's url_remap.
	 *
	 * @return array
	 */
	protected static function content_url_map()
	{
		$map  = array();
		$ctdi = CatchThemesDemoImport::get_instance();

		if (! empty($ctdi->importer)) {
			$data = $ctdi->importer->get_importer_data();
			if (! empty($data['url_remap']) && is_array($data['url_remap'])) {
				$map = $data['url_remap'];
			}
		}

		return $map;
	}

	/**
	 * Download a demo file declared on the import info, reusing the plugin's Downloader.
	 *
	 * @param array  $info   Selected demo's file info.
	 * @param string $key    The info key holding the file URL.
	 * @param string $prefix Filename prefix for the saved file.
	 * @return string Local file path, or '' on failure / not declared.
	 */
	protected static function download($info, $key, $prefix)
	{
		if (empty($info[$key])) {
			return '';
		}

		$downloader = new Downloader();
		$filename   = $prefix . Helpers::$demo_import_start_time . '.json';
		$path       = $downloader->download_file($info[$key], $filename);

		return is_wp_error($path) ? '' : $path;
	}

	/**
	 * Read a downloaded file's contents.
	 *
	 * @param string $path File path.
	 * @return string Contents, or '' on failure.
	 */
	protected static function read($path)
	{
		$data = Helpers::data_from_file($path);

		return is_wp_error($data) ? '' : $data;
	}

	/**
	 * Insert/update a post defensively for the import context.
	 *
	 * Meta-box save handlers attach to the generic `save_post` action and assume an
	 * edit-screen request. Some call check_admin_referer() *before* checking the post
	 * type or request fields (e.g. Essential Content Types Pro), so they die with
	 * "The link you followed has expired" on a nonce-less programmatic save. We
	 * therefore suspend the generic `save_post` hook for the duration of the save.
	 *
	 * Type-specific hooks (e.g. core's `save_post_wp_global_styles` /
	 * `save_post_wp_template` cache invalidation) and `wp_insert_post` are left
	 * intact, and our FSE posts carry no meta-box $_POST data to lose.
	 *
	 * @param array $postarr wp_insert_post / wp_update_post arguments.
	 * @return int|\WP_Error New/updated post ID, or WP_Error.
	 */
	protected static function save_post_safely($postarr)
	{
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- $wp_filter is a WP core global; we restore it below.
		global $wp_filter;

		$backup = isset($wp_filter['save_post']) ? $wp_filter['save_post'] : null;

		if (null !== $backup) {
			unset($wp_filter['save_post']);
		}

		$result = empty($postarr['ID']) ? wp_insert_post($postarr, true) : wp_update_post($postarr, true);

		if (null !== $backup) {
			$wp_filter['save_post'] = $backup;
		}

		return $result;
	}

	/**
	 * Append a message to the current import log file.
	 *
	 * @param string $message Message to log.
	 */
	protected static function log($message)
	{
		$ctdi = CatchThemesDemoImport::get_instance();
		$path = $ctdi->get_log_file_path();

		if (! empty($path)) {
			Helpers::append_to_file($message, $path, 'Block theme import');
		}
	}
}
