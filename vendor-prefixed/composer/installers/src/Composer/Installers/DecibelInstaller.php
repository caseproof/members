<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;

class DecibelInstaller extends BaseInstaller
{
    /** @var array */
    protected $locations = array(
        'app'    => 'app/{$name}/',
    );
}
