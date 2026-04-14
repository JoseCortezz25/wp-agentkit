<?php

namespace AgentKit\Stats;

use AgentKit\Security\RateLimiter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ConversationLogger {
	public function log_pair( string $session_id, string $user_message, string $assistant_message, array $meta = array() ): void {
		global $wpdb;

		$conversations = $wpdb->prefix . 'agentkit_conversations';
		$messages      = $wpdb->prefix . 'agentkit_messages';
		$stats_table   = $wpdb->prefix . 'agentkit_stats_daily';
		$now           = \current_time( 'mysql', true );
		$today         = gmdate( 'Y-m-d', strtotime( $now ) );
		$conversation  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$conversations} WHERE session_id = %s LIMIT 1", $session_id ), \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$is_new        = empty( $conversation );

		if ( $is_new ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$conversations,
				array(
					'session_id'      => $session_id,
					'user_ip'         => ( new RateLimiter() )->get_ip_hash(),
					'page_url'        => $meta['page_url'] ?? '',
					'page_title'      => $meta['page_title'] ?? '',
					'started_at'      => $now,
					'last_message_at' => $now,
					'message_count'   => 0,
					'total_tokens'    => 0,
					'status'          => 'active',
				)
			);
			$conversation_id = (int) $wpdb->insert_id;
			\do_action( 'agentkit_conversation_started', array( 'id' => $conversation_id, 'session_id' => $session_id ) );
		} else {
			$conversation_id = (int) $conversation['id'];
		}

		foreach ( array( array( 'user', $user_message ), array( 'assistant', $assistant_message ) ) as $pair ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$messages,
				array(
					'conversation_id' => $conversation_id,
					'role'            => $pair[0],
					'content'         => $pair[1],
					'provider'        => $meta['provider'] ?? '',
					'model'           => $meta['model'] ?? '',
					'created_at'      => $now,
				)
			);
		}

		$wpdb->query( $wpdb->prepare( "UPDATE {$conversations} SET last_message_at = %s, message_count = message_count + 2 WHERE id = %d", $now, $conversation_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->update_daily_stats( $stats_table, $today, $session_id, $is_new );
		\do_action( 'agentkit_message_received', array( 'conversation_id' => $conversation_id, 'content' => $user_message ) );
	}

	private function update_daily_stats( string $stats_table, string $today, string $session_id, bool $is_new_conversation ): void {
		global $wpdb;

		$stats = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$stats_table} WHERE stat_date = %s LIMIT 1", $today ), \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( empty( $stats ) ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$stats_table,
				array(
					'stat_date'             => $today,
					'conversations'         => $is_new_conversation ? 1 : 0,
					'messages'              => 2,
					'tokens_input'          => 0,
					'tokens_output'         => 0,
					'unique_sessions'       => 1,
					'avg_messages_per_conv' => $is_new_conversation ? 2 : 0,
				),
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%f' )
			);
			return;
		}

		$conversations   = (int) $stats['conversations'] + ( $is_new_conversation ? 1 : 0 );
		$messages        = (int) $stats['messages'] + 2;
		$unique_sessions = $this->session_seen_today( $today, $session_id ) ? (int) $stats['unique_sessions'] : ( (int) $stats['unique_sessions'] + 1 );
		$avg             = $conversations > 0 ? round( $messages / $conversations, 2 ) : 0;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$stats_table,
			array(
				'conversations'         => $conversations,
				'messages'              => $messages,
				'unique_sessions'       => $unique_sessions,
				'avg_messages_per_conv' => $avg,
			),
			array( 'stat_date' => $today ),
			array( '%d', '%d', '%d', '%f' ),
			array( '%s' )
		);
	}

	private function session_seen_today( string $today, string $session_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'agentkit_conversations';
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE session_id = %s AND DATE(started_at) = %s", $session_id, $today ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		return $count > 1;
	}
}
