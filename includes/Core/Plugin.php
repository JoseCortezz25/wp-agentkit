<?php

namespace AgentKit\Core;

use AgentKit\Admin\AdminMenu;
use AgentKit\Admin\AssetLoader;
use AgentKit\Admin\MediaUploader;
use AgentKit\Admin\SettingsManager;
use AgentKit\API\RestController;
use AgentKit\RAG\ContentIndexer;
use AgentKit\RAG\FileIndexer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {
	public function run(): void {
		$settings = new SettingsManager();
		$file_indexer = new FileIndexer( $settings );

		( new AdminMenu() )->register();
		( new AssetLoader( $settings ) )->register();
		( new RestController( $settings ) )->register();
		( new MediaUploader( $settings ) )->register();

		$content_indexer = new ContentIndexer( $settings );
		\add_action( 'save_post', array( $content_indexer, 'handle_post_save' ), 10, 3 );
		\add_action( 'agentkit_daily_reindex', array( $content_indexer, 'reindex_public_content' ) );
		\add_action( 'agentkit_process_pending_files', array( $file_indexer, 'process_pending_files' ) );

		\add_shortcode( 'agentkit', array( $this, 'render_shortcode' ) );
	}

	public function render_shortcode( array $atts = array() ): string {
		$atts = \shortcode_atts(
			array(
				'title'   => '',
				'height'  => '560px',
				'theme'   => 'light',
				'context' => '',
			),
			$atts,
			'agentkit'
		);

		\wp_enqueue_script( 'agentkit-widget' );
		\wp_enqueue_style( 'agentkit-widget' );

		return sprintf(
			'<div class="agentkit-shortcode" data-agentkit-embed="1" data-title="%1$s" data-height="%2$s" data-theme="%3$s" data-context="%4$s"></div>',
			\esc_attr( (string) $atts['title'] ),
			\esc_attr( (string) $atts['height'] ),
			\esc_attr( (string) $atts['theme'] ),
			\esc_attr( (string) $atts['context'] )
		);
	}
}
