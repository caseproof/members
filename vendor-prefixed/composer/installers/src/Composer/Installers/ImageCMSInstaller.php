<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;

class ImageCMSInstaller extends BaseInstaller
{
    protected $locations = array(
        'template'    => 'templates/{$name}/',
        'module'      => 'application/modules/{$name}/',
        'library'     => 'application/libraries/{$name}/',
    );
}
