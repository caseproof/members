<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Members\Composer\Installers;

class SiteDirectInstaller extends BaseInstaller
{
    protected $locations = array(
        'module' => 'modules/{$vendor}/{$name}/',
        'plugin' => 'plugins/{$vendor}/{$name}/'
    );

    public function inflectPackageVars($vars)
    {
        return $this->parseVars($vars);
    }

    protected function parseVars($vars)
    {
        $vars['vendor'] = strtolower($vars['vendor']) == 'sitedirect' ? 'SiteDirect' : $vars['vendor'];
        $vars['name'] = str_replace(array('-', '_'), ' ', $vars['name']);
        $vars['name'] = str_replace(' ', '', ucwords($vars['name']));

        return $vars;
    }
}
