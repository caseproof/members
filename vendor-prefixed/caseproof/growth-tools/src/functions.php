<?php
/**
 * @license GPL-3.0
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Members\Caseproof\GrowthTools;

/**
 * Returns the main instance of the plugin.
 *
 * @param  array $config Config data.
 * @return Members\Caseproof\GrowthTools\Bootstrap
 */
function instance(array $config = []): App
{
    static $instance = null;

    if (is_null($instance)) {
        $instance = new App(new Config($config));
    }

    return $instance;
}
