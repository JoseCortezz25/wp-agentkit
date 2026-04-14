<?php

namespace AgentKit\API;

use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConversationsEndpoint {
	public function __construct( private AdminEndpoint $admin ) {}

	public function register_routes(): void {
		\register_rest_route(
			'agentkit/v1',
			'/conversations',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_conversations' ),
					'permission_callback' => array( $this->admin, 'authorize_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_conversation' ),
					'permission_callback' => array( $this->admin, 'authorize_admin' ),
				),
			)
		);

		\register_rest_route(
			'agentkit/v1',
			'/conversations/messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_messages' ),
				'permission_callback' => array( $this->admin, 'authorize_admin' ),
			)
		);
	}

	public function list_conversations() {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_conversations';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY last_message_at DESC LIMIT 50", \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared

		return \rest_ensure_response( $rows ?: array() );
	}

	public function delete_conversation( WP_REST_Request $request ) {
		global $wpdb;

		$id       = (int) $request->get_param( 'id' );
		$table    = $wpdb->prefix . 'agentkit_conversations';
		$messages = $wpdb->prefix . 'agentkit_messages';

		if ( $id > 0 ) {
			$wpdb->delete( $messages, array( 'conversation_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		return \rest_ensure_response( array( 'success' => true ) );
	}

	public function get_messages( WP_REST_Request $request ) {
		global $wpdb;

		$id   = (int) $request->get_param( 'id' );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}agentkit_messages WHERE conversation_id = %d ORDER BY created_at ASC", $id ), \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return \rest_ensure_response( $rows ?: array() );
	}
}
