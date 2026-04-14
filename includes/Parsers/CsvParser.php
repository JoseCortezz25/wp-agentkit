<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CsvParser extends BaseParser {
	public function parse( string $file_path ): string {
		$handle = fopen( $file_path, 'rb' );

		if ( false === $handle ) {
			return '';
		}

		$rows = array();

		while ( false !== ( $data = fgetcsv( $handle ) ) ) {
			$rows[] = implode( ' | ', array_map( 'trim', $data ) );
		}

		fclose( $handle );

		return $this->clean_text( implode( "\n", $rows ) );
	}
}
