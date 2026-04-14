<?php

namespace AgentKit\RAG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VectorStore {
	public function replace_source_chunks( string $source_type, int $source_id, string $source_url, array $chunks, string $embedding_model = '' ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_chunks';
		$wpdb->delete( $table, array( 'source_type' => $source_type, 'source_id' => $source_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		foreach ( $chunks as $index => $chunk ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				array(
					'source_type'     => $source_type,
					'source_id'       => $source_id,
					'source_url'      => $source_url,
					'chunk_index'     => $index,
					'chunk_text'      => $chunk['text'],
					'embedding'       => \wp_json_encode( $chunk['embedding'] ?? array() ),
					'embedding_model' => $embedding_model,
					'token_count'     => $chunk['token_count'] ?? 0,
					'checksum'        => md5( $chunk['text'] ),
					'indexed_at'      => \current_time( 'mysql', true ),
				),
				array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}
	}

	public function query_candidates( string $query, int $limit = 20 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_chunks';
		$like  = '%' . $wpdb->esc_like( $query ) . '%';
		$sql   = $wpdb->prepare(
			"SELECT id, source_type, source_id, source_url, chunk_text, embedding FROM {$table}
			WHERE chunk_text LIKE %s
			ORDER BY indexed_at DESC
			LIMIT %d",
			$like,
			$limit
		);

		return $wpdb->get_results( $sql, \ARRAY_A ) ?: array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
}
