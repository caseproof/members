<?php
/**
 * @license GPL-3.0
 *
 * Modified by Justin Tadlock on 05-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Members\Caseproof\GrowthTools;

use Members\Caseproof\GrowthTools\Helper\AddonHelper;

/**
 * Main plugin application.
 *
 * @see \Members\Caseproof\GrowthTools\instance() Instead of instantiating this class directly,
 *                                       retrieve the main instance using this function.
 */
class App
{
    /**
     * Configuration for the App.
     *
     * @var Config
     */
    protected Config $config;

    /**
     * Constructor.
     *
     * @param Config $config Config object.
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->addHooks();
    }

    /**
     * Registers WordPress hooks necessary to bootstrap the plugin.
     */
    public function addHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 9999);
        add_action('wp_ajax_caseproof_growth_tool_plugin_action_' . $this->config->instanceId, [$this, 'pluginAction']);
    }

    /**
     * Add menu into WordPress admin.
     */
    public function addMenu()
    {
        add_submenu_page(
            $this->config->parentMenuSlug,
            __('Growth Tools', 'members'),
            __('Growth Tools', 'members'),
            'install_plugins',
            $this->config->menuSlug,
            [$this, 'renderPage']
        );
    }

    /**
     * Getter for config
     *
     * @return array
     */
    protected function getConfig()
    {
        if (false === ($config = get_transient('caseproof_growth_tools_configuration_data'))) {
            $response = wp_remote_get($this->config->configFileUrl);
            $config = json_decode($response['body'], true);
            set_transient('caseproof_growth_tools_configuration_data', $config, 24 * HOUR_IN_SECONDS);
        }

        return $config;
    }

    /**
     * Render html page.
     *
     * @return void
     */
    public function renderPage()
    {
        wp_enqueue_script('caseproof_grtl-growth-tools-script', $this->config->assetsUrl . '/main.min.js', []);
        wp_enqueue_style('caseproof_grtl-growth-tools-style', $this->config->assetsUrl . '/main.min.css', []);
        $growthToolsData = $this->getConfig();
        $active  = get_option('active_plugins', []);
        $pluginsStatus = [];

        foreach ($growthToolsData['plugins'] as $k => $plugin) {
            if (!in_array($this->config->instanceId, $plugin['target'], true)) {
                unset($growthToolsData['plugins'][$k]);
                continue;
            }

            $pluginsStatus[$plugin['main']] = 'notinstalled';
            if (is_file(WP_PLUGIN_DIR . '/' . $plugin['main'])) {
                $pluginsStatus[$plugin['main']] = 'installed';

                if (in_array($plugin['main'], $active)) {
                    $pluginsStatus[$plugin['main']] = 'activated';
                }
            }
        }

        $labels = [
            'notinstalled' => esc_html(__('Not Installed', 'members')),
            'installed' => esc_html(__('Installed', 'members')),
            'activated' => esc_html(__('Active', 'members')),
            'active' => esc_html(__('Activate', 'members')),
            'deactive' => esc_html(__('Deactivate', 'members')),
            'install' => esc_html(__('Install', 'members')),
        ];
        $ajaxAction = 'caseproof_growth_tool_plugin_action_' . $this->config->instanceId;
        $baseLogoUrl = $this->config->imageBaseUrl;
        require "views/list.phtml";
    }

    /**
     * Ajax handler for install/activate plugin
     */
    public function pluginAction()
    {
        $growth_tools_data = $this->getConfig();
        $type = sanitize_text_field($_REQUEST['type']);
        $pluginMain = sanitize_text_field($_REQUEST['plugin']);

        if ($type == 'install') {
            foreach ($growth_tools_data['plugins'] as $plugin) {
                if ($plugin['main'] == $pluginMain) {
                    AddonHelper::installAddon($plugin['download_url']);
                }
            }
        } elseif ($type == 'activate') {
            AddonHelper::activateAddon($pluginMain);
        } elseif ($type == 'deactivate') {
            AddonHelper::deactivateAddon($pluginMain);
        }

        exit;
    }
}
