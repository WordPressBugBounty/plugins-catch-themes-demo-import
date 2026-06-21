<?php

// Exit if accessed directly
if (! defined('ABSPATH')) exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- catch_themes_demo_import_ is the plugin's function prefix; registered as add_action callback.
function catch_themes_demo_import_navigation()
{
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local function variables, not exposed to global scope.

	// The customizer import already set nav_menu_locations to exactly the locations
	// the demo uses -- but the menu IDs inside it are the OLD ids from the export
	// site, so they no longer match the freshly imported menus. We only need to
	// translate those old ids to the new ones, and ONLY for the locations the demo
	// actually defined. (Previously this assigned a menu to EVERY registered location,
	// which is why every header/right/footer/social area ended up with a menu instead
	// of just the intended ones.)
	$demo_locations = get_theme_mod('nav_menu_locations');

	if (empty($demo_locations) || ! is_array($demo_locations)) {
		return;
	}

	// Old term id -> new term id map built during the content import.
	$term_map = array();
	if (class_exists('CTDI\\CatchThemesDemoImport')) {
		$ctdi = \CTDI\CatchThemesDemoImport::get_instance();
		if (! empty($ctdi->importer)) {
			$importer_data = $ctdi->importer->get_importer_data();
			if (! empty($importer_data['mapping']['term_id']) && is_array($importer_data['mapping']['term_id'])) {
				$term_map = $importer_data['mapping']['term_id'];
			}
		}
	}

	// Imported menus indexed by name, used as a fallback when the id map is unavailable.
	$menus_by_name = array();
	foreach (get_terms(array('taxonomy' => 'nav_menu', 'hide_empty' => false)) as $menu) {
		if (! is_wp_error($menu) && isset($menu->name)) {
			$menus_by_name[$menu->name] = $menu->term_id;
		}
	}

	$remapped = array();
	foreach ($demo_locations as $location => $old_menu_id) {
		if (! empty($term_map[$old_menu_id])) {
			// Preferred: precise old menu id -> new menu id translation.
			$remapped[$location] = (int) $term_map[$old_menu_id];
		} elseif (false !== stripos((string) $location, 'social')) {
			// Fallback: a "social" location gets the imported "Social" menu.
			foreach ($menus_by_name as $name => $id) {
				if (false !== stripos($name, 'social')) {
					$remapped[$location] = $id;
					break;
				}
			}
		} else {
			// Fallback: a non-social location gets the first non-social imported menu.
			foreach ($menus_by_name as $name => $id) {
				if (false === stripos($name, 'social')) {
					$remapped[$location] = $id;
					break;
				}
			}
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

	if (! empty($remapped)) {
		set_theme_mod('nav_menu_locations', $remapped);
	}
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public action API.
add_action('cp-ctdi/after_import', 'catch_themes_demo_import_navigation');

/**
 * Clear existing widgets from the theme's widget areas right before the demo widgets
 * are imported, so the demo's widget layout is applied cleanly.
 *
 * WordPress seeds every fresh install with default widgets (Search, Archives,
 * Categories, ...). The widget importer only *appends*, so without this those
 * defaults would sit alongside the imported demo widgets (e.g. "Archives" and
 * "Categories" showing above the demo's "EW: About" in Footer 1). Existing widgets
 * are moved to the Inactive Widgets area -- not deleted -- so nothing is lost and the
 * behaviour can be turned off with the cp-ctdi/clear_widgets_before_import filter.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- catch_themes_demo_import_ is the plugin's function prefix; registered as add_action callback.
function catch_themes_demo_import_clear_widgets()
{
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
	if (! apply_filters('cp-ctdi/clear_widgets_before_import', true)) {
		return;
	}

	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local function variables, not exposed to global scope.
	$sidebars_widgets = get_option('sidebars_widgets', array());

	if (! is_array($sidebars_widgets)) {
		return;
	}

	$inactive = array();
	if (! empty($sidebars_widgets['wp_inactive_widgets']) && is_array($sidebars_widgets['wp_inactive_widgets'])) {
		$inactive = $sidebars_widgets['wp_inactive_widgets'];
	}

	foreach ($sidebars_widgets as $sidebar_id => $widgets) {
		// Skip the inactive bucket and the non-array bookkeeping key.
		if ('wp_inactive_widgets' === $sidebar_id || 'array_version' === $sidebar_id) {
			continue;
		}

		if (! empty($widgets) && is_array($widgets)) {
			// Park the existing widgets in the Inactive area (recoverable, not deleted).
			$inactive = array_merge($inactive, $widgets);
			$sidebars_widgets[$sidebar_id] = array();
		}
	}

	$sidebars_widgets['wp_inactive_widgets'] = $inactive;
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

	update_option('sidebars_widgets', $sidebars_widgets);
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public action API.
add_action('cp-ctdi/widget_importer_before_widgets_import', 'catch_themes_demo_import_clear_widgets');

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only admin routing check, no data modification.
if (isset($_GET['page']) && 'catch-themes-demo-import' === $_GET['page']) {
	add_action('admin_enqueue_scripts', 'catch_themes_demo_import_plugin_active_check', 10);
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing flag set by this plugin's own redirect.
if (isset($_GET['activate_plugin'])) {
	add_action('admin_init', 'catch_themes_demo_import_activate_plugin');
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- catch_themes_demo_import_ is the plugin's function prefix; registered as add_action callback.
function catch_themes_demo_import_plugin_active_check()
{
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local function variables, not exposed to global scope.
	$current_theme = wp_get_theme();
	$activate_data = array();

	if ('Catch Themes' == wp_strip_all_tags($current_theme->author)) {
		if (! current_user_can('activate_plugins')) {
			wp_die(esc_html__('You do not have sufficient permissions to activate plugins for this site.', 'catch-themes-demo-import'));
		}
		$free    = 'essential-content-types/essential-content-types.php';
		$pro     = 'essential-content-types-pro/essential-content-types-pro.php';
		$plugins = false;
		$plugins = get_option('active_plugins'); // get active plugins

		if (! is_plugin_active($free) && ! is_plugin_active($pro)) {

			$all_plugins = get_plugins();
			// Activate Pro plugin if both plugins exist
			if (array_key_exists($free, $all_plugins) && array_key_exists($pro, $all_plugins)) {
				$activate_data = array(
					'activate' => $pro,
					'url'      => admin_url('themes.php?page=catch-themes-demo-import&activate_plugin=essential-content-types-pro'),
				);
			}
			// Activate Pro plugin if only Pro plugin exists
			elseif (! array_key_exists($free, $all_plugins) && array_key_exists($pro, $all_plugins)) {
				$activate_data = array(
					'activate' => $pro,
					'url'      => admin_url('themes.php?page=catch-themes-demo-import&activate_plugin=essential-content-types-pro'),
				);
			}
			// Activate Free plugin if only Free plugin exists or install free if none exists
			else {
				$activate_data = array(
					'activate' => $free,
					'url'      => admin_url('themes.php?page=catch-themes-demo-import&activate_plugin=essential-content-types'),
				);
			}
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	wp_localize_script('ctdi-dashboard-js', 'activate', $activate_data);
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- catch_themes_demo_import_ is the plugin's function prefix; registered as admin_init callback.
function catch_themes_demo_import_activate_plugin()
{
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local function variables, not exposed to global scope.
	$plugin      = 'essential-content-types';
	$plugin_free = 'essential-content-types/essential-content-types.php';
	$plugin_pro  = 'essential-content-types-pro/essential-content-types-pro.php';
	$all_plugins = get_plugins();
	if (array_key_exists($plugin_free, $all_plugins) || array_key_exists($plugin_pro, $all_plugins)) {
	} else {
		include_once(ABSPATH . 'wp-admin/includes/plugin-install.php'); //for plugins_api..

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		//includes necessary for Plugin_Upgrader and Plugin_Installer_Skin
		include_once(ABSPATH . 'wp-admin/includes/file.php');
		include_once(ABSPATH . 'wp-admin/includes/misc.php');
		include_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- anonymous upgrader skin class scoped inside a function; not exposed globally.
		class Quiet_Skin extends \WP_Upgrader_Skin
		{
			public function feedback($string, ...$arg)
			{
				// just keep it quiet
			}
		}

		$upgrader = new Plugin_Upgrader(new Quiet_Skin(compact('title', 'url', 'nonce', 'plugin', 'api')));
		$upgrader->install($api->download_link);
	}
	if (! current_user_can('activate_plugins')) {
		wp_die(esc_html__('You do not have sufficient permissions to activate plugins for this site.', 'catch-themes-demo-import'));
	}

	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing flag set by this plugin's own redirect; value is sanitized below.
	$activate_plugin = isset($_GET['activate_plugin']) ? sanitize_text_field(wp_unslash($_GET['activate_plugin'])) : '';

	activate_plugin($activate_plugin . '/' . $activate_plugin . '.php');
	wp_safe_redirect(admin_url('themes.php?page=catch-themes-demo-import&response=activated'));
	exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- catch_themes_demo_import_ is the plugin's function prefix; registered as after_switch_theme callback.
function catch_themes_demo_import_flush_transient()
{
	delete_transient('ctdi_demo_json');
	delete_transient('cdti_import_dir_list');
}
add_action('after_switch_theme', 'catch_themes_demo_import_flush_transient');

// Register the Block (FSE) theme import steps. Hooked on after_setup_theme so that
// wp_is_block_theme() is reliable; the class itself bails out for classic themes.
add_action('after_setup_theme', array('CTDI\\BlockImporter', 'maybe_register'), 20);
