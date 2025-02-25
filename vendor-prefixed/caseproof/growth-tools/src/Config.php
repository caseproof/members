<?php

declare(strict_types=1);

namespace Members\Caseproof\GrowthTools;

use Members\Caseproof\GrowthTools\Helper\AddonHelper;

class Config
{
    /**
     * The product ID ie memberpress, easy-affiliate
     *
     * @var string
     */
    protected string $instanceId = '';

    /**
     * The path to the plugin main file.
     *
     * @var string
     */
    protected string $file;

    /**
     * The plugin path.
     *
     * @var string
     */
    protected string $path;

    /**
     * The menu path.
     *
     * @var string
     */
    protected string $menuSlug;

    /**
     * The full URL to the plugin's assets path.
     *
     * @var string
     */
    protected string $assetsUrl;

    /**
     * Denotes the slug of the admin menu's parent menu.
     *
     * @var string
     */
    protected string $parentMenuSlug = 'tools.php';

    /**
     * List of css classes for action buttons.
     *
     * @var string[]
     */
    protected array $buttonCSSClasses;

    /**
     * Base URl for images
     *
     * @var string
     */
    protected string $imageBaseUrl = 'https://cspf-growth-tools.s3.us-east-2.amazonaws.com/img';

    /**
     * Url to configuration file
     *
     * @var string
     */
    protected string $configFileUrl = 'https://cspf-growth-tools.s3.us-east-2.amazonaws.com/v2/tools.json';

    /**
     * Function to render header.
     *
     * @var callable
     */
    public $headerHtmlCallback = [App::class, 'getHeaderHtml'];

    /**
     * Store plugins main file and setting page url.
     *
     * @var array
     */
    protected array $addonsConfig = [];

    /**
     * Extra inline css.
     *
     * @var string
     */
    protected $customInlineCSS = '';

    /**
     * Constructor
     *
     * @param array $params An array of configuration parameters {.
     *
     * @type string $file Required. The path to the plugin main file.
     * @type string $path Optional. The plugin path. Defaults to dirname($file)
     *                                  if not supplied.
     * @type string $assetsUrl Optional. The full URL to the plugin's assets
     *                                  path. Defaults to plugins_url('dist', $path)
     *                                  if not supplied.
     * @type string $parentMenuSlug Optional. Denotes the slug of the admin
     *                                  menu's parent menu. Defaults to 'tools.php'
     *                                  if not supplied.
     * }
     */
    public function __construct(array $params)
    {
        $this->file = dirname(__DIR__) . '/growth-tools.php';
        foreach ($params as $key => $value) {
            $this->$key = $value;
        }

        if (empty($this->path)) {
            $this->path = dirname($this->file);
        }

        if (empty($this->assetsUrl)) {
            $this->assetsUrl = plugins_url('src/dist', $this->file);
        }

        if (empty($this->buttonCSSClasses)) {
            $this->buttonCSSClasses = ['button', 'button-primary'];
        }
    }

    /**
     * Retrieves a configuration value.
     *
     * @param  string $key The configuration key.
     * @return mixed Returns the configuration value if it exists or null if the
     *               configuration value does not exist.
     */
    public function __get(string $key)
    {
        if ($key === 'pluginsConfig' || $key === 'themesConfig') {
            return $key === 'pluginsConfig' ? $this->getPluginsConfig() : $this->getThemesConfig();
        }
        return $this->$key ?? null;
    }

    /**
     * Set a configuration value.
     *
     * @param  string $key   The configuration key.
     * @param  mixed  $value Value.
     * @return mixed Returns the configuration value if it exists or null if the
     *               configuration value does not exist.
     */
    public function __set(string $key, $value) // phpcs:ignore Squiz.Commenting.FunctionComment.ScalarTypeHintMissing -- `mixed` type
    {
        if ($key === 'pluginsConfig' || $key === 'themesConfig') {
            _doing_it_wrong(
                __METHOD__,
                'Direct modification of pluginsConfig and themesConfig is deprecated.',
                '1.4.0'
            );
            return;
        }
        $this->$key = $value;
    }

    /**
     * Getter for config
     *
     * @return array
     */
    public function getAddonsConfig(): array
    {
        if (! empty($this->addonsConfig)) {
            return $this->addonsConfig;
        }

        $config = get_transient('caseproof_growth_tools_configuration_data_v2');
        if (false === $config || $config === null) {
            $config   = [];
            $response = wp_remote_get($this->configFileUrl);
            if (! is_wp_error($response)) {
                $config = isset($response['body']) ? json_decode($response['body'], true) : $response;
                set_transient('caseproof_growth_tools_configuration_data_v2', $config, 24 * HOUR_IN_SECONDS);
            }
        }

        if (! empty($config)) {
            $existingPlugins = array_keys(get_plugins());
            $existingThemes  = array_keys(wp_get_themes());
            // Check for which version the plugins are using free or premium.
            // Processing addon data.
            $config['plugins'] = $this->processAddons($config['plugins'] ?? [], $existingPlugins);
            $config['themes']  = $this->processAddons($config['themes'] ?? [], $existingThemes, true);
        }
        // The addonsConfig will have the plugin and theme both data.
        $this->addonsConfig = $config;
        return $config;
    }

    /**
     * Retrieve the plugins configuration from the addons configuration.
     *
     * @return array The configuration settings for plugins.
     */
    public function getPluginsConfig(): array
    {
        return $this->getAddonsConfig()['plugins'] ?? [];
    }

    /**
     * Retrieve the themes configuration from the addons configuration.
     *
     * @return array The configuration settings for themes.
     */
    public function getThemesConfig(): array
    {
        return $this->getAddonsConfig()['themes'] ?? [];
    }

    /**
     * Get Addons status.
     *
     * @return array
     */
    public function getAddonsStatus(): array
    {
        $growthToolsData = $this->getAddonsConfig();
        $pluginsStatus   = [];
        if (empty($growthToolsData)) {
            return $pluginsStatus;
        }

        $existingPlugins = array_keys(get_plugins());
        $existingThemes  = array_keys(wp_get_themes());

        // Process plugins.
        $pluginsStatus = is_array($growthToolsData['plugins'] ?? false) ?
            AddonHelper::processAddonStatus(
                $growthToolsData['plugins'],
                $existingPlugins,
                'is_plugin_active'
            ) : [];

        // Process themes.
        $themesStatus = is_array($growthToolsData['themes'] ?? false) ?
            AddonHelper::processAddonStatus(
                $growthToolsData['themes'],
                $existingThemes,
                [AddonHelper::class, 'isThemeActive']
            ) : [];

        $addonsStatus['plugins'] = $pluginsStatus;
        $addonsStatus['themes']  = $themesStatus;

        return $addonsStatus;
    }

    /**
     * Get plugin status.
     *
     * @return array
     */
    public function getPluginsStatus(): array
    {
        return $this->getAddonsStatus()['plugins'];
    }

    /**
     * Process add-ons from configuration data.
     *
     * Processes the add-ons data from the configuration data.
     * Filters out add-ons that are not targeted at the current site.
     * and determines which version of the add-on is active (free or premium).
     *
     * @param  array   $addons         The add-ons to process.
     * @param  array   $existingAddons An array of existing add-ons, with the main plugin file as the key.
     * @param  boolean $isTheme        Whether the add-ons are themes or plugins.
     * @return array The processed add-ons.
     */
    protected function processAddons(array $addons, array $existingAddons, bool $isTheme = false): array
    {
        foreach ($addons as $k => &$addon) {
            if (! empty($this->instanceId) && ! in_array($this->instanceId, $addon['target'], true)) {
                unset($addons[$k]);
                continue;
            }
            $premiumMainFile = $addon['main']['premium'] ?? '';
            $freeMainFile    = $addon['main']['free'] ?? '';

            $bothInactive = $isTheme ?
                !AddonHelper::isThemeActive($premiumMainFile) && !AddonHelper::isThemeActive($freeMainFile)
                : !is_plugin_active($premiumMainFile) && !is_plugin_active($freeMainFile);

            $premiumActive = $bothInactive ? false :
                ($isTheme ?
                    AddonHelper::isThemeActive($premiumMainFile)
                    : is_plugin_active($premiumMainFile)
                );

            $addon['settings_url'] = $addon['settings_page']['free'] ?? '';
            $addon['addon_file']   = $freeMainFile;
            $addon['addon_type']   = $isTheme ? 'theme' : 'plugin';

            if (in_array($premiumMainFile, $existingAddons, true) && ($premiumActive || $bothInactive)) {
                $addon['addon_file']   = $premiumMainFile;
                $addon['settings_url'] = $addon['settings_page']['premium'] ?? '';
            }

            if (!empty($addon['settings_url'])) {
                $addon['settings_url'] = get_admin_url() . $addon['settings_url'];
            }
        }
        return $addons;
    }
}
