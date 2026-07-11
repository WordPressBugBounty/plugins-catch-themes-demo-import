<?php
/**
 * Static functions used in the CTDI plugin.
 *
 * @package ctdi
 */

namespace CTDI;

// Exit if accessed directly
if (! defined('ABSPATH')) exit;

/**
 * Class with static helper functions.
 */
class Helpers
{
	/**
	 * Holds the date and time string for demo import and log file.
	 *
	 * @var string
	 */
	public static $demo_import_start_time = '';

	/**
	 * Filter through the array of import files and get rid of those who do not comply.
	 *
	 * @param  array $import_files list of arrays with import file details.
	 * @return array list of filtered arrays.
	 */
	public static function validate_import_file_info($import_files)
	{
		$filtered_import_file_info = array();
		if (is_array($import_files)) {
			foreach ($import_files as $import_file) {
				if (self::is_import_file_info_format_correct($import_file)) {
					$filtered_import_file_info[] = $import_file;
				}
			}
		}
		return $filtered_import_file_info;
	}

	/**
	 * Helper function: a simple check for valid import file format.
	 *
	 * @param  array $import_file_info array with import file details.
	 * @return boolean
	 */
	private static function is_import_file_info_format_correct($import_file_info)
	{

		if (empty($import_file_info['import_file_name'])) {
			return false;
		}

		return true;
	}


	/**
	 * Download import files. Content .xml and widgets .wie|.json files.
	 *
	 * @param  array  $import_file_info array with import file details.
	 * @return array|WP_Error array of paths to the downloaded files or WP_Error object with error message.
	 */
	public static function download_import_files($import_file_info)
	{
		$downloaded_files = array(
			'content'    => '',
			'widgets'    => '',
			'customizer' => '',
			'redux'      => '',
		);
		$downloader       = new Downloader();

		// ----- Set content file path -----
		// Check if 'import_file_url' is not defined. That would mean a local file.
		if (empty($import_file_info['import_file_url'])) {
			if (! empty($import_file_info['local_import_file']) && file_exists($import_file_info['local_import_file'])) {
				$downloaded_files['content'] = $import_file_info['local_import_file'];
			}
		} else {
			// Set the filename string for content import file.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
			$content_filename = apply_filters('cp-ctdi/downloaded_content_file_prefix', 'demo-content-import-file_') . self::$demo_import_start_time . apply_filters('cp-ctdi/downloaded_content_file_suffix_and_file_extension', '.xml');

			// Download the content import file.
			$downloaded_files['content'] = $downloader->download_file($import_file_info['import_file_url'], $content_filename);

			// Return from this function if there was an error.
			if (is_wp_error($downloaded_files['content'])) {
				return $downloaded_files['content'];
			}
		}

		// ----- Set widget file path -----
		// Get widgets file as well. If defined!
		if (! empty($import_file_info['import_widget_file_url'])) {
			// Set the filename string for widgets import file.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
			$widget_filename = apply_filters('cp-ctdi/downloaded_widgets_file_prefix', 'demo-widgets-import-file_') . self::$demo_import_start_time . apply_filters('cp-ctdi/downloaded_widgets_file_suffix_and_file_extension', '.json');

			// Download the widgets import file.
			$downloaded_files['widgets'] = $downloader->download_file($import_file_info['import_widget_file_url'], $widget_filename);

			// Return from this function if there was an error.
			if (is_wp_error($downloaded_files['widgets'])) {
				return $downloaded_files['widgets'];
			}
		} elseif (! empty($import_file_info['local_import_widget_file'])) {
			if (file_exists($import_file_info['local_import_widget_file'])) {
				$downloaded_files['widgets'] = $import_file_info['local_import_widget_file'];
			}
		}

		// ----- Set customizer file path -----
		// Get customizer import file as well. If defined!
		if (! empty($import_file_info['import_customizer_file_url'])) {
			// Setup filename path to save the customizer content.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
			$customizer_filename = apply_filters('cp-ctdi/downloaded_customizer_file_prefix', 'demo-customizer-import-file_') . self::$demo_import_start_time . apply_filters('cp-ctdi/downloaded_customizer_file_suffix_and_file_extension', '.dat');

			// Download the customizer import file.
			$downloaded_files['customizer'] = $downloader->download_file($import_file_info['import_customizer_file_url'], $customizer_filename);

			// Return from this function if there was an error.
			if (is_wp_error($downloaded_files['customizer'])) {
				return $downloaded_files['customizer'];
			}
		} elseif (! empty($import_file_info['local_import_customizer_file'])) {
			if (file_exists($import_file_info['local_import_customizer_file'])) {
				$downloaded_files['customizer'] = $import_file_info['local_import_customizer_file'];
			}
		}

		// ----- Set Redux file paths -----
		// Get Redux import file as well. If defined!
		if (! empty($import_file_info['import_redux']) && is_array($import_file_info['import_redux'])) {
			$redux_items = array();

			// Setup filename paths to save the Redux content.
			foreach ($import_file_info['import_redux'] as $index => $redux_item) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
				$redux_filename = apply_filters('cp-ctdi/downloaded_redux_file_prefix', 'demo-redux-import-file_') . $index . '-' . self::$demo_import_start_time . apply_filters('cp-ctdi/downloaded_redux_file_suffix_and_file_extension', '.json');

				// Download the Redux import file.
				$file_path = $downloader->download_file($redux_item['file_url'], $redux_filename);


				// Return from this function if there was an error.
				if (is_wp_error($file_path)) {
					return $file_path;
				}

				$redux_items[] = array(
					'option_name' => $redux_item['option_name'],
					'file_path'   => $file_path,
				);
			}

			// Download the Redux import file.
			$downloaded_files['redux'] = $redux_items;
		} elseif (! empty($import_file_info['local_import_redux'])) {

			$redux_items = array();

			// Setup filename paths to save the Redux content.
			foreach ($import_file_info['local_import_redux'] as $redux_item) {
				if (file_exists($redux_item['file_path'])) {
					$redux_items[] = $redux_item;
				}
			}

			// Download the Redux import file.
			$downloaded_files['redux'] = $redux_items;
		}

		return $downloaded_files;
	}


	/**
	 * Write content to a file.
	 *
	 * @param string $content content to be saved to the file.
	 * @param string $file_path file path where the content should be saved.
	 * @return string|WP_Error path to the saved file or WP_Error object with error message.
	 */
	public static function write_to_file($content, $file_path)
	{
		// Verify WP file-system credentials.
		$verified_credentials = self::check_wp_filesystem_credentials();

		if (is_wp_error($verified_credentials)) {
			return $verified_credentials;
		}

		// By this point, the $wp_filesystem global should be working, so let's use it to create a file.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $wp_filesystem is a WordPress core global; cannot be renamed.
		global $wp_filesystem;

		if (! $wp_filesystem->put_contents($file_path, $content)) {
			return new \WP_Error(
				'failed_writing_file_to_server',
				sprintf(
					// Translators:  Notice error message after incorrect file path that was attempted to be written.
					__('An error occurred while writing file to your server! Tried to write a file to: %1$s%2$s.', 'catch-themes-demo-import'),
					'<br>',
					esc_html($file_path)
				)
			);
		}

		// Return the file path on successful file write.
		return $file_path;
	}



	/**
	 * Append content to the file.
	 *
	 * @param string $content content to be saved to the file.
	 * @param string $file_path file path where the content should be saved.
	 * @param string $separator_text separates the existing content of the file with the new content.
	 * @return boolean|WP_Error, path to the saved file or WP_Error object with error message.
	 */

	public static function append_to_file($content, $file_path, $separator_text = '')
	{
		// Verify WP file-system credentials.
		$verified_credentials = self::check_wp_filesystem_credentials();

		if (is_wp_error($verified_credentials)) {
			return $verified_credentials;
		}

		// By this point, the $wp_filesystem global should be working, so let's use it to create a file.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $wp_filesystem is a WordPress core global; cannot be renamed.
		global $wp_filesystem;

		$existing_data = '';

		if (file_exists($file_path)) {
			$existing_data = $wp_filesystem->get_contents($file_path);
		}

		// Style separator.
		$separator = PHP_EOL . '---' . $separator_text . '---' . PHP_EOL;

		if (! $wp_filesystem->put_contents($file_path, $existing_data . $separator . $content . PHP_EOL)) {
			return new \WP_Error(
				'failed_writing_file_to_server',
				sprintf(
					// Translators:  Notice error message after incorrect file path that was attempted to be written.
					__('An error occurred while writing file to your server! Tried to write a file to: %1$s%2$s.', 'catch-themes-demo-import'),
					'<br>',
					esc_html($file_path)
				)
			);
		}

		return true;
	}


	/**
	 * Get data from a file
	 *
	 * @param string $file_path file path where the content should be saved.
	 * @return string $data, content of the file or WP_Error object with error message.
	 */
	public static function data_from_file($file_path)
	{
		// Verify WP file-system credentials.
		$verified_credentials = self::check_wp_filesystem_credentials();

		if (is_wp_error($verified_credentials)) {
			return $verified_credentials;
		}

		// By this point, the $wp_filesystem global should be working, so let's use it to read a file.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $wp_filesystem is a WordPress core global; cannot be renamed.
		global $wp_filesystem;

		$data = $wp_filesystem->get_contents($file_path);

		if (! $data) {
			return new \WP_Error(
				'failed_reading_file_from_server',
				sprintf(
					// Translators:  Notice error message after incorrect reading a file.
					__('An error occurred while reading a file from your server! Tried reading file from path: %1$s%2$s.', 'catch-themes-demo-import'),
					'<br>',
					esc_html($file_path)
				)
			);
		}

		// Return the file data.
		return $data;
	}


	/**
	 * Helper function: check for WP file-system credentials needed for reading and writing to a file.
	 *
	 * @return boolean|WP_Error
	 */
	private static function check_wp_filesystem_credentials()
	{
		// Check if the file-system method is 'direct', if not display an error.
		if (! ('direct' === get_filesystem_method())) {
			return new \WP_Error(
				'no_direct_file_access',
				sprintf(
					// Translators: %1$s %2$s is strong tag, %3$s is link for How to set direct filesystem method
					__('This WordPress page does not have %1$sdirect%2$s write file access. This plugin needs it in order to save the demo import xml file to the upload directory of your site. You can change this setting with these instructions: %3$s.', 'catch-themes-demo-import'),
					'<strong>',
					'</strong>',
					'<a href="http://gregorcapuder.com/wordpress-how-to-set-direct-filesystem-method/" target="_blank">How to set <strong>direct</strong> filesystem method</a>'
				)
			);
		}

		// Get plugin page settings.
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$plugin_page_setup = apply_filters(
			'cp-ctdi/plugin_page_setup',
			array(
				'parent_slug' => 'themes.php',
				'page_title'  => esc_html__('Catch Themes Demo Import', 'catch-themes-demo-import'),
				'menu_title'  => esc_html__('Catch Themes Demo Import', 'catch-themes-demo-import'),
				'capability'  => 'import',
				'menu_slug'   => 'catch-themes-demo-import',
			)
		);
		// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		// Get user credentials for WP file-system API.
		$demo_import_page_url = wp_nonce_url($plugin_page_setup['parent_slug'] . '?page=' . $plugin_page_setup['menu_slug'], $plugin_page_setup['menu_slug']);

		if (false === ($creds = request_filesystem_credentials($demo_import_page_url, '', false, false, null))) {
			return new \WP_error(
				'filesystem_credentials_could_not_be_retrieved',
				__('An error occurred while retrieving reading/writing permissions to your server (could not retrieve WP filesystem credentials)!', 'catch-themes-demo-import')
			);
		}

		// Now we have credentials, try to get the wp_filesystem running.
		if (! WP_Filesystem($creds)) {
			return new \WP_Error(
				'wrong_login_credentials',
				__('Your WordPress login credentials don\'t allow to use WP_Filesystem!', 'catch-themes-demo-import')
			);
		}

		return true;
	}


	/**
	 * Get log file path
	 *
	 * @return string, path to the log file
	 */
	public static function get_log_path()
	{
		$upload_dir  = wp_upload_dir();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$upload_path = apply_filters('cp-ctdi/upload_file_path', trailingslashit($upload_dir['path']));

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$log_path = $upload_path . apply_filters('cp-ctdi/log_file_prefix', 'log_file_') . self::$demo_import_start_time . apply_filters('cp-ctdi/log_file_suffix_and_file_extension', '.txt');

		self::register_file_as_media_attachment($log_path);

		return $log_path;
	}


	/**
	 * Register file as attachment to the Media page.
	 *
	 * @param string $log_path log file path.
	 * @return void
	 */
	public static function register_file_as_media_attachment($log_path)
	{
		// Check the type of file.
		$log_mimes = array('txt' => 'text/plain');
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$filetype  = wp_check_filetype(basename($log_path), apply_filters('cp-ctdi/file_mimes', $log_mimes));

		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => self::get_log_url($log_path),
			'post_mime_type' => $filetype['type'],
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
			'post_title'     => apply_filters('cp-ctdi/attachment_prefix', esc_html__('Catch Themes Demo Import - ', 'catch-themes-demo-import')) . preg_replace('/\.[^.]+$/', '', basename($log_path)),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the file as attachment in Media page.
		$attach_id = wp_insert_attachment($attachment, $log_path);
	}


	/**
	 * Get log file url
	 *
	 * @param string $log_path log path to use for the log filename.
	 * @return string, url to the log file.
	 */
	public static function get_log_url($log_path)
	{
		$upload_dir = wp_upload_dir();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$upload_url = apply_filters('cp-ctdi/upload_file_url', trailingslashit($upload_dir['url']));

		return $upload_url . basename($log_path);
	}


	/**
	 * Prevent PHP error *display* from corrupting an AJAX/JSON response for the
	 * remainder of this request. Errors are still written to the debug log
	 * (WP_DEBUG_LOG); they are just not echoed into the body, where they would
	 * break JSON parsing.
	 */
	public static function silence_error_display()
	{
		// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed, Squiz.PHP.DiscouragedFunctions.Discouraged -- silencing error display (not logging) for a JSON endpoint.
		@ini_set('display_errors', '0');
	}

	/**
	 * Check if the AJAX call is valid.
	 */
	public static function verify_ajax_call()
	{
		// Keep PHP error output from corrupting this AJAX request's JSON response.
		// With WP_DEBUG_DISPLAY enabled, notices/warnings/deprecations from WordPress,
		// the active theme or other plugins are echoed into the body and break JSON
		// parsing (the import then fails on the front end with "Error: OK (200)").
		// They are still recorded in the debug log via WP_DEBUG_LOG.
		self::silence_error_display();

		check_ajax_referer('ctdi-ajax-verification', 'security');


		// Check if user has the WP capability to import data.
		if (! current_user_can('import')) {

			wp_die(
				wp_kses_post(
					sprintf(
						// Translators: %1$s %2$s is a opening and closing HTML tag with a notice.
						__('%1$sYour user role isn\'t high enough. You don\'t have permission to import demo data.%2$s', 'catch-themes-demo-import'),
						'<div class="notice notice-error"><p>',
						'</p></div>'
					)
				)
			);
		}
	}


	/**
	 * Process uploaded files and return the paths to these files.
	 *
	 * @param array  $uploaded_files $_FILES array form an AJAX request.
	 * @param string $log_file_path path to the log file.
	 * @return array of paths to the content import and widget import files.
	 */
	public static function process_uploaded_files($uploaded_files, $log_file_path)
	{
		// Variable holding the paths to the uploaded files.
		$selected_import_files = array(
			'content'    => '',
			'widgets'    => '',
			'customizer' => '',
			'redux'      => '',
		);

		// Upload settings to disable form and type testing for AJAX uploads.
		$upload_overrides = array(
			'test_form' => false,
			'test_type' => false,
		);

		// Handle demo content and widgets file upload.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- nonce verified upstream via Helpers::verify_ajax_call(); wp_handle_upload() validates and sanitizes all file data.
		$no_file_error        = array('error' => esc_html__('No file was uploaded.', 'catch-themes-demo-import'));
		$content_file_info    = isset($_FILES['content_file']) ? wp_handle_upload($_FILES['content_file'], $upload_overrides) : $no_file_error;
		$widget_file_info     = isset($_FILES['widget_file']) ? wp_handle_upload($_FILES['widget_file'], $upload_overrides) : $no_file_error;
		$customizer_file_info = isset($_FILES['customizer_file']) ? wp_handle_upload($_FILES['customizer_file'], $upload_overrides) : $no_file_error;
		$redux_file_info      = isset($_FILES['redux_file']) ? wp_handle_upload($_FILES['redux_file'], $upload_overrides) : $no_file_error;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing

		// Process content import file.
		if ($content_file_info && ! isset($content_file_info['error'])) {
			// Set uploaded content file.
			$selected_import_files['content'] = $content_file_info['file'];
		} else {
			// Add this error to log file.
			$log_added = self::append_to_file(
				sprintf(
					// Translators: %s is error message to be display while content file is not uploaded
					__('Content file was not uploaded. Error: %s', 'catch-themes-demo-import'),
					$content_file_info['error'] ?? esc_html__('Unknown upload error.', 'catch-themes-demo-import')
				),
				$log_file_path,
				esc_html__('Upload files', 'catch-themes-demo-import')
			);
		}

		// Process widget import file.
		if ($widget_file_info && ! isset($widget_file_info['error'])) {
			// Set uploaded widget file.
			$selected_import_files['widgets'] = $widget_file_info['file'];
		} else {
			// Add this error to log file.
			$log_added = self::append_to_file(
				sprintf(
					// Translators: %s is error message to be display while widget file is not uploaded
					__('Widget file was not uploaded. Error: %s', 'catch-themes-demo-import'),
					$widget_file_info['error'] ?? esc_html__('Unknown upload error.', 'catch-themes-demo-import')
				),
				$log_file_path,
				esc_html__('Upload files', 'catch-themes-demo-import')
			);
		}

		// Process Customizer import file.
		if ($customizer_file_info && ! isset($customizer_file_info['error'])) {
			// Set uploaded customizer file.
			$selected_import_files['customizer'] = $customizer_file_info['file'];
		} else {
			// Add this error to log file.
			$log_added = self::append_to_file(
				sprintf(
					// Translators: %s is error message to be display while customizer file is not uploaded
					__('Customizer file was not uploaded. Error: %s', 'catch-themes-demo-import'),
					$customizer_file_info['error'] ?? esc_html__('Unknown upload error.', 'catch-themes-demo-import')
				),
				$log_file_path,
				esc_html__('Upload files', 'catch-themes-demo-import')
			);
		}

		// Process Redux import file.
		if ($redux_file_info && ! isset($redux_file_info['error'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified upstream via Helpers::verify_ajax_call().
			if (isset($_POST['redux_option_name']) && empty($_POST['redux_option_name'])) {
				// Write error to log file and send an AJAX response with the error.
				self::log_error_and_send_ajax_response(
					esc_html__('Missing Redux option name! Please also enter the Redux option name!', 'catch-themes-demo-import'),
					$log_file_path,
					esc_html__('Upload files', 'catch-themes-demo-import')
				);
			}

			// Set uploaded Redux file.
			// Note: do not run the full path through sanitize_file_name() — that strips the
			// directory separators and the saved file can no longer be located on disk.
			$selected_import_files['redux'] = array(
				array(
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified upstream via Helpers::verify_ajax_call().
					'option_name' => isset($_POST['redux_option_name']) ? sanitize_text_field(wp_unslash($_POST['redux_option_name'])) : '',
					'file_path'   => $redux_file_info['file'],
				),
			);
		} else {
			// Add this error to log file.
			$log_added = self::append_to_file(
				sprintf(
					// Translators: %s is error message to be display while redux file is not uploaded
					__('Redux file was not uploaded. Error: %s', 'catch-themes-demo-import'),
					$redux_file_info['error'] ?? esc_html__('Unknown upload error.', 'catch-themes-demo-import')
				),
				$log_file_path,
				esc_html__('Upload files', 'catch-themes-demo-import')
			);
		}

		// Add this message to log file.
		$log_added = self::append_to_file(
			__('The import files were successfully uploaded!', 'catch-themes-demo-import') . self::import_file_info($selected_import_files),
			$log_file_path,
			esc_html__('Upload files', 'catch-themes-demo-import')
		);

		// Return array with paths of uploaded files.
		return $selected_import_files;
	}


	/**
	 * Get import file information and max execution time.
	 *
	 * @param array $selected_import_files array of selected import files.
	 */
	public static function import_file_info($selected_import_files)
	{
		$redux_file_string = '';

		if (! empty($selected_import_files['redux'])) {
			$redux_file_string = array_reduce(
				$selected_import_files['redux'],
				function ($string, $item) {
					return sprintf('%1$s%2$s -> %3$s %4$s', $string, $item['option_name'], $item['file_path'], PHP_EOL);
				},
				''
			);
		}

		return PHP_EOL .
			sprintf(
				// Translators: %s is a max execution time.
				__('Initial max execution time = %s', 'catch-themes-demo-import'),
				ini_get('max_execution_time')
			) . PHP_EOL .
			sprintf(
				// Translators: %1$s file into %2$s%1$s is a site url %3$s%1$s is a data file %4$s%1$s is a customizer file and %1$s%6$s is a redux file
				__('Files info:%1$sSite URL = %2$s%1$sData file = %3$s%1$sWidget file = %4$s%1$sCustomizer file = %5$s%1$sRedux files:%1$s%6$s', 'catch-themes-demo-import'),
				PHP_EOL,
				get_site_url(),
				empty($selected_import_files['content']) ? esc_html__('not defined!', 'catch-themes-demo-import') : $selected_import_files['content'],
				empty($selected_import_files['widgets']) ? esc_html__('not defined!', 'catch-themes-demo-import') : $selected_import_files['widgets'],
				empty($selected_import_files['customizer']) ? esc_html__('not defined!', 'catch-themes-demo-import') : $selected_import_files['customizer'],
				empty($redux_file_string) ? esc_html__('not defined!', 'catch-themes-demo-import') : $redux_file_string
			);
	}


	/**
	 * Write the error to the log file and send the AJAX response.
	 *
	 * @param string $error_text text to display in the log file and in the AJAX response.
	 * @param string $log_file_path path to the log file.
	 * @param string $separator title separating the old and new content.
	 */
	public static function log_error_and_send_ajax_response($error_text, $log_file_path, $separator = '')
	{
		// Add this error to log file.
		$log_added = self::append_to_file(
			$error_text,
			$log_file_path,
			$separator
		);

		// Send JSON Error response to the AJAX call.
		wp_send_json($error_text);
	}


	/**
	 * Set the $demo_import_start_time class variable with the current date and time string.
	 */

	public static function set_demo_import_start_time()
	{
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$format = apply_filters('cp-ctdi/date_format_for_file_names', 'Y-m-d__H-i-s');
		self::$demo_import_start_time = gmdate($format);
	}


	/**
	 * Get the category list of all categories used in the predefined demo imports array.
	 *
	 * @param  array $demo_imports Array of demo import items (arrays).
	 * @return array|boolean       List of all the categories or false if there aren't any.
	 */
	public static function get_all_demo_import_categories($demo_imports)
	{
		$categories = array();

		foreach ($demo_imports as $item) {
			if (! empty($item['categories']) && is_array($item['categories'])) {
				foreach ($item['categories'] as $category) {
					$categories[sanitize_key($category)] = $category;
				}
			}
		}

		if (empty($categories)) {
			return false;
		}

		return $categories;
	}


	/**
	 * Return the concatenated string of demo import item categories.
	 * These should be separated by comma and sanitized properly.
	 *
	 * @param  array  $item The predefined demo import item data.
	 * @return string       The concatenated string of categories.
	 */
	public static function get_demo_import_item_categories($item)
	{
		$sanitized_categories = array();

		if (isset($item['categories'])) {
			foreach ($item['categories'] as $category) {
				$sanitized_categories[] = sanitize_key($category);
			}
		}

		if (! empty($sanitized_categories)) {
			return implode(',', $sanitized_categories);
		}

		return false;
	}


	/**
	 * Set the CTDI transient with the current importer data.
	 *
	 * @param array $data Data to be saved to the transient.
	 */
	public static function set_ctdi_import_data_transient($data)
	{
		// Lifetime of the import "resume" state shared between the multiple AJAX calls.
		// This MUST outlast the whole import. Image-heavy demos that download remote
		// attachments can run for many minutes, so the previous 6-minute (0.1 hour)
		// lifetime expired mid-import: the next AJAX call then found no resume data,
		// restarted the import from the beginning, re-downloaded the same files and
		// got stuck repeating "New AJAX call!" without ever finishing. Use a long,
		// filterable lifetime so large imports can complete.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- cp-ctdi/ is the established hook prefix for this plugin's public API.
		$expiration = apply_filters('cp-ctdi/importer_data_transient_expiration', DAY_IN_SECONDS);

		set_transient('ctdi_importer_data', $data, $expiration);
	}
}
