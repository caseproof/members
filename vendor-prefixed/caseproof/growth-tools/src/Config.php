<?php

namespace Members\Caseproof\GrowthTools;

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
    protected array $pluginsConfig = [];

    /**
     * Extra inline css
     *
     * @var string
     */
    protected $customInlineCSS = '';

    /**
     * Constructor
     *
     * @param array $params An array of configuration parameters {
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
        return isset($this->$key) ? $this->$key : null;
    }

    /**
     * Getter for config
     *
     * @return array
     */
    public function getPluginsConfig(): array
    {
        if (! empty($this->pluginsConfig)) {
            return $this->pluginsConfig;
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
            // Check for which version the plugins are using free or premium.
            foreach ($config['plugins'] as $k => &$plugin) {
                if (! empty($this->instanceId) && ! in_array($this->instanceId, $plugin['target'], true)) {
                    unset($config['plugins'][$k]);
                    continue;
                }
                $premiumMainFile = $plugin['main']['premium'] ?? '';
                $freeMainFile = $plugin['main']['free'] ?? '';
                $bothInactive = !is_plugin_active($premiumMainFile) && !is_plugin_active($freeMainFile);
                $premiumActive = is_plugin_active($premiumMainFile);
                $plugin['settings_url'] = $plugin['settings_page']['free'] ?? '';
                $plugin['plugin_file']  = $freeMainFile;

                if (in_array($premiumMainFile, $existingPlugins, true) && ($premiumActive || $bothInactive)) {
                    $plugin['plugin_file']  = $premiumMainFile;
                    $plugin['settings_url'] = $plugin['settings_page']['premium'] ?? '';
                }

                if (!empty($plugin['settings_url'])) {
                    $plugin['settings_url'] = get_admin_url() . $plugin['settings_url'];
                }
            }
        }

        $this->pluginsConfig = $config;
        return $config;
    }

    /**
     * Get plugin status.
     *
     * @return array
     */
    public function getPluginsStatus(): array
    {
        $growthToolsData = $this->getPluginsConfig();
        $pluginsStatus = [];
        if (empty($growthToolsData)) {
            return $pluginsStatus;
        }

        $existingPlugins = array_keys(get_plugins());
        foreach ($growthToolsData['plugins'] as $k => $plugin) {
            $pluginsStatus[$plugin['plugin_file']] = 'notinstalled';
            if (in_array($plugin['plugin_file'], $existingPlugins, true)) {
                $pluginsStatus[$plugin['plugin_file']] = 'installed';

                if (is_plugin_active($plugin['plugin_file'])) {
                    $pluginsStatus[$plugin['plugin_file']] = 'activated';
                }
            }
        }

        return $pluginsStatus;
    }
}
