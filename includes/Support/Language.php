<?php

namespace AgentKit\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Language {
	public static function normalize( string $code ): string {
		$code = strtolower( trim( $code ) );

		if ( '' === $code ) {
			return 'en';
		}

		return substr( $code, 0, 2 );
	}

	public static function provider_not_configured( string $provider, string $code ): string {
		$provider = trim( $provider );

		return match ( self::normalize( $code ) ) {
			'es' => sprintf( '%s no esta configurado todavia. Define una API key en el panel de administracion de AgentKit.', $provider ),
			'pt' => sprintf( '%s ainda não está configurado. Defina uma chave de API no painel administrativo do AgentKit.', $provider ),
			'fr' => sprintf( '%s n\'est pas encore configuré. Définissez une clé API dans le panneau d\'administration AgentKit.', $provider ),
			'de' => sprintf( '%s ist noch nicht konfiguriert. Lege einen API-Schlüssel im AgentKit-Adminbereich fest.', $provider ),
			'it' => sprintf( '%s non è ancora configurato. Definisci una chiave API nel pannello di amministrazione di AgentKit.', $provider ),
			'nl' => sprintf( '%s is nog niet geconfigureerd. Stel een API-sleutel in binnen het AgentKit-beheerpaneel.', $provider ),
			default => sprintf( '%s is not configured yet. Set an API key in the AgentKit admin panel.', $provider ),
		};
	}

	public static function invalid_nonce( string $code ): string {
		return match ( self::normalize( $code ) ) {
			'es' => 'Nonce invalido.',
			'pt' => 'Nonce inválido.',
			'fr' => 'Nonce invalide.',
			'de' => 'Ungueltiger Nonce.',
			'it' => 'Nonce non valido.',
			'nl' => 'Ongeldige nonce.',
			default => 'Invalid nonce.',
		};
	}

	public static function invalid_payload( string $code ): string {
		return match ( self::normalize( $code ) ) {
			'es' => 'Mensaje o sesion invalidos.',
			'pt' => 'Mensagem ou sessão inválidas.',
			'fr' => 'Message ou session invalide.',
			'de' => 'Nachricht oder Sitzung ungueltig.',
			'it' => 'Messaggio o sessione non validi.',
			'nl' => 'Bericht of sessie ongeldig.',
			default => 'Invalid message or session.',
		};
	}

	public static function rate_limit_ip( string $code ): string {
		return match ( self::normalize( $code ) ) {
			'es' => 'Limite por IP alcanzado.',
			'pt' => 'Limite por IP atingido.',
			'fr' => 'Limite par IP atteinte.',
			'de' => 'IP-Limit erreicht.',
			'it' => 'Limite per IP raggiunto.',
			'nl' => 'IP-limiet bereikt.',
			default => 'IP limit reached.',
		};
	}

	public static function rate_limit_session( string $code ): string {
		return match ( self::normalize( $code ) ) {
			'es' => 'Limite por sesion alcanzado.',
			'pt' => 'Limite por sessão atingido.',
			'fr' => 'Limite de session atteinte.',
			'de' => 'Sitzungslimit erreicht.',
			'it' => 'Limite per sessione raggiunto.',
			'nl' => 'Sessielimiet bereikt.',
			default => 'Session limit reached.',
		};
	}

	public static function file_not_found( string $code ): string {
		return match ( self::normalize( $code ) ) {
			'es' => 'Archivo no encontrado en disco.',
			'pt' => 'Arquivo não encontrado no disco.',
			'fr' => 'Fichier introuvable sur le disque.',
			'de' => 'Datei auf dem Datentraeger nicht gefunden.',
			'it' => 'File non trovato sul disco.',
			'nl' => 'Bestand niet gevonden op schijf.',
			default => 'File not found on disk.',
		};
	}
}
