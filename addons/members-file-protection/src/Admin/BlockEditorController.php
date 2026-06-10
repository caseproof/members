<?php
/**
 * Block editor media modal integration.
 *
 * @package MembersFileProtection
 */

namespace Members\FileProtection\Admin;

defined( 'ABSPATH' ) || exit;

use Members\FileProtection\Capabilities;

/**
 * Adds a protection badge in the block editor media library.
 */
class BlockEditorController {

	/**
	 * @return void
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! Capabilities::currentUserCanManage() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'members-file-protection-admin',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/css/admin.css',
			array( 'dashicons' ),
			'1.0.6'
		);

		wp_enqueue_script(
			'members-file-protection-block-editor',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/block-editor.js',
			array( 'wp-hooks', 'wp-i18n' ),
			'1.0.0',
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'members-file-protection-block-editor', 'members' );
		}
	}
}
