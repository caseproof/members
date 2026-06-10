<?php
/**
 * Server configuration contract.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Writes and validates server rewrite configuration.
 */
interface ServerConfigInterface {

	/**
	 * Writes rewrite rules for the given extensions.
	 *
	 * @param string[] $extensions File extensions without dots.
	 * @return bool True on success.
	 */
	public function write( array $extensions ): bool;

	/**
	 * Removes plugin rewrite rules.
	 *
	 * @return bool
	 */
	public function remove(): bool;

	/**
	 * Whether rewrite protection appears active.
	 *
	 * @return bool
	 */
	public function isActive(): bool;

	/**
	 * Returns the configuration block for manual installation.
	 *
	 * @param string[] $extensions File extensions without dots.
	 * @return string
	 */
	public function getConfigBlock( array $extensions ): string;

	/**
	 * Whether the written rules cover the given extensions.
	 *
	 * @param string[] $extensions File extensions without dots.
	 * @return bool
	 */
	public function matchesExtensions( array $extensions ): bool;

	/**
	 * Human-readable server label.
	 *
	 * @return string
	 */
	public function getServerLabel(): string;
}
