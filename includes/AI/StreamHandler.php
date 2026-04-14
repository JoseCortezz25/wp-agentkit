<?php

namespace AgentKit\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StreamHandler {
	public function stream( LLMProviderInterface $provider, array $messages, array $options, ?LLMProviderInterface $fallback = null ): string {
		$this->send_headers();
		$this->emit( 'meta', array( 'status' => 'start' ) );

		$response = $this->stream_from_provider( $provider, $messages, $options );

		if ( '' === trim( $response ) && null !== $fallback ) {
			$this->emit( 'meta', array( 'status' => 'fallback' ) );
			$response = $this->stream_from_provider( $fallback, $messages, $options );
		}

		$this->emit( 'done', array( 'message' => $response ) );

		return $response;
	}

	private function stream_from_provider( LLMProviderInterface $provider, array $messages, array $options ): string {
		$buffer     = '';
		$last_ping  = \time();

		foreach ( $provider->chat( $messages, $options ) as $chunk ) {
			$chunk = (string) $chunk;

			if ( '' === $chunk ) {
				continue;
			}

			$buffer .= $chunk;
			$this->emit( 'chunk', array( 'text' => $chunk ) );

			if ( \time() - $last_ping >= 15 ) {
				$this->ping();
				$last_ping = \time();
			}
		}

		return $buffer;
	}

	private function send_headers(): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/event-stream' );
			header( 'Cache-Control: no-cache, no-transform' );
			header( 'X-Accel-Buffering: no' );
			header( 'Connection: keep-alive' );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		while ( \ob_get_level() > 0 ) {
			\ob_end_flush();
		}
	}

	private function emit( string $event, array $payload ): void {
		echo "event: {$event}\n";
		echo 'data: ' . \wp_json_encode( $payload ) . "\n\n";
		$this->flush();
	}

	private function ping(): void {
		echo ": ping\n\n";
		$this->flush();
	}

	private function flush(): void {
		if ( function_exists( 'ob_flush' ) ) {
			@ob_flush();
		}

		flush();
	}
}
