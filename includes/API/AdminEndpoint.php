<?php

namespace AgentKit\API;

use AgentKit\Admin\SettingsManager;
use AgentKit\Database\Migrator;
use AgentKit\RAG\ContentIndexer;
use AgentKit\Security\NonceManager;
use AgentKit\Support\Language;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminEndpoint {
	public function __construct( private SettingsManager $settings ) {}

	public function register_routes(): void {
		\register_rest_route(
			'agentkit/v1',
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'authorize_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'authorize_admin' ),
				),
			)
		);

		\register_rest_route(
			'agentkit/v1',
			'/index',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reindex' ),
				'permission_callback' => array( $this, 'authorize_admin' ),
			)
		);

		\register_rest_route(
			'agentkit/v1',
			'/providers/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_provider' ),
				'permission_callback' => array( $this, 'authorize_admin' ),
			)
		);
	}

	public function authorize_admin() {
		$language = (string) $this->settings->get( 'general.base_language', 'en' );
		$nonce = ( new NonceManager() )->verify_rest_request( $language );

		if ( \is_wp_error( $nonce ) ) {
			return $nonce;
		}

		if ( ! \current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'agentkit_forbidden', Language::normalize( $language ) === 'es' ? 'No autorizado.' : 'Forbidden.', array( 'status' => 403 ) );
		}

		return true;
	}

	public function get_settings() {
		return \rest_ensure_response( $this->settings->get_public_admin_settings() );
	}

	public function update_settings( WP_REST_Request $request ) {
		$payload = $request->get_json_params();

		return \rest_ensure_response( $this->settings->save( is_array( $payload ) ? $payload : array() ) );
	}

	public function reindex() {
		( new Migrator() )->migrate();
		( new ContentIndexer( $this->settings ) )->reindex_public_content();

		return \rest_ensure_response( array( 'success' => true ) );
	}

	public function test_provider( WP_REST_Request $request ) {
		$target   = (string) $request->get_param( 'target' );
		$provider = 'fallback' === $target ? \AgentKit\AI\ProviderFactory::make_fallback( $this->settings ) : \AgentKit\AI\ProviderFactory::make( $this->settings );

		if ( null === $provider ) {
			return \rest_ensure_response( array( 'success' => false, 'message' => 'Fallback no configurado.' ) );
		}

		return \rest_ensure_response(
			array(
				'success' => $provider->test_connection(),
				'models'  => $provider->get_available_models(),
			)
		);
	}
}
