<?php

namespace AgentKit\API;

use AgentKit\Admin\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestController {
	public function __construct( private SettingsManager $settings ) {}

	public function register(): void {
		\add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		$admin = new AdminEndpoint( $this->settings );

		( new ChatEndpoint( $this->settings ) )->register_routes();
		( new ChatStreamEndpoint( $this->settings ) )->register_routes();
		$admin->register_routes();
		( new FilesEndpoint( $this->settings, $admin ) )->register_routes();
		( new StatsEndpoint( $admin ) )->register_routes();
		( new ConversationsEndpoint( $admin ) )->register_routes();
	}
}
