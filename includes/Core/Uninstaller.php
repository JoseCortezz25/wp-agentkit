<?php

namespace AgentKit\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Uninstaller {
	public static function uninstall(): void {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'agentkit_conversations',
			$wpdb->prefix . 'agentkit_messages',
			$wpdb->prefix . 'agentkit_chunks',
			$wpdb->prefix . 'agentkit_files',
			$wpdb->prefix . 'agentkit_stats_daily',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		}

		\delete_option( 'agentkit_settings' );
		\delete_option( 'agentkit_version' );
	}
}
