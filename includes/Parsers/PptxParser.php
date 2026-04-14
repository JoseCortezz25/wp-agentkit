<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PptxParser extends BaseParser {
	public function parse( string $file_path ): string {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			throw new \RuntimeException( 'PPTX requiere la extension ZIP de PHP.' );
		}

		$zip = new \ZipArchive();

		if ( true !== $zip->open( $file_path ) ) {
			throw new \RuntimeException( 'No se pudo abrir el archivo PPTX.' );
		}

		$texts = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );

			if ( ! is_string( $name ) || ! preg_match( '#^ppt/slides/slide\\d+\\.xml$#', $name ) ) {
				continue;
			}

			$content = $zip->getFromIndex( $i );

			if ( false !== $content ) {
				$texts[] = strip_tags( str_replace( array( '</a:p>', '</a:t>' ), "\n", $content ) );
			}
		}

		$zip->close();

		return $this->clean_text( implode( "\n", $texts ) );
	}
}
