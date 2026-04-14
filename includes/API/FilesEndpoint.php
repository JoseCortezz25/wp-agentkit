<?php

namespace AgentKit\API;

use AgentKit\Admin\SettingsManager;
use AgentKit\RAG\FileIndexer;
use WP_Error;
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

		\register_rest_route(
			'agentkit/v1',
			'/files/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_file' ),
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
		$attachment_id = (int) $request->get_param( 'attachment_id' );

		if ( $attachment_id <= 0 ) {
			return new WP_Error( 'agentkit_invalid_attachment', 'Attachment ID is required.', array( 'status' => 400 ) );
		}

		( new FileIndexer( $this->settings ) )->register_attachment( $attachment_id );

		return \rest_ensure_response(
			array(
				'success'       => true,
				'attachment_id' => $attachment_id,
				'file'          => $this->find_registered_file( $attachment_id ),
			)
		);
	}

	public function reindex_file( WP_REST_Request $request ) {
		$attachment_id = (int) $request->get_param( 'attachment_id' );

		if ( $attachment_id <= 0 ) {
			return new WP_Error( 'agentkit_invalid_attachment', 'Attachment ID is required.', array( 'status' => 400 ) );
		}

		( new FileIndexer( $this->settings ) )->index_attachment( $attachment_id );

		return \rest_ensure_response( array( 'success' => true, 'attachment_id' => $attachment_id ) );
	}

	public function delete_file( WP_REST_Request $request ) {
		$attachment_id = (int) $request->get_param( 'attachment_id' );

		if ( $attachment_id <= 0 ) {
			return new WP_Error( 'agentkit_invalid_attachment', 'Attachment ID is required.', array( 'status' => 400 ) );
		}

		( new FileIndexer( $this->settings ) )->delete_attachment_index( $attachment_id );

		return \rest_ensure_response( array( 'success' => true, 'attachment_id' => $attachment_id ) );
	}

	public function upload_file( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;

		if ( ! is_array( $file ) || empty( $file['name'] ) ) {
			return new WP_Error( 'agentkit_missing_file', 'No file was uploaded.', array( 'status' => 400 ) );
		}

		if ( (int) ( $file['error'] ?? \UPLOAD_ERR_OK ) !== \UPLOAD_ERR_OK ) {
			return new WP_Error( 'agentkit_upload_failed', $this->upload_error_message( (int) $file['error'] ), array( 'status' => 400 ) );
		}

		$allowed_types = $this->get_allowed_types();
		$max_file_size = max( 1, (int) $this->settings->get( 'files.max_file_size', 10485760 ) );
		$max_total     = max( 1, (int) $this->settings->get( 'files.max_total_files', 50 ) );
		$file_size     = (int) ( $file['size'] ?? 0 );
		$extension     = strtolower( (string) pathinfo( (string) $file['name'], \PATHINFO_EXTENSION ) );

		if ( '' === $extension || ! in_array( $extension, $allowed_types, true ) ) {
			return new WP_Error(
				'agentkit_file_type_not_allowed',
				sprintf( 'File type .%s is not allowed. Allowed: %s.', $extension ?: '?', implode( ', ', $allowed_types ) ),
				array( 'status' => 400 )
			);
		}

		if ( $file_size <= 0 || $file_size > $max_file_size ) {
			return new WP_Error(
				'agentkit_file_too_large',
				sprintf( 'File exceeds max size (%d bytes).', $max_file_size ),
				array( 'status' => 400 )
			);
		}

		if ( $this->count_registered_files() >= $max_total ) {
			return new WP_Error(
				'agentkit_max_files_reached',
				sprintf( 'Maximum number of files reached (%d).', $max_total ),
				array( 'status' => 400 )
			);
		}

		if ( ! function_exists( '\\media_handle_upload' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
			require_once \ABSPATH . 'wp-admin/includes/media.php';
			require_once \ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = \media_handle_upload( 'file', 0, array(), array( 'test_form' => false ) );

		if ( \is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'agentkit_media_upload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
		}

		( new FileIndexer( $this->settings ) )->register_attachment( (int) $attachment_id );

		return \rest_ensure_response(
			array(
				'success'    => true,
				'message'    => 'File uploaded and queued for indexing.',
				'attachment' => array(
					'id'   => (int) $attachment_id,
					'name' => basename( (string) \get_attached_file( (int) $attachment_id ) ),
					'url'  => (string) \wp_get_attachment_url( (int) $attachment_id ),
				),
				'file'       => $this->find_registered_file( (int) $attachment_id ),
			)
		);
	}

	private function get_allowed_types(): array {
		$allowed = array_map(
			static fn ( mixed $type ): string => strtolower( trim( (string) $type ) ),
			(array) $this->settings->get( 'files.allowed_types', array() )
		);

		$allowed = array_values( array_filter( $allowed, static fn ( string $type ): bool => '' !== $type ) );

		return ! empty( $allowed ) ? $allowed : array( 'pdf', 'docx', 'pptx', 'txt', 'md', 'csv' );
	}

	private function count_registered_files(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_files';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
	}

	private function find_registered_file( int $attachment_id ): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_files';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE attachment_id = %d", $attachment_id ), \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return is_array( $row ) ? $row : null;
	}

	private function upload_error_message( int $error_code ): string {
		return match ( $error_code ) {
			\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
			\UPLOAD_ERR_PARTIAL => 'The file upload was interrupted. Please try again.',
			\UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
			default => 'Could not upload the file. Please try again.',
		};

	}
}
