<?php

namespace AgentKit\AI;

use Generator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface LLMProviderInterface {
	public function chat( array $messages, array $options ): Generator;
	public function embed( string $text ): array;
	public function get_available_models(): array;
	public function test_connection(): bool;
}
