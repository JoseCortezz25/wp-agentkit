<?php

namespace AgentKit\RAG;

use AgentKit\Admin\SettingsManager;
use AgentKit\AI\ProviderFactory;
use AgentKit\Parsers\ParserFactory;
use AgentKit\Support\Language;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FileIndexer {
	public function __construct( private SettingsManager $settings ) {}

	public function register_attachment( int $attachment_id ): void {
		$file_path = \get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$allowed   = (array) $this->settings->get( 'files.allowed_types', array() );
		$max_size  = max( 1, (int) $this->settings->get( 'files.max_file_size', 10485760 ) );
		$max_total = max( 1, (int) $this->settings->get( 'files.max_total_files', 50 ) );
		$file_size = (int) filesize( $file_path );

		if ( ! in_array( $extension, $allowed, true ) ) {
			return;
		}

		if ( $file_size <= 0 || $file_size > $max_size ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentkit_files';
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE attachment_id = %d", $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( $exists > 0 ) {
			return;
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared

		if ( $total >= $max_total ) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'attachment_id' => $attachment_id,
				'original_name' => basename( $file_path ),
				'file_type'     => $extension,
				'file_size'     => $file_size,
				'chunk_count'   => 0,
				'status'        => 'pending',
				'uploaded_by'   => (int) \get_current_user_id(),
				'created_at'    => \current_time( 'mysql', true ),
			)
		);

		if ( ! \wp_next_scheduled( 'agentkit_process_pending_files' ) ) {
			\wp_schedule_single_event( \time() + 5, 'agentkit_process_pending_files' );
		}
	}

	public function process_pending_files(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_files';
		$files = $wpdb->get_results( "SELECT * FROM {$table} WHERE status IN ('pending','indexing') ORDER BY created_at ASC LIMIT 5", \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $files as $file ) {
			$this->index_attachment( (int) $file['attachment_id'] );
		}
	}

	public function index_attachment( int $attachment_id ): void {
		global $wpdb;

		$table     = $wpdb->prefix . 'agentkit_files';
		$file_path = \get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->mark_error( $attachment_id, Language::file_not_found( (string) $this->settings->get( 'general.base_language', 'en' ) ) );
			return;
		}

		$wpdb->update( $table, array( 'status' => 'indexing', 'error_message' => '' ), array( 'attachment_id' => $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		try {
			$parser   = ParserFactory::make( $file_path );
			$text     = $parser->parse( $file_path );
			$splitter = new ChunkSplitter();
			$provider = ProviderFactory::make( $this->settings );
			$chunks   = array();

			foreach ( $splitter->split( $text, (int) \apply_filters( 'agentkit_chunk_size', 400 ) ) as $chunk_text ) {
				$chunks[] = array(
					'text'        => $chunk_text,
					'embedding'   => $provider->embed( $chunk_text ),
					'token_count' => str_word_count( $chunk_text ),
				);
			}

			( new VectorStore() )->replace_source_chunks( 'file', $attachment_id, (string) \wp_get_attachment_url( $attachment_id ), $chunks, (string) $this->settings->get( 'provider.embedding_model', '' ) );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				array(
					'chunk_count'   => count( $chunks ),
					'status'        => 'indexed',
					'indexed_at'    => \current_time( 'mysql', true ),
					'error_message' => '',
				),
				array( 'attachment_id' => $attachment_id )
			);
		} catch ( \Throwable $throwable ) {
			$this->mark_error( $attachment_id, $throwable->getMessage() );
		}
	}

	public function delete_attachment_index( int $attachment_id ): void {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'agentkit_files', array( 'attachment_id' => $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->delete( $wpdb->prefix . 'agentkit_chunks', array( 'source_type' => 'file', 'source_id' => $attachment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}

	private function mark_error( int $attachment_id, string $message ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->prefix . 'agentkit_files',
			array(
				'status'        => 'error',
				'error_message' => $message,
			),
			array( 'attachment_id' => $attachment_id )
		);
	}
}
