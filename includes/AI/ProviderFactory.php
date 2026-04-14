<?php

namespace AgentKit\AI;

use AgentKit\Admin\SettingsManager;
use AgentKit\AI\Providers\AnthropicProvider;
use AgentKit\AI\Providers\GeminiProvider;
use AgentKit\AI\Providers\OpenRouterProvider;
use AgentKit\AI\Providers\OpenAIProvider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProviderFactory {
	public static function make( SettingsManager $settings, string $settings_key = 'provider' ): LLMProviderInterface {
		$config    = $settings->get_all()[ $settings_key ] ?? array();
		$providers = \apply_filters(
			'agentkit_providers',
			array(
				'openai'     => OpenAIProvider::class,
				'anthropic'  => AnthropicProvider::class,
				'gemini'     => GeminiProvider::class,
				'openrouter' => OpenRouterProvider::class,
			)
		);
		$name      = $config['name'] ?? 'openai';
		$class     = $providers[ $name ] ?? OpenAIProvider::class;

		return new $class( $config );
	}

	public static function make_fallback( SettingsManager $settings ): ?LLMProviderInterface {
		$fallback = $settings->get_all()['fallback_provider'] ?? array();

		if ( empty( $fallback['enabled'] ) ) {
			return null;
		}

		return self::make( $settings, 'fallback_provider' );
	}
}
