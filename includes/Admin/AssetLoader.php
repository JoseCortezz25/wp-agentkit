<?php

namespace AgentKit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetLoader {
	public function __construct( private SettingsManager $settings ) {}

	public function register(): void {
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
		\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_widget' ) );
	}

	public function enqueue_admin( string $hook ): void {
		if ( 'toplevel_page_agentkit' !== $hook ) {
			return;
		}

		\wp_enqueue_script( 'agentkit-admin', AGENTKIT_URL . 'dist/admin.js', array(), AGENTKIT_VERSION, true );
		\wp_enqueue_style( 'agentkit-admin', AGENTKIT_URL . 'dist/admin.css', array(), AGENTKIT_VERSION );
		\wp_localize_script(
			'agentkit-admin',
			'agentkitAdmin',
			array(
				'restUrl'  => \esc_url_raw( \rest_url( 'agentkit/v1' ) ),
				'nonce'    => \wp_create_nonce( 'wp_rest' ),
				'wpLanguage' => function_exists( 'determine_locale' ) ? strtolower( substr( (string) \determine_locale(), 0, 2 ) ) : strtolower( substr( (string) \get_locale(), 0, 2 ) ),
				'settings' => $this->settings->get_public_admin_settings(),
			)
		);
	}

	public function enqueue_widget(): void {
		\wp_register_script( 'agentkit-widget', AGENTKIT_URL . 'dist/widget.js', array(), AGENTKIT_VERSION, true );
		\wp_register_style( 'agentkit-widget', AGENTKIT_URL . 'dist/widget.css', array(), AGENTKIT_VERSION );

		\wp_enqueue_script( 'agentkit-widget' );
		\wp_enqueue_style( 'agentkit-widget' );

		\wp_localize_script(
			'agentkit-widget',
			'agentkitWidget',
			array(
				'restUrl'        => \esc_url_raw( \rest_url( 'agentkit/v1' ) ),
				'streamUrl'      => \esc_url_raw( \rest_url( 'agentkit/v1/chat-stream' ) ),
				'nonce'          => \wp_create_nonce( 'wp_rest' ),
				'baseLanguage'   => $this->settings->get( 'general.base_language', 'en' ),
				'welcomeMessage' => $this->resolve_welcome_message(),
				'agentName'      => $this->settings->get( 'general.agent_name', 'AgentKit' ),
				'appearance'     => $this->settings->get( 'appearance', array() ),
			)
		);
	}

	private function resolve_welcome_message(): string {
		$message = (string) $this->settings->get( 'general.welcome_message', '' );

		if ( '' !== trim( $message ) ) {
			return $message;
		}

		return match ( (string) $this->settings->get( 'general.base_language', 'en' ) ) {
			'es' => 'Hola, soy tu asistente del sitio. ¿En qué puedo ayudarte?',
			'pt' => 'Olá, sou o assistente do site. Como posso ajudar?',
			'fr' => 'Bonjour, je suis l’assistant du site. Comment puis-je vous aider ?',
			'de' => 'Hallo, ich bin der Assistent dieser Website. Wie kann ich helfen?',
			'it' => 'Ciao, sono l’assistente del sito. Come posso aiutarti?',
			'nl' => 'Hallo, ik ben de assistent van deze site. Hoe kan ik helpen?',
			'auto' => 'Hello, I am the site assistant. I will adapt to your language.',
			default => 'Hello, I am the site assistant. How can I help?',
		};
	}
}
