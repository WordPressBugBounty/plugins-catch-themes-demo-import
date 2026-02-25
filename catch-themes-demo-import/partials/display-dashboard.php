<?php

// Exit if accessed directly
if (! defined('ABSPATH')) exit;

/**
 * Provide a admin area dashboard view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       catchplugins.com
 * @since      1.0.0
 *
 * @package    Catch_Themes_Demo_Import
 * @subpackage Catch_Themes_Demo_Import/admin/partials
 */
?>

<div id="catch-themes-demo-import" class="catchids-main">
	<div class="content-wrapper">
		<div class="header">
			<h2><?php esc_html_e('Settings', 'catch-themes-demo-import'); ?></h2>
		</div> <!-- .Header -->
		<div class="content">
			<div class="module-container catch-themes-demo-import-options">
				<?php
				$options    = catchids_get_options();
				$post_types = catchids_get_all_post_types();
				foreach ($post_types as $key => $value) :
				?>
					<!-- Custom Post Types -->
					<div id="module-<?php echo esc_attr($key); ?>" class="catch-modules">
						<div class="module-header <?php echo esc_attr($options[$key] ? 'active' : 'inactive'); ?>">
							<h3 class="module-title"><?php esc_html($value); ?></h3>
							<div class="switch">
								<input type="checkbox" id="catchids_options[<?php echo esc_html($key); ?>]" class="catchids-input-switch" rel="<?php echo esc_attr($key); ?>" <?php checked(true, esc_attr($options[$key])); ?>>
								<label for="catchids_options[<?php echo esc_attr($key); ?>]"></label>
							</div>
							<div class="loader"></div>
						</div><!-- .module-header -->
					</div><!-- .catch-modules -->
				<?php endforeach; ?>

				<!-- Media -->
				<div id="module-<?php echo esc_attr('media'); ?>" class="catch-modules">
					<div class="module-header <?php echo esc_attr($options['media'] ? 'active' : 'inactive'); ?>">
						<h3 class="module-title"><?php esc_html_e('Media', 'catch-themes-demo-import'); ?></h3>
						<div class="switch">
							<input type="checkbox" id="catchids_options[media]" class="catchids-input-switch" rel="media" <?php checked(true, $options['media']); ?>>
							<label for="catchids_options[media]"></label>
						</div>
						<div class="loader"></div>
					</div><!-- .module-header -->
				</div><!-- .catch-modules -->

				<!-- Categories -->
				<div id="module-<?php echo esc_attr('category'); ?>" class="catch-modules">
					<div class="module-header <?php echo esc_attr($options['category'] ? 'active' : 'inactive'); ?>">
						<h3 class="module-title"><?php esc_html_e('Categories', 'catch-themes-demo-import'); ?></h3>
						<div class="switch">
							<input type="checkbox" id="catchids_options[category]" class="catchids-input-switch" rel="category" <?php checked(true, $options['category']); ?>>
							<label for="catchids_options[category]"></label>
						</div>
						<div class="loader"></div>
					</div><!-- .module-header -->
				</div><!-- .catch-modules -->

				<!-- Users -->
				<div id="module-<?php echo esc_attr('user'); ?>" class="catch-modules">
					<div class="module-header <?php echo esc_attr($options['user'] ? 'active' : 'inactive'); ?>">
						<h3 class="module-title"><?php esc_html_e('Users', 'catch-themes-demo-import'); ?></h3>
						<div class="switch">
							<input type="checkbox" id="catchids_options[user]" class="catchids-input-switch" rel="user" <?php checked(true, $options['user']); ?>>
							<label for="catchids_options[user]"></label>
						</div>
						<div class="loader"></div>
					</div><!-- .module-header -->
				</div><!-- .catch-modules -->

				<!-- Comments -->
				<div id="module-<?php echo 'comment'; ?>" class="catch-modules">
					<div class="module-header <?php echo esc_attr($options['comment'] ? 'active' : 'inactive'); ?>">
						<h3 class="module-title"><?php esc_html_e('Comments', 'catch-themes-demo-import'); ?></h3>
						<div class="switch">
							<input type="checkbox" id="catchids_options[comment]" class="catchids-input-switch" rel="comment" <?php checked(true, $options['comment']); ?>>
							<label for="catchids_options[comment]"></label>
						</div>
						<div class="loader"></div>
					</div><!-- .module-header -->
				</div><!-- .catch-modules -->

			</div><!-- .module-container -->
		</div><!-- .content -->
	</div> <!-- .content-wrapper -->
</div> <!-- Main Content-->