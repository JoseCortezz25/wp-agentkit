<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TxtParser extends BaseParser {
	public function parse( string $file_path ): string {
		$content = file_get_contents( $file_path );

		return false === $content ? '' : $this->clean_text( $content );
	}
}
