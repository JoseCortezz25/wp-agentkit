<?php

namespace AgentKit\API;

use AgentKit\Admin\SettingsManager;
use AgentKit\RAG\FileIndexer;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FilesEndpoint {
	public function __construct( private SettingsManager $settings, private AdminEndpoint $admin ) {}

	public function register_routes(): void {
		\register_rest_route(
			'agentkit/v1',
			'/files',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_files' ),
					'permission_callback' => array( $this->admin, 'authorize_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'register_file' ),
					'permission_callback' => array( $this->admin, 'authorize_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_file' ),
					'permission_callback' => array( $this->admin, 'authorize_admin' ),
				),
			)
		);

		\register_rest_route(
			'agentkit/v1',
			'/files/reindex',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reindex_file' ),
				'permission_callback' => array( $this->admin, 'authorize_admin' ),
			)
		);
	}

	public function list_files() {
		global $wpdb;

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}agentkit_files ORDER BY created_at DESC LIMIT 100", \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared

		return \rest_ensure_response( $rows ?: array() );
	}

	public function register_file( WP_REST_Request $request ) {
		( new FileIndexer( $this->settings ) )->register_attachment( (int) $request->get_param( 'attachment_id' ) );

		return \rest_ensure_response( array( 'success' => true ) );
	}

	public function reindex_file( WP_REST_Request $request ) {
		( new FileIndexer( $this->settings ) )->index_attachment( (int) $request->get_param( 'attachment_id' ) );

		return \rest_ensure_response( array( 'success' => true ) );
	}

	public function delete_file( WP_REST_Request $request ) {
		( new FileIndexer( $this->settings ) )->delete_attachment_index( (int) $request->get_param( 'attachment_id' ) );

		return \rest_ensure_response( array( 'success' => true ) );
	}
}
