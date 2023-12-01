<?php
/**
 * @license GPL-3.0
 *
 * Modified by Justin Tadlock on 01-December-2023 using Strauss.
 * @see https://github.com/BrianHenryIE/strauss
 */

namespace Members\Caseproof\GrowthTools\Helper;

/**
 * WordPress class extended for on-the-fly add-on installations.
 */
class AddonInstallSkin extends \WP_Upgrader_Skin
{
    /**
     * Set upgrader.
     *
     * @param \WP_Upgrader $upgrader Upgrader object.
     */
    /** @codingStandardsIgnoreStart  */
    public function set_upgrader(&$upgrader)
    {
        /** @codingStandardsIgnoreEnd */
        if (is_object($upgrader)) {
            $this->upgrader =& $upgrader;
        }
    }

    /**
     * Empty out the header of its HTML content and only check to see if it has
     * been performed or not.
     */
    public function header()
    {
    }

    /**
     * Empty out the footer of its HTML contents.
     */
    public function footer()
    {
    }

    /**
     * Instead of outputting HTML for errors, json_encode the errors and send them
     * back to the Ajax script for processing.
     *
     * @param array $errors Array of errors with the install process.
     */
    public function error(array $errors)
    {
        if (!empty($errors)) {
            wp_send_json_error($errors);
        }
    }

    /**
     * Empty out the feedback method to prevent outputting HTML strings as the install
     * is progressing.
     *
     * @param string $string  The feedback string.
     * @param mixed  ...$args Optional text replacements.
     */
    public function feedback(string $string, ...$args)
    {
    }

    /**
     * Empty out JavaScript output that calls function to decrement the update counts.
     *
     * @param string $type Type of update count to decrement.
     */
    /** @codingStandardsIgnoreStart */
    public function decrement_update_count(string $type)
    {
        /** @codingStandardsIgnoreEnd */
    }
}
