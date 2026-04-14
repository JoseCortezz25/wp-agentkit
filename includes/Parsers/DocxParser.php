<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DocxParser extends BaseParser {
	public function parse( string $file_path ): string {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			throw new \RuntimeException( 'DOCX requiere la extension ZIP de PHP.' );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			throw new \RuntimeException( 'No se pudo abrir el archivo DOCX.' );
		}

		$content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $content ) {
			return '';
		}

		$content = preg_replace( '/<w:p[^>]*>/', "\n", $content );
		$content = strip_tags( (string) $content );

		return $this->clean_text( (string) $content );
	}
}
