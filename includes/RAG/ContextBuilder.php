<?php

namespace AgentKit\RAG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContextBuilder {
	public function build( array $chunks ): string {
		if ( empty( $chunks ) ) {
			return '';
		}

		$parts = array();

		foreach ( $chunks as $chunk ) {
			$parts[] = sprintf(
				"--- Fuente: %s #%d ---\n%s",
				$chunk['source_type'] ?? 'site',
				(int) ( $chunk['source_id'] ?? 0 ),
				$chunk['chunk_text'] ?? ''
			);
		}

		return implode( "\n\n", $parts );
	}
}
