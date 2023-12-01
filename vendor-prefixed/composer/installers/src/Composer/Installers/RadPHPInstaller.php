<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;

class RadPHPInstaller extends BaseInstaller
{
    protected $locations = array(
        'bundle' => 'src/{$name}/'
    );

    /**
     * Format package name to CamelCase
     */
    public function inflectPackageVars($vars)
    {
        $nameParts = explode('/', $vars['name']);
        foreach ($nameParts as &$value) {
            $value = strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $value));
            $value = str_replace(array('-', '_'), ' ', $value);
            $value = str_replace(' ', '', ucwords($value));
        }
        $vars['name'] = implode('/', $nameParts);
        return $vars;
    }
}
