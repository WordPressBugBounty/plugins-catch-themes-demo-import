<?php

// Exit if accessed directly
if (! defined('ABSPATH')) exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- catch_themes_demo_import_ is the plugin's function prefix; registered as add_action callback.
function catch_themes_demo_import_navigation()
{
	// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- local function variables, not exposed to global scope.
	$registered_menus = get_registered_nav_menus();
	$nav_menus = get_terms(array(
		'taxonomy'   => 'nav_menu',
		'hide_empty' => true,
	));

	$menus = array();
	foreach ($nav_menus as $menu) {
		$menus[$menu->name] = $menu->term_id;
	}

	$new_menu = array();
	foreach ($registered_menus as $location => $description) {
		foreach ($menus as $key => $value) {
			if (strpos($key, 'Social') !== false && strpos($location, 'social') !== false) {
				$new_menu[$location] = $value;
			} elseif (strpos($key, 'Social') === false && strpos($location, 'social') === false) {
				$new_menu[$location] = $value;
			}
		}
	}
	// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	set_theme_mod('nav_menu_locations', $new_menu);
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public action API.
add_action('cp-ctdi/after_import', 'catch_themes_demo_import_navigation');

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
