<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 05-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;


class OsclassInstaller extends BaseInstaller 
{
    
    protected $locations = array(
        'plugin' => 'oc-content/plugins/{$name}/',
        'theme' => 'oc-content/themes/{$name}/',
        'language' => 'oc-content/languages/{$name}/',
    );
    
}
