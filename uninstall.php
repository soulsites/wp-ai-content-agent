<?php
/**
 * Plugin-Deinstallation: Entfernt alle Daten aus der Datenbank.
 * Wird aufgerufen wenn das Plugin über die WP-Admin-Oberfläche gelöscht wird.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Alle Plugin-Optionen entfernen
$options = [
    'aica_api_key',
    'aica_model',
    'aica_max_tokens',
    'aica_temperature',
    'aica_default_lang',
    'aica_auto_publish',
    'aica_default_status',
    'aica_voice_profiles',
    'aica_agent_settings',
    'aica_db_version',
    'aica_plugin_version',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Custom-Tabellen löschen
$tables = [ 'aica_jobs', 'aica_content', 'aica_logs' ];
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Alle geplanten Cron-Jobs entfernen
$cron_hooks = [];
$cron_array = _get_cron_array();
if ( is_array( $cron_array ) ) {
    foreach ( $cron_array as $timestamp => $cron ) {
        foreach ( array_keys( $cron ) as $hook ) {
            if ( str_starts_with( $hook, 'aica_' ) ) {
                wp_clear_scheduled_hook( $hook );
            }
        }
    }
}

// Post-Meta löschen (generierte Posts behalten, nur Meta entfernen)
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aica_generated' ], [ '%s' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aica_original_topic' ], [ '%s' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aica_keywords' ], [ '%s' ] );
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_aica_voice_id' ], [ '%s' ] );
