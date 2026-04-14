<?php

namespace AgentKit\RAG;

use AgentKit\Admin\SettingsManager;
use AgentKit\AI\ProviderFactory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContentIndexer {
	public function __construct( private SettingsManager $settings ) {}

	public function handle_post_save( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );

		if ( \wp_is_post_revision( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}

		if ( ! \is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		$this->index_post( $post_id );
	}

	public function reindex_public_content(): void {
		$posts = \get_posts(
			array(
				'post_type'      => \get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
			)
		);

		foreach ( $posts as $post ) {
			$this->index_post( (int) $post->ID );
		}
	}

	public function index_post( int $post_id ): void {
		$post = \get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		\do_action( 'agentkit_before_index', $post_id, $post->post_type );

		$text = implode(
			"\n\n",
			array_filter(
				array(
					\get_the_title( $post ),
					$post->post_excerpt,
					$post->post_content,
				)
			)
		);

		$splitter = new ChunkSplitter();
		$store    = new VectorStore();
		$provider = ProviderFactory::make( $this->settings );
		$chunks   = array();

		foreach ( $splitter->split( $text, (int) \apply_filters( 'agentkit_chunk_size', 400 ) ) as $chunk_text ) {
			$chunks[] = array(
				'text'        => $chunk_text,
				'embedding'   => method_exists( $provider, 'embed' ) ? $provider->embed( $chunk_text ) : array(),
				'token_count' => str_word_count( $chunk_text ),
			);
		}

		$store->replace_source_chunks( $post->post_type, $post_id, \get_permalink( $post_id ) ?: '', $chunks, $this->settings->get( 'provider.embedding_model', '' ) );

		\do_action( 'agentkit_after_index', $post_id, $chunks );
	}
}
