<?php
/**
 * @license MIT
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */
namespace Members\Composer\Installers;

class TheliaInstaller extends BaseInstaller
{
    protected $locations = array(
        'module'                => 'local/modules/{$name}/',
        'frontoffice-template'  => 'templates/frontOffice/{$name}/',
        'backoffice-template'   => 'templates/backOffice/{$name}/',
        'email-template'        => 'templates/email/{$name}/',
    );
}
