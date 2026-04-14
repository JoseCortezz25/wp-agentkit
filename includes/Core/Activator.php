<?php

namespace AgentKit\Core;

use AgentKit\Database\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {
	public static function activate(): void {
		$migrator = new Migrator();
		$migrator->migrate();
		$wp_language = self::detect_wordpress_language();

		if ( ! \wp_next_scheduled( 'agentkit_daily_reindex' ) ) {
			\wp_schedule_event( \time() + \HOUR_IN_SECONDS, 'daily', 'agentkit_daily_reindex' );
		}

		\add_option( 'agentkit_version', AGENTKIT_VERSION );
		\add_option(
			'agentkit_settings',
			array(
				'general'  => array(
					'agent_name'          => 'AgentKit',
					'system_prompt'       => 'Eres el asistente virtual de {site_name}. Responde usando solamente el contenido disponible del sitio cuando la restriccion de contexto este activada.',
					'base_language'       => $wp_language,
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
			)
		);
	}

	private static function detect_wordpress_language(): string {
		$locale = function_exists( 'determine_locale' ) ? \determine_locale() : \get_locale();
		$code   = strtolower( substr( (string) $locale, 0, 2 ) );

		return '' !== $code ? $code : 'en';
	}
}
