<?php

namespace AgentKit\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KeyManager {
	public function encrypt( string $plain ): string {
		$key    = \hash( 'sha256', (string) \AUTH_KEY, true );
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $cipher ) {
			return '';
		}

		return base64_encode( $iv . $tag . $cipher );
	}

	public function decrypt( string $encoded ): string {
		$raw = base64_decode( $encoded, true );

		if ( false === $raw || strlen( $raw ) < 28 ) {
			return '';
		}

		$key    = \hash( 'sha256', (string) \AUTH_KEY, true );
		$iv     = substr( $raw, 0, 12 );
		$tag    = substr( $raw, 12, 16 );
		$cipher = substr( $raw, 28 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		return false === $plain ? '' : $plain;
	}
}
