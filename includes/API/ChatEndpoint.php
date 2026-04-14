<?php

namespace AgentKit\API;

use AgentKit\Admin\SettingsManager;
use AgentKit\AI\PromptBuilder;
use AgentKit\AI\ProviderFactory;
use AgentKit\RAG\ContextBuilder;
use AgentKit\RAG\RAGOrchestrator;
use AgentKit\Security\NonceManager;
use AgentKit\Security\RateLimiter;
use AgentKit\Security\Sanitizer;
use AgentKit\Stats\ConversationLogger;
use AgentKit\Support\Language;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ChatEndpoint {
	public function __construct( private SettingsManager $settings ) {}

	public function register_routes(): void {
		\register_rest_route(
			'agentkit/v1',
			'/chat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function handle( WP_REST_Request $request ) {
		$base_language = (string) $this->settings->get( 'general.base_language', 'en' );
		$nonce_check = ( new NonceManager() )->verify_rest_request( $base_language );

		if ( \is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$sanitizer  = new Sanitizer();
		$rate       = new RateLimiter();
		$message    = $sanitizer->sanitize_user_message(
			(string) $request->get_param( 'message' ),
			(int) $this->settings->get( 'security.max_message_length', 2000 )
		);
		$session_id = \sanitize_text_field( (string) $request->get_param( 'session_id' ) );

		if ( '' === $message || '' === $session_id ) {
			return new WP_Error( 'agentkit_invalid_payload', Language::invalid_payload( $base_language ), array( 'status' => 400 ) );
		}

		$limit = $rate->enforce(
			$session_id,
			(int) $this->settings->get( 'security.rate_limit_ip', 30 ),
			(int) $this->settings->get( 'security.rate_limit_session', 50 ),
			$base_language
		);

		if ( \is_wp_error( $limit ) ) {
			return $limit;
		}

		$rag      = new RAGOrchestrator( $this->settings );
		$chunks   = $rag->retrieve( $message );
		$context  = ( new ContextBuilder() )->build( $chunks );
		$messages = ( new PromptBuilder( $this->settings ) )->build( $message, $context );
		$provider = ProviderFactory::make( $this->settings );
		$fallback = ProviderFactory::make_fallback( $this->settings );
		$response = '';

		foreach (
			$provider->chat(
				$messages,
				array(
					'temperature' => (float) $this->settings->get( 'general.temperature', 0.2 ),
					'max_tokens'  => (int) $this->settings->get( 'general.max_response_tokens', 500 ),
					'base_language' => $base_language,
				)
			) as $chunk
		) {
			$response .= $chunk;
		}

		if ( '' === trim( $response ) && null !== $fallback ) {
			foreach (
				$fallback->chat(
					$messages,
				array(
					'temperature' => (float) $this->settings->get( 'general.temperature', 0.2 ),
					'max_tokens'  => (int) $this->settings->get( 'general.max_response_tokens', 500 ),
					'base_language' => $base_language,
				)
			) as $chunk
			) {
				$response .= $chunk;
			}
		}

		$response = \apply_filters( 'agentkit_response', $sanitizer->sanitize_assistant_output( $response ), array( 'message' => $message ) );

		( new ConversationLogger() )->log_pair(
			$session_id,
			$message,
			$response,
			array(
				'provider'   => $this->settings->get( 'provider.name', 'openai' ),
				'model'      => $this->settings->get( 'provider.chat_model', '' ),
				'page_url'   => \esc_url_raw( (string) $request->get_param( 'page_url' ) ),
				'page_title' => \sanitize_text_field( (string) $request->get_param( 'page_title' ) ),
			)
		);

		return \rest_ensure_response(
			array(
				'message' => $response,
				'chunks'  => $chunks,
			)
		);
	}
}
