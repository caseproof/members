<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;

class JoomlaInstaller extends BaseInstaller
{
    protected $locations = array(
        'component'    => 'components/{$name}/',
        'module'       => 'modules/{$name}/',
        'template'     => 'templates/{$name}/',
        'plugin'       => 'plugins/{$name}/',
        'library'      => 'libraries/{$name}/',
    );

    // TODO: Add inflector for mod_ and com_ names
}
