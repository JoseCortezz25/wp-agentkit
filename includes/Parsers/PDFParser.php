<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PDFParser extends BaseParser {
	public function parse( string $file_path ): string {
		if ( ! class_exists( '\\Smalot\\PdfParser\\Parser' ) ) {
			throw new \RuntimeException( 'PDF requiere instalar dependencias Composer (`./tools/composer install`).' );
		}

		$parser = new \Smalot\PdfParser\Parser();
		$pdf    = $parser->parseFile( $file_path );

		return $this->clean_text( $pdf->getText() );
	}
}
