<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 05-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Members\Composer\Installers;

class TastyIgniterInstaller extends BaseInstaller
{
    protected $locations = array(
        'extension' => 'extensions/{$vendor}/{$name}/',
        'theme' => 'themes/{$name}/',
    );

    /**
     * Format package name.
     *
     * Cut off leading 'ti-ext-' or 'ti-theme-' if present.
     * Strip vendor name of characters that is not alphanumeric or an underscore
     *
     */
    public function inflectPackageVars($vars)
    {
        if ($vars['type'] === 'tastyigniter-extension') {
            $vars['vendor'] = preg_replace('/[^a-z0-9_]/i', '', $vars['vendor']);
            $vars['name'] = preg_replace('/^ti-ext-/', '', $vars['name']);
        }

        if ($vars['type'] === 'tastyigniter-theme') {
            $vars['name'] = preg_replace('/^ti-theme-/', '', $vars['name']);
        }

        return $vars;
    }
}