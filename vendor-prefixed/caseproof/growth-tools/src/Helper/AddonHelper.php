<?php

declare(strict_types=1);

namespace Members\Caseproof\GrowthTools\Helper;

class AddonHelper
{
    /**
     * Activate plugin.
     *
     * @param string $addon     Name of the addon.
     * @param string $addonType Type of addon.
     */
    public static function activateAddon(string $addon, string $addonType = 'plugin')
    {
        // Run a security check.
        if (check_ajax_referer('caseproof_growth_tools_install_addon', 'nonce')) {
            // Check for permissions.
            if (! current_user_can('activate_plugin')) {
                wp_send_json_error(
                    esc_html__('Could not activate addon. Please check user permissions.', 'members')
                );
            }

            if (isset($addon)) {
                $addon    = sanitize_text_field(wp_unslash($_POST['addon']));
                $activate = 'plugin' === $addonType ? activate_plugin($addon, '', false, true) : switch_theme($addon);

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
     * @param string $addon     Name of the plugin.
     * @param string $addonType Type of addon.
     */
    public static function deactivateAddon(string $addon, string $addonType = 'plugin')
    {
        // Run a security check.
        check_ajax_referer('caseproof_growth_tools_install_addon', 'nonce');

        // Check for permissions.
        if (! current_user_can('activate_plugins')) {
            wp_send_json_error();
        }

        if ('theme' === $addonType) {
            wp_send_json_error(esc_html__(
                'Could not deactivate the theme. Please deactivate from the theme page.',
                'members'
            ));
        }

        try {
            deactivate_plugins($addon);
            wp_send_json_success(esc_html__('Plugin deactivated.', 'members'));
        } catch (\Exception $exception) {
            wp_send_json_error(esc_html__(
                'Could not deactivate the addon. Please deactivate from the Plugins page.',
                'members'
            ));
        }
    }

    /**
     * Download and install plugin.
     *
     * @param string $addon     Plugin.
     * @param string $addonType Type of addon.
     *
     * @return false
     */
    public static function installAddon(string $addon, string $addonType = 'plugin')
    {
        // Run a security check.
        check_ajax_referer('caseproof_growth_tools_install_addon', 'nonce');

        // Check for permissions.
        if (! current_user_can('install_plugins')) {
            wp_send_json_error();
        }

        // Install the addon.
        if (isset($addon)) {
            $downloadUrl = sanitize_text_field(wp_unslash($addon));

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
            $skin      = new AddonInstallSkin();
            $installer = 'plugin' === $addonType ? new \Plugin_Upgrader($skin) : new \Theme_Upgrader($skin);
            $installer->install($downloadUrl);

            // Flush the cache and return the newly installed plugin basename.
            wp_cache_flush();

            if ($installer) {
                $addonBasename = 'plugin' === $addonType
                    ? $installer->plugin_info()
                    : $installer->theme_info();
                echo wp_json_encode(['addon' => $addonBasename]);
                wp_die();
            }
        }

        ob_end_clean();
        // Send back a response.
        echo wp_json_encode(true);
        wp_die();
    }

    /**
     * Checks if a given theme is active.
     *
     * @param  string $themeDir Directory name of the theme.
     * @return boolean True if the theme is active, false otherwise.
     */
    public static function isThemeActive(string $themeDir): bool
    {
        return $themeDir === get_template();
    }

    /**
     * Determines the status of an array of add-ons.
     *
     * @param  array    $addons           An array of add-ons, with each item having a 'addon_file' key.
     * @param  array    $existingAddons   An array of existing plugins/Themes.
     * @param  callable $isActiveCallback A callback to determine if a plugins/Themes is active.
     * @return array An array of add-on statuses, with the add-on's main file as the key.
     */
    public static function processAddonStatus(array $addons, array $existingAddons, callable $isActiveCallback): array
    {
        $addonsStatus = [];
        foreach ($addons as $addon) {
            $addonFile = $addon['addon_file'];
            $status    = 'notinstalled';

            if (in_array($addonFile, $existingAddons, true)) {
                $status = 'installed';
                if ($isActiveCallback($addonFile)) {
                    $status = 'activated';
                }
            }

            $addonsStatus[$addonFile] = $status;
        }
        return $addonsStatus;
    }
}
