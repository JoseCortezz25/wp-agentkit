<?php

namespace AgentKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminMenu {
	public function register(): void {
		\add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	public function add_menu(): void {
		\add_menu_page(
			'AgentKit',
			'AgentKit',
			'manage_options',
			'agentkit',
			array( $this, 'render_page' ),
			'dashicons-format-chat',
			58
		);
	}

	public function render_page(): void {
		echo '<div class="wrap"><div id="agentkit-admin-root"></div></div>';
	}
}
