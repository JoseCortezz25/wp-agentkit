<?php

namespace AgentKit\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Migrator {
	public function migrate(): void {
		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( ( new Schema() )->get_sql() as $sql ) {
			\dbDelta( $sql );
		}

		\update_option( 'agentkit_db_version', AGENTKIT_VERSION, false );
	}
}
