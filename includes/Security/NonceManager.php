<?php

namespace AgentKit\Security;

use AgentKit\Support\Language;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NonceManager {
	public function verify_rest_request( string $language = 'en' ): true|WP_Error {
		$nonce = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ?? '' ) );

		if ( ! \wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'agentkit_invalid_nonce', Language::invalid_nonce( $language ), array( 'status' => 403 ) );
		}

		return true;
	}
}
