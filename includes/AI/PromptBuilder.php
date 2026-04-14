<?php

namespace AgentKit\AI;

use AgentKit\Admin\SettingsManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PromptBuilder {
	public function __construct( private SettingsManager $settings ) {}

	public function build( string $message, string $context, array $history = array() ): array {
		$system = (string) $this->settings->get( 'general.system_prompt', '' );
		$base_language = (string) $this->settings->get( 'general.base_language', 'en' );
		$language_name = $this->map_language_name( $base_language );
		$system = strtr(
			$system,
			array(
				'{site_name}'    => \get_bloginfo( 'name' ),
				'{site_url}'     => \home_url(),
				'{current_page}' => \home_url( \add_query_arg( array() ) ),
				'{language}'     => $language_name,
			)
		);

		$language_instruction = 'auto' === $base_language
			? 'Detecta el idioma del usuario y responde en ese mismo idioma, manteniendo consistencia durante toda la conversación.'
			: sprintf( 'Responde siempre en %s. Si el usuario escribe en otro idioma, puedes aclararlo brevemente pero mantén la respuesta principal en %s.', $language_name, $language_name );

		$messages = array(
			array(
				'role'    => 'system',
				'content' => trim( $system . "\n\n[IDIOMA]\n" . $language_instruction . "\n\n[CONTEXTO]\n" . $context ),
			),
		);

		foreach ( $history as $item ) {
			$messages[] = array(
				'role'    => $item['role'],
				'content' => $item['content'],
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => "[MENSAJE_USUARIO]\n" . $message,
		);

		$prompt = \wp_json_encode( $messages );
		$prompt = \apply_filters( 'agentkit_prompt', $prompt, array( 'context' => $context, 'message' => $message ) );

		$decoded = json_decode( (string) $prompt, true );

		return is_array( $decoded ) ? $decoded : $messages;
	}

	private function map_language_name( string $code ): string {
		$map = array(
			'auto' => 'el idioma del usuario',
			'es'   => 'español',
			'en'   => 'English',
			'pt'   => 'português',
			'fr'   => 'français',
			'de'   => 'Deutsch',
			'it'   => 'italiano',
			'nl'   => 'Nederlands',
		);

		return $map[ strtolower( $code ) ] ?? strtoupper( $code );
	}
}
