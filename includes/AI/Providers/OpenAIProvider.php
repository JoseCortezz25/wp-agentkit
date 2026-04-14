<?php

namespace AgentKit\AI\Providers;

use Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenAIProvider extends BaseProvider {
	public function get_available_models(): array {
		return array( 'gpt-4o-mini', 'gpt-4.1-mini', 'text-embedding-3-small' );
	}

	public function embed( string $text ): array {
		if ( empty( $this->config['api_key'] ) ) {
			return array();
		}

		$data = $this->request_json(
			'https://api.openai.com/v1/embeddings',
			array(
				'Authorization' => 'Bearer ' . $this->config['api_key'],
				'Content-Type'  => 'application/json',
			),
			array(
				'input' => $text,
				'model' => $this->config['embedding_model'] ?? 'text-embedding-3-small',
			)
		);

		return $data['data'][0]['embedding'] ?? array();
	}

	public function chat( array $messages, array $options ): Generator {
		if ( empty( $this->config['api_key'] ) ) {
			$fallback = $this->provider_not_configured_message( 'OpenAI', $options );
			foreach ( preg_split( '/\s+/', $fallback ) as $token ) {
				yield $token . ' ';
			}
			return;
		}

		$data = $this->request_json(
			'https://api.openai.com/v1/chat/completions',
			array(
				'Authorization' => 'Bearer ' . $this->config['api_key'],
				'Content-Type'  => 'application/json',
			),
			array(
				'model'       => $this->config['chat_model'] ?? 'gpt-4o-mini',
				'messages'    => $messages,
				'temperature' => $options['temperature'] ?? 0.2,
				'max_tokens'  => $options['max_tokens'] ?? 500,
			)
		);

		$text = $data['choices'][0]['message']['content'] ?? '';

		foreach ( preg_split( '/(\s+)/', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE ) as $piece ) {
			if ( '' !== $piece ) {
				yield $piece;
			}
		}
	}
}
