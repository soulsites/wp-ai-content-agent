<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Zentrale Settings-Verwaltung.
 */
class Settings {

    private static array $cache = [];

    public static function get( string $key, $default = null ) {
        if ( ! isset( self::$cache[ $key ] ) ) {
            self::$cache[ $key ] = get_option( $key, $default );
        }
        return self::$cache[ $key ];
    }

    public static function set( string $key, $value ): bool {
        self::$cache[ $key ] = $value;
        return update_option( $key, $value );
    }

    public static function get_api_key(): string {
        $key = self::get( 'aica_api_key', '' );
        return is_string( $key ) ? $key : '';
    }

    public static function get_model(): string {
        return self::get( 'aica_model', 'claude-opus-4-6' );
    }

    public static function get_max_tokens(): int {
        return (int) self::get( 'aica_max_tokens', 8000 );
    }

    public static function get_temperature(): float {
        return (float) self::get( 'aica_temperature', 0.7 );
    }

    public static function get_default_language(): string {
        return self::get( 'aica_default_lang', 'de' );
    }

    public static function get_agent_settings( string $agent_key ): array {
        $all = self::get( 'aica_agent_settings', Installer::get_default_agent_settings() );
        return $all[ $agent_key ] ?? [];
    }

    public static function get_all_agent_settings(): array {
        return self::get( 'aica_agent_settings', Installer::get_default_agent_settings() );
    }

    public static function get_voice_profiles(): array {
        $profiles = self::get( 'aica_voice_profiles', [] );
        return is_array( $profiles ) ? $profiles : [];
    }

    public static function get_voice_profile( int $id ): ?array {
        foreach ( self::get_voice_profiles() as $profile ) {
            if ( (int) $profile['id'] === $id ) {
                return $profile;
            }
        }
        return null;
    }

    public static function get_available_models(): array {
        return [
            'claude-opus-4-6'   => 'Claude Opus 4.6 (Leistungsstärkste)',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Ausgewogen)',
            'claude-haiku-4-5'  => 'Claude Haiku 4.5 (Schnell & Günstig)',
        ];
    }

    public static function get_available_schedules(): array {
        return [
            'manual'     => 'Manuell',
            'hourly'     => 'Stündlich',
            'twicedaily' => 'Zweimal täglich',
            'daily'      => 'Täglich',
            'weekly'     => 'Wöchentlich',
        ];
    }

    public static function save_from_request( array $data ): void {
        $api_key = sanitize_text_field( $data['aica_api_key'] ?? '' );
        if ( $api_key && $api_key !== '***SAVED***' ) {
            self::set( 'aica_api_key', $api_key );
        }

        self::set( 'aica_model', sanitize_key( $data['aica_model'] ?? 'claude-opus-4-6' ) );
        self::set( 'aica_max_tokens', min( 100000, max( 500, absint( $data['aica_max_tokens'] ?? 8000 ) ) ) );
        self::set( 'aica_temperature', min( 1.0, max( 0.0, (float) ( $data['aica_temperature'] ?? 0.7 ) ) ) );
        self::set( 'aica_default_lang', sanitize_key( $data['aica_default_lang'] ?? 'de' ) );
        self::set( 'aica_auto_publish', ! empty( $data['aica_auto_publish'] ) ? 1 : 0 );
        self::set( 'aica_default_status', sanitize_key( $data['aica_default_status'] ?? 'draft' ) );

        // Agenten-Einstellungen
        $agent_settings = self::get_all_agent_settings();
        $agent_keys     = array_keys( $agent_settings );

        foreach ( $agent_keys as $agent_key ) {
            if ( ! isset( $data['agents'][ $agent_key ] ) ) {
                continue;
            }
            $agent_data = $data['agents'][ $agent_key ];

            $agent_settings[ $agent_key ]['enabled']      = ! empty( $agent_data['enabled'] );
            $agent_settings[ $agent_key ]['model']        = sanitize_key( $agent_data['model'] ?? Settings::get_model() );
            $agent_settings[ $agent_key ]['max_tokens']   = min( 50000, max( 100, absint( $agent_data['max_tokens'] ?? 2000 ) ) );
            $agent_settings[ $agent_key ]['temperature']  = min( 1.0, max( 0.0, (float) ( $agent_data['temperature'] ?? 0.5 ) ) );
            $agent_settings[ $agent_key ]['system_prompt'] = sanitize_textarea_field( wp_unslash( $agent_data['system_prompt'] ?? '' ) );
        }

        self::set( 'aica_agent_settings', $agent_settings );

        // Cache leeren
        self::$cache = [];
    }
}
