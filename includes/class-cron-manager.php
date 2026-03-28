<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Verwaltet WP-Cron-Jobs für automatische Content-Generierung.
 */
class Cron_Manager {

    private static ?Cron_Manager $instance = null;

    private function __construct() {}

    public static function get_instance(): Cron_Manager {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        // Benutzerdefiniertes Schedule "weekly" registrieren
        add_filter( 'cron_schedules', [ $this, 'add_schedules' ] );

        // Alle aktiven Job-Hooks registrieren
        $this->register_job_hooks();
    }

    /**
     * Fügt zusätzliche WP-Cron-Intervalle hinzu.
     */
    public function add_schedules( array $schedules ): array {
        $schedules['weekly'] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => 'Einmal pro Woche',
        ];
        $schedules['twicemonthly'] = [
            'interval' => 15 * DAY_IN_SECONDS,
            'display'  => 'Zweimal pro Monat',
        ];
        return $schedules;
    }

    /**
     * Registriert alle aktiven Job-Hooks.
     */
    private function register_job_hooks(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aica_jobs';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $jobs = $wpdb->get_results(
            "SELECT id, cron_hook FROM {$table} WHERE active = 1 AND cron_hook IS NOT NULL AND schedule != 'manual'"
        );

        foreach ( $jobs as $job ) {
            add_action( $job->cron_hook, function () use ( $job ) {
                $this->run_job( (int) $job->id );
            } );
        }
    }

    /**
     * Plant einen Job im WP-Cron-System.
     */
    public function schedule_job( int $job_id, string $hook, string $schedule ): void {
        // Alten Eintrag entfernen
        $this->unschedule_hook( $hook );

        if ( 'manual' === $schedule ) {
            return;
        }

        // Hook-Handler registrieren
        add_action( $hook, function () use ( $job_id ) {
            $this->run_job( $job_id );
        } );

        // Nächste Ausführung planen
        $timestamp = $this->get_next_run_timestamp( $schedule );
        wp_schedule_event( $timestamp, $schedule, $hook );

        // next_run in DB speichern
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aica_jobs',
            [ 'next_run' => gmdate( 'Y-m-d H:i:s', $timestamp ) ],
            [ 'id' => $job_id ],
            null,
            [ '%d' ]
        );
    }

    /**
     * Entfernt einen Job aus dem WP-Cron-System.
     */
    public function unschedule_job( int $job_id ): void {
        global $wpdb;
        $hook = $wpdb->get_var( $wpdb->prepare(
            "SELECT cron_hook FROM {$wpdb->prefix}aica_jobs WHERE id = %d",
            $job_id
        ) );

        if ( $hook ) {
            $this->unschedule_hook( $hook );
        }
    }

    private function unschedule_hook( string $hook ): void {
        $timestamp = wp_next_scheduled( $hook );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $hook );
        }
    }

    /**
     * Führt einen Job sofort aus und gibt die Content-ID zurück.
     *
     * @return int|\WP_Error
     */
    public function run_job( int $job_id ) {
        global $wpdb;
        $job = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aica_jobs WHERE id = %d AND active = 1",
            $job_id
        ) );

        if ( ! $job ) {
            return new \WP_Error( 'job_not_found', "Job #{$job_id} nicht gefunden." );
        }

        $orchestrator = new Orchestrator();
        $content_id   = $orchestrator->create_generation_job( [
            'job_id'      => $job_id,
            'topic'       => $job->topic,
            'keywords'    => $job->keywords ?? '',
            'voice_id'    => (int) $job->voice_id,
            'post_status' => $job->post_status ?? 'draft',
            'category_id' => (int) $job->category_id,
        ] );

        if ( is_wp_error( $content_id ) ) {
            return $content_id;
        }

        $orchestrator->process_content( $content_id );

        return $content_id;
    }

    /**
     * Berechnet den Timestamp der nächsten Ausführung.
     */
    private function get_next_run_timestamp( string $schedule ): int {
        $intervals = [
            'hourly'     => HOUR_IN_SECONDS,
            'twicedaily' => 12 * HOUR_IN_SECONDS,
            'daily'      => DAY_IN_SECONDS,
            'weekly'     => WEEK_IN_SECONDS,
        ];

        $interval = $intervals[ $schedule ] ?? DAY_IN_SECONDS;
        return time() + $interval;
    }

    /**
     * Gibt alle geplanten Jobs zurück.
     */
    public function get_scheduled_jobs(): array {
        global $wpdb;
        $jobs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aica_jobs ORDER BY created_at DESC"
        );

        foreach ( $jobs as $job ) {
            if ( $job->cron_hook ) {
                $job->next_scheduled = wp_next_scheduled( $job->cron_hook );
            } else {
                $job->next_scheduled = null;
            }
        }

        return $jobs ?: [];
    }
}
