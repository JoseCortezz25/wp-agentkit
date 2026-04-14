<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MarkdownParser extends BaseParser {
	public function parse( string $file_path ): string {
		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			return '';
		}

		$content = preg_replace( '/[#>*`_\-]+/', ' ', $content );

		return $this->clean_text( (string) $content );
	}
}
