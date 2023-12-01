<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;

class PlentymarketsInstaller extends BaseInstaller
{
    protected $locations = array(
        'plugin'   => '{$name}/'
    );

    /**
     * Remove hyphen, "plugin" and format to camelcase
     * @param array $vars
     *
     * @return array
     */
    public function inflectPackageVars($vars)
    {
        $vars['name'] = explode("-", $vars['name']);
        foreach ($vars['name'] as $key => $name) {
            $vars['name'][$key] = ucfirst($vars['name'][$key]);
            if (strcasecmp($name, "Plugin") == 0) {
                unset($vars['name'][$key]);
            }
        }
        $vars['name'] = implode("",$vars['name']);

        return $vars;
    }
}
