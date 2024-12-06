<?php

namespace Members\Caseproof\GrowthTools\Helper;

class AddonHelper
{
    /**
     * Activate plugin.
     *
     * @param string $plugin Name of the plugin.
     */
    public static function activateAddon(string $plugin)
    {
        // Run a security check.
        if (check_ajax_referer('caseproof_growth_tools_install_addon', 'nonce')) {
            // Check for permissions.
            if (! current_user_can('activate_plugin')) {
                wp_send_json_error(
                    esc_html__('Could not activate addon. Please check user permissions.', 'members')
                );
            }

            if (isset($plugin)) {
                $plugin   = sanitize_text_field(wp_unslash($_POST['plugin']));
                $activate = activate_plugin($plugin, '', false, true);

                if (! is_wp_error($activate)) {
                    wp_send_json_success(esc_html__('Addon activated.', 'members'));
                }
            }

            wp_send_json_error(
                esc_html__('Could not activate addon. Please activate from the Plugins page.', 'members')
            );
        }

        wp_send_json_error(
            esc_html__('Could not activate addon. Please refresh page and try again.', 'members')
        );
    }

    /**
     * Deactivate plugin.
     *
     * @param string $plugin Name of the plugin.
     */
    public static function deactivateAddon(string $plugin)
    {
        // Run a security check.
        check_ajax_referer('caseproof_growth_tools_install_addon', 'nonce');

        // Check for permissions.
        if (! current_user_can('activate_plugins')) {
            wp_send_json_error();
        }

        try {
            deactivate_plugins($plugin);
            wp_send_json_success(esc_html__('Plugin deactivated.', 'members'));
        } catch (\Exception $exception) {
            wp_send_json_error(esc_html__('Could not deactivate the addon. Please deactivate from the Plugins page.', 'members'));
        }
    }

    /**
     * Download and install plugin.
     *
     * @param  string $plugin Plugin .
     * @return false
     */
    public static function installAddon(string $plugin)
    {
        // Run a security check.
        check_ajax_referer('caseproof_growth_tools_install_addon', 'nonce');

        // Check for permissions.
        if (! current_user_can('install_plugins')) {
            wp_send_json_error();
        }

        // Install the addon.
        if (isset($plugin)) {
            $download_url = sanitize_text_field(wp_unslash($plugin));

            // Set the current screen to avoid undefined notices.
            set_current_screen();

            // Prepare variables.
            $method = '';
            $url    = add_query_arg(
                [
                    'page' => 'caseproof_grtl-growth-tools',
                ],
                admin_url('admin.php')
            );
            $url    = esc_url($url);

            // Start output bufferring to catch the filesystem form if credentials are needed.
            ob_start();
            $creds = request_filesystem_credentials($url, $method, false, false, null);

            // Check for file system permissions.
            if (false === $creds) {
                return false;
            }

            if (!WP_Filesystem($creds)) {
                return false;
            }

            // We do not need any extra credentials if we have gotten this far, so let's install the plugin.
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

            // Do not allow WordPress to search/download translations, as this will break JS output.
            remove_action('upgrader_process_complete', ['Language_Pack_Upgrader', 'async_upgrade'], 20);

            // Create the plugin upgrader with our custom skin.
            $installer = new \Plugin_Upgrader(new AddonInstallSkin());
            $installer->install($download_url);

            // Flush the cache and return the newly installed plugin basename.
            wp_cache_flush();
            if ($installer->plugin_info()) {
                $plugin_basename = $installer->plugin_info();
                echo wp_json_encode(['plugin' => $plugin_basename]);
                wp_die();
            }
        }

        ob_end_clean();
        // Send back a response.
        echo wp_json_encode(true);
        wp_die();
    }
}
