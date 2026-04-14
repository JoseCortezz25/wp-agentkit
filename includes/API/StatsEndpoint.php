<?php

namespace AgentKit\API;

use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StatsEndpoint {
	public function __construct( private AdminEndpoint $admin ) {}

	public function register_routes(): void {
		\register_rest_route(
			'agentkit/v1',
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this->admin, 'authorize_admin' ),
			)
		);
	}

	public function get_stats() {
		global $wpdb;

		$conversations = $wpdb->prefix . 'agentkit_conversations';
		$messages      = $wpdb->prefix . 'agentkit_messages';
		$chunks        = $wpdb->prefix . 'agentkit_chunks';
		$files         = $wpdb->prefix . 'agentkit_files';
		$daily         = $wpdb->prefix . 'agentkit_stats_daily';
		$series        = $wpdb->get_results( "SELECT stat_date, conversations, messages, unique_sessions, avg_messages_per_conv FROM {$daily} ORDER BY stat_date DESC LIMIT 30", \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$top_questions = $wpdb->get_results( "SELECT content, COUNT(*) AS total FROM {$messages} WHERE role = 'user' GROUP BY content ORDER BY total DESC LIMIT 10", \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		$file_statuses = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$files} GROUP BY status", \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared

		return \rest_ensure_response(
			array(
				'conversations' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$conversations}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
				'messages'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$messages}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
				'chunks'        => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$chunks}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
				'files'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$files}" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
				'indexedFiles'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$files} WHERE status = 'indexed'" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
				'errorFiles'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$files} WHERE status = 'error'" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
				'dailySeries'   => array_reverse( $series ?: array() ),
				'topQuestions'  => $top_questions ?: array(),
				'fileStatuses'  => $file_statuses ?: array(),
			)
		);
	}
}
