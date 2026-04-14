<?php

namespace AgentKit\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {
	public static function deactivate(): void {
		\wp_clear_scheduled_hook( 'agentkit_daily_reindex' );
		\wp_clear_scheduled_hook( 'agentkit_process_pending_files' );
	}
}
