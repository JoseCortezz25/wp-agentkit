<?php

namespace AgentKit\RAG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChunkSplitter {
	public function split( string $text, int $size = 400, int $overlap = 50 ): array {
		$normalized = preg_replace( '/\s+/', ' ', trim( \wp_strip_all_tags( $text ) ) );

		if ( empty( $normalized ) ) {
			return array();
		}

		$words    = preg_split( '/\s+/', $normalized ) ?: array();
		$chunks   = array();
		$position = 0;

		while ( $position < count( $words ) ) {
			$slice = array_slice( $words, $position, $size );
			$text  = trim( implode( ' ', $slice ) );

			if ( '' !== $text ) {
				if ( ! empty( $chunks ) && count( $slice ) < 50 ) {
					$chunks[ count( $chunks ) - 1 ] .= ' ' . $text;
				} else {
					$chunks[] = $text;
				}
			}

			$position += max( 1, $size - $overlap );
		}

		return $chunks;
	}
}
