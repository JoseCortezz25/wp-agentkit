<?php

namespace AgentKit\AI\Providers;

use Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AnthropicProvider extends BaseProvider {
	public function get_available_models(): array {
		return array( 'claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5' );
	}

	public function embed( string $text ): array {
		unset( $text );

		return array();
	}

	public function chat( array $messages, array $options ): Generator {
		if ( empty( $this->config['api_key'] ) ) {
			yield $this->provider_not_configured_message( 'Anthropic', $options );
			return;
		}

		$system = '';
		$mapped = array();

		foreach ( $messages as $message ) {
			if ( 'system' === ( $message['role'] ?? '' ) ) {
				$system = (string) $message['content'];
				continue;
			}

			$mapped[] = array(
				'role'    => $message['role'],
				'content' => array(
					array(
						'type' => 'text',
						'text' => (string) $message['content'],
					),
				),
			);
		}

		$data = $this->request_json(
			'https://api.anthropic.com/v1/messages',
			array(
				'x-api-key'         => $this->config['api_key'],
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			array(
				'model'       => $this->config['chat_model'] ?? 'claude-3-5-haiku-latest',
				'system'      => $system,
				'messages'    => $mapped,
				'max_tokens'  => $options['max_tokens'] ?? 500,
				'temperature' => $options['temperature'] ?? 0.2,
			)
		);

		$text = $data['content'][0]['text'] ?? '';

		foreach ( preg_split( '/(\s+)/', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE ) as $piece ) {
			if ( '' !== $piece ) {
				yield $piece;
			}
		}
	}
}
