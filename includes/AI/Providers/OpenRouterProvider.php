<?php

namespace AgentKit\AI\Providers;

use Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenRouterProvider extends BaseProvider {
	public function get_available_models(): array {
		return array( 'openai/gpt-4o-mini', 'anthropic/claude-3.5-haiku' );
	}

	public function embed( string $text ): array {
		if ( empty( $this->config['api_key'] ) ) {
			return array();
		}

		$data = $this->request_json(
			'https://openrouter.ai/api/v1/embeddings',
			array(
				'Authorization' => 'Bearer ' . $this->config['api_key'],
				'Content-Type'  => 'application/json',
			),
			array(
				'input' => $text,
				'model' => $this->config['embedding_model'] ?? 'openai/text-embedding-3-small',
			)
		);

		return $data['data'][0]['embedding'] ?? array();
	}

	public function chat( array $messages, array $options ): Generator {
		if ( empty( $this->config['api_key'] ) ) {
			yield $this->provider_not_configured_message( 'OpenRouter', $options );
			return;
		}

		$data = $this->request_json(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'Authorization' => 'Bearer ' . $this->config['api_key'],
				'Content-Type'  => 'application/json',
			),
			array(
				'model'       => $this->config['chat_model'] ?? 'openai/gpt-4o-mini',
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
