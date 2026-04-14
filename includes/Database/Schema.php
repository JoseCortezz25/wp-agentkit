<?php

namespace AgentKit\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {
	public function get_sql(): array {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'agentkit_';

		return array(
			"CREATE TABLE {$prefix}conversations (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_id VARCHAR(64) NOT NULL,
				user_ip VARCHAR(64) NULL,
				page_url TEXT NULL,
				page_title VARCHAR(255) NULL,
				started_at DATETIME NOT NULL,
				last_message_at DATETIME NOT NULL,
				message_count INT NOT NULL DEFAULT 0,
				total_tokens INT NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'active',
				PRIMARY KEY  (id),
				KEY session_id (session_id)
			) {$charset};",
			"CREATE TABLE {$prefix}messages (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				conversation_id BIGINT UNSIGNED NOT NULL,
				role VARCHAR(20) NOT NULL,
				content LONGTEXT NOT NULL,
				tokens_input INT NOT NULL DEFAULT 0,
				tokens_output INT NOT NULL DEFAULT 0,
				provider VARCHAR(50) NULL,
				model VARCHAR(100) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY conversation_id (conversation_id)
			) {$charset};",
			"CREATE TABLE {$prefix}chunks (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				source_type VARCHAR(20) NOT NULL,
				source_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				source_url TEXT NULL,
				chunk_index INT NOT NULL,
				chunk_text LONGTEXT NOT NULL,
				embedding LONGTEXT NULL,
				embedding_model VARCHAR(100) NULL,
				token_count INT NULL,
				checksum VARCHAR(32) NOT NULL,
				indexed_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY source_lookup (source_type, source_id),
				KEY checksum (checksum),
				FULLTEXT KEY chunk_text (chunk_text)
			) {$charset};",
			"CREATE TABLE {$prefix}files (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				attachment_id BIGINT UNSIGNED NOT NULL,
				original_name VARCHAR(255) NOT NULL,
				file_type VARCHAR(20) NOT NULL,
				file_size BIGINT NOT NULL DEFAULT 0,
				chunk_count INT NOT NULL DEFAULT 0,
				status VARCHAR(20) NOT NULL DEFAULT 'pending',
				error_message TEXT NULL,
				uploaded_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
				indexed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id)
			) {$charset};",
			"CREATE TABLE {$prefix}stats_daily (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				stat_date DATE NOT NULL,
				conversations INT NOT NULL DEFAULT 0,
				messages INT NOT NULL DEFAULT 0,
				tokens_input BIGINT NOT NULL DEFAULT 0,
				tokens_output BIGINT NOT NULL DEFAULT 0,
				unique_sessions INT NOT NULL DEFAULT 0,
				avg_messages_per_conv DECIMAL(5,2) NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY stat_date (stat_date)
			) {$charset};",
		);
	}
}
