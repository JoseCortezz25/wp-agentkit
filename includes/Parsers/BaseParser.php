<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class BaseParser {
	abstract public function parse( string $file_path ): string;

	protected function clean_text( string $text ): string {
		$text = \wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );

		return trim( (string) $text );
	}
}
