<?php

namespace AgentKit\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sanitizer {
	public function sanitize_user_message( string $message, int $max_length = 2000 ): string {
		$message = \sanitize_textarea_field( $message );
		$message = mb_substr( trim( $message ), 0, $max_length );

		return $message;
	}

	public function sanitize_assistant_output( string $message ): string {
		$allowed = array(
			'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
			'code'   => array(),
			'pre'    => array(),
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);

		return \wp_kses( $message, $allowed );
	}
}
