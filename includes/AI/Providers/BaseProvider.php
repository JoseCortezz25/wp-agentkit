<?php

namespace AgentKit\AI\Providers;

use AgentKit\AI\LLMProviderInterface;
use AgentKit\Support\Language;
use Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseProvider implements LLMProviderInterface {
	public function __construct( protected array $config ) {}

	protected function request_json( string $url, array $headers, array $payload ): array {
		$response = \wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => $headers,
				'body'    => \wp_json_encode( $payload ),
			)
		);

		if ( \is_wp_error( $response ) ) {
			return array();
		}

		$body = \wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : array();
	}

	public function test_connection(): bool {
		return ! empty( $this->config['api_key'] );
	}

	public function get_available_models(): array {
		return array();
	}

	public function chat( array $messages, array $options ): Generator {
		unset( $messages, $options );
		yield '';
	}

	protected function provider_not_configured_message( string $provider, array $options ): string {
		return Language::provider_not_configured( $provider, (string) ( $options['base_language'] ?? 'en' ) );
	}
}
