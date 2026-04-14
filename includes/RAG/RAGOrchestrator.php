<?php

namespace AgentKit\RAG;

use AgentKit\Admin\SettingsManager;
use AgentKit\AI\ProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAGOrchestrator {
	public function __construct( private SettingsManager $settings ) {}

	public function retrieve( string $message ): array {
		$provider  = ProviderFactory::make( $this->settings );
		$embedding = $provider->embed( $message );
		$search    = new SemanticSearch( new VectorStore() );
		$chunks    = $search->search( $message, $embedding, 5 );

		return \apply_filters( 'agentkit_context', $chunks, $message );
	}
}
