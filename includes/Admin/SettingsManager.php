<?php

namespace AgentKit\Admin;

use AgentKit\Security\KeyManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SettingsManager {
	private string $option_name = 'agentkit_settings';

	public function get_all(): array {
		$settings = \get_option( $this->option_name, array() );
		$settings = array_replace_recursive( $this->get_defaults(), is_array( $settings ) ? $settings : array() );

		$settings = $this->decrypt_provider( $settings, 'provider' );
		$settings = $this->decrypt_provider( $settings, 'fallback_provider' );

		return is_array( $settings ) ? $settings : array();
	}

	public function get( string $path, mixed $default = null ): mixed {
		$segments = explode( '.', $path );
		$value    = $this->get_all();

		foreach ( $segments as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	public function save( array $settings ): array {
		$current = \get_option( $this->option_name, array() );
		$merged  = array_replace_recursive( $this->get_defaults(), is_array( $current ) ? $current : array(), $settings );

		$merged = $this->encrypt_provider( $merged, is_array( $current ) ? $current : array(), 'provider' );
		$merged = $this->encrypt_provider( $merged, is_array( $current ) ? $current : array(), 'fallback_provider' );

		\update_option( $this->option_name, $merged, false );

		return $this->get_public_admin_settings();
	}

	public function get_public_admin_settings(): array {
		$settings = $this->get_all();

		$settings = $this->mask_provider( $settings, 'provider' );
		$settings = $this->mask_provider( $settings, 'fallback_provider' );

		return $settings;
	}

	private function decrypt_provider( array $settings, string $key ): array {
		if ( isset( $settings[ $key ]['api_key'] ) && '' !== $settings[ $key ]['api_key'] ) {
			$settings[ $key ]['api_key'] = ( new KeyManager() )->decrypt( $settings[ $key ]['api_key'] );
		}

		return $settings;
	}

	private function mask_provider( array $settings, string $key ): array {
		if ( ! empty( $settings[ $key ]['api_key'] ) ) {
			$settings[ $key ]['api_key'] = str_repeat( '*', max( 8, strlen( (string) $settings[ $key ]['api_key'] ) ) );
		}

		return $settings;
	}

	private function encrypt_provider( array $merged, array $current, string $key ): array {
		if ( ! isset( $merged[ $key ]['api_key'] ) ) {
			return $merged;
		}

		$incoming = (string) $merged[ $key ]['api_key'];
		$masked   = str_replace( '*', '', $incoming );

		if ( '' !== $masked ) {
			$merged[ $key ]['api_key'] = ( new KeyManager() )->encrypt( $masked );
		} elseif ( isset( $current[ $key ]['api_key'] ) ) {
			$merged[ $key ]['api_key'] = $current[ $key ]['api_key'];
		}

		return $merged;
	}

	private function get_defaults(): array {
		return array(
			'general' => array(
				'agent_name'          => 'AgentKit',
				'system_prompt'       => 'Eres el asistente virtual de {site_name}. Responde usando solamente el contenido disponible del sitio cuando la restriccion de contexto este activada.',
				'base_language'       => $this->detect_wordpress_language(),
				'context_only'        => true,
				'temperature'         => 0.2,
				'max_response_tokens' => 500,
				'welcome_message'     => 'Hola, soy tu asistente del sitio. Puedo ayudarte con el contenido publicado aqui.',
			),
			'security' => array(
				'rate_limit_ip'      => 30,
				'rate_limit_session' => 50,
				'max_message_length' => 2000,
				'allowed_domains'    => array(),
			),
			'provider' => array(
				'name'            => 'openai',
				'chat_model'      => 'gpt-4o-mini',
				'embedding_model' => 'text-embedding-3-small',
				'api_key'         => '',
			),
			'fallback_provider' => array(
				'enabled'         => false,
				'name'            => 'openai',
				'chat_model'      => 'gpt-4o-mini',
				'embedding_model' => 'text-embedding-3-small',
				'api_key'         => '',
			),
			'files' => array(
				'max_file_size'   => 10485760,
				'max_total_files' => 50,
				'allowed_types'   => array( 'pdf', 'docx', 'pptx', 'txt', 'md', 'csv' ),
			),
		);
	}

	private function detect_wordpress_language(): string {
		$locale = function_exists( 'determine_locale' ) ? \determine_locale() : \get_locale();
		$code   = strtolower( substr( (string) $locale, 0, 2 ) );

		return '' !== $code ? $code : 'en';
	}
}
