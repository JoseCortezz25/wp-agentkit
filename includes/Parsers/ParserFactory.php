<?php

namespace AgentKit\Parsers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ParserFactory {
	public static function make( string $file_path ): BaseParser {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$parsers   = \apply_filters(
			'agentkit_parsers',
			array(
				'txt'  => TxtParser::class,
				'md'   => MarkdownParser::class,
				'csv'  => CsvParser::class,
				'pdf'  => PDFParser::class,
				'docx' => DocxParser::class,
				'pptx' => PptxParser::class,
			)
		);

		$class = $parsers[ $extension ] ?? TxtParser::class;

		return new $class();
	}
}
