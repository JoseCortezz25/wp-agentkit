<?php

namespace AgentKit\AI\Providers;

use Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GeminiProvider extends BaseProvider {
	public function get_available_models(): array {
		return array( 'gemini-3-flash-preview', 'gemini-3-pro-preview', 'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-embedding-2-preview', 'gemini-embedding-001' );
	}

	public function embed( string $text ): array {
		if ( empty( $this->config['api_key'] ) ) {
			return array();
		}

		$data = $this->request_json(
			'https://generativelanguage.googleapis.com/v1beta/models/' . ( $this->config['embedding_model'] ?? 'text-embedding-004' ) . ':embedContent?key=' . rawurlencode( $this->config['api_key'] ),
			array( 'Content-Type' => 'application/json' ),
			array(
				'content' => array(
					'parts' => array(
						array( 'text' => $text ),
					),
				),
			)
		);

		return $data['embedding']['values'] ?? array();
	}

	public function chat( array $messages, array $options ): Generator {
		if ( empty( $this->config['api_key'] ) ) {
			yield $this->provider_not_configured_message( 'Gemini', $options );
			return;
		}

		$parts = array();

		foreach ( $messages as $message ) {
			$parts[] = array( 'text' => strtoupper( (string) $message['role'] ) . ': ' . (string) $message['content'] );
		}

		$data = $this->request_json(
			'https://generativelanguage.googleapis.com/v1beta/models/' . ( $this->config['chat_model'] ?? 'gemini-2.0-flash' ) . ':generateContent?key=' . rawurlencode( $this->config['api_key'] ),
			array( 'Content-Type' => 'application/json' ),
			array(
				'contents' => array(
					array( 'parts' => $parts ),
				),
				'generationConfig' => array(
					'temperature'     => $options['temperature'] ?? 0.2,
					'maxOutputTokens' => $options['max_tokens'] ?? 500,
				),
			)
		);

		$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

		foreach ( preg_split( '/(\s+)/', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE ) as $piece ) {
			if ( '' !== $piece ) {
				yield $piece;
			}
		}
	}
}
