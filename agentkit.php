<?php
/**
 * Plugin Name: AgentKit
 * Description: Chat AI open source para WordPress con RAG, panel admin y soporte multi-provider.
 * Version: 1.0.0
 * Author: AgentKit
 * License: GPL-2.0-or-later
 * Text Domain: agentkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENTKIT_VERSION', '1.0.0' );
define( 'AGENTKIT_FILE', __FILE__ );
define( 'AGENTKIT_PATH', \plugin_dir_path( __FILE__ ) );
define( 'AGENTKIT_URL', \plugin_dir_url( __FILE__ ) );

if ( file_exists( AGENTKIT_PATH . 'vendor/autoload.php' ) ) {
	require_once AGENTKIT_PATH . 'vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class ): void {
		if ( 0 !== strpos( $class, 'AgentKit\\' ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( 'AgentKit\\' ) ) );
		$file     = AGENTKIT_PATH . 'includes/' . $relative . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

\register_activation_hook( AGENTKIT_FILE, array( AgentKit\Core\Activator::class, 'activate' ) );
\register_deactivation_hook( AGENTKIT_FILE, array( AgentKit\Core\Deactivator::class, 'deactivate' ) );

\add_action(
	'plugins_loaded',
	static function (): void {
		$plugin = new AgentKit\Core\Plugin();
		$plugin->run();
	}
);
