<?php

namespace AgentKit\RAG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SemanticSearch {
	public function __construct( private VectorStore $store ) {}

	public function search( string $message, array $query_embedding = array(), int $limit = 5 ): array {
		$candidates = $this->store->query_candidates( $message, 25 );

		if ( empty( $query_embedding ) ) {
			return array_slice( $candidates, 0, $limit );
		}

		$ranked = array();

		foreach ( $candidates as $candidate ) {
			$embedding = json_decode( (string) $candidate['embedding'], true );
			$score     = is_array( $embedding ) ? $this->cosine_similarity( $query_embedding, $embedding ) : 0.0;
			$candidate['score'] = $score;
			$ranked[]           = $candidate;
		}

		usort(
			$ranked,
			static fn ( array $a, array $b ): int => $b['score'] <=> $a['score']
		);

		return array_slice( $ranked, 0, $limit );
	}

	private function cosine_similarity( array $a, array $b ): float {
		$len = min( count( $a ), count( $b ) );

		if ( 0 === $len ) {
			return 0.0;
		}

		$dot = 0.0;
		$na  = 0.0;
		$nb  = 0.0;

		for ( $i = 0; $i < $len; $i++ ) {
			$av  = (float) $a[ $i ];
			$bv  = (float) $b[ $i ];
			$dot += $av * $bv;
			$na  += $av * $av;
			$nb  += $bv * $bv;
		}

		if ( 0.0 === $na || 0.0 === $nb ) {
			return 0.0;
		}

		return $dot / ( sqrt( $na ) * sqrt( $nb ) );
	}
}
