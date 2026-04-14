<?php

namespace AgentKit\Admin;

use AgentKit\RAG\FileIndexer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MediaUploader {
	public function __construct( private SettingsManager $settings ) {}

	public function register(): void {
		\add_action( 'add_attachment', array( $this, 'handle_attachment' ) );
		\add_action( 'delete_attachment', array( $this, 'handle_delete_attachment' ) );
	}

	public function handle_attachment( int $attachment_id ): void {
		( new FileIndexer( $this->settings ) )->register_attachment( $attachment_id );
	}

	public function handle_delete_attachment( int $attachment_id ): void {
		( new FileIndexer( $this->settings ) )->delete_attachment_index( $attachment_id );
	}
}
