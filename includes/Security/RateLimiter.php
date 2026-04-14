<?php

namespace AgentKit\Security;

use AgentKit\Support\Language;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RateLimiter {
	public function enforce( string $session_id, int $ip_limit, int $session_limit, string $language = 'en' ): true|WP_Error {
		$ip_key      = 'agentkit_ip_' . md5( $this->get_ip_hash() );
		$session_key = 'agentkit_session_' . md5( $session_id );
		$ip_count    = (int) \get_transient( $ip_key );
		$session_cnt = (int) \get_transient( $session_key );

		if ( $ip_count >= $ip_limit ) {
			return new WP_Error( 'agentkit_rate_limit_ip', Language::rate_limit_ip( $language ), array( 'status' => 429 ) );
		}

		if ( $session_cnt >= $session_limit ) {
			return new WP_Error( 'agentkit_rate_limit_session', Language::rate_limit_session( $language ), array( 'status' => 429 ) );
		}

		\set_transient( $ip_key, $ip_count + 1, \HOUR_IN_SECONDS );
		\set_transient( $session_key, $session_cnt + 1, \DAY_IN_SECONDS );

		return true;
	}

	public function get_ip_hash(): string {
		$ip = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );

		return hash( 'sha256', $ip );
	}
}
