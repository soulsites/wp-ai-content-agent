<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrator – koordiniert alle Agenten und steuert den Content-Generierungs-Workflow.
 */
class Orchestrator {

    private API_Client $client;
    private int        $total_tokens = 0;

    public function __construct() {
        $this->client = new API_Client();
    }

    /**
     * Erstellt einen neuen Content-Generierungs-Job in der DB.
     *
     * @return int|\WP_Error Content-ID oder Fehler
     */
    public function create_generation_job( array $params ) {
        global $wpdb;

        $data = [
            'topic'       => sanitize_textarea_field( $params['topic']    ?? '' ),
            'keywords'    => sanitize_text_field( $params['keywords']     ?? '' ),
            'pipeline_id' => absint( $params['pipeline_id'] ?? 0 ) ?: null,
            'voice_id'    => absint( $params['voice_id']    ?? 0 ) ?: null,
            'post_status' => sanitize_key( $params['post_status'] ?? 'draft' ),
            'category_id' => absint( $params['category_id'] ?? 0 ) ?: null,
            'job_id'      => absint( $params['job_id']      ?? 0 ) ?: null,
            'status'      => 'pending',
        ];

        if ( empty( $data['topic'] ) ) {
            return new \WP_Error( 'no_topic', 'Kein Thema angegeben.' );
        }

        $result = $wpdb->insert( $wpdb->prefix . 'aica_content', $data );

        if ( false === $result ) {
            return new \WP_Error( 'db_error', 'Datenbankfehler beim Erstellen des Jobs.' );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Startet die Generierung asynchron (via WP-Cron Workaround: sofortiger Aufruf).
     * In einer Production-Umgebung würde man hier ActionScheduler oder einen Background-Process nutzen.
     */
    public function run_async( int $content_id ): void {
        // WordPress hat keinen nativen Background-Process.
        // Wir nutzen wp_schedule_single_event für sofortige Ausführung.
        wp_schedule_single_event( time(), 'aica_process_content', [ $content_id ] );

        // Hook registrieren (falls nicht schon global registriert)
        if ( ! has_action( 'aica_process_content', [ $this, 'process_content' ] ) ) {
            add_action( 'aica_process_content', [ $this, 'process_content' ] );
        }

        // Sofort ausführen wenn Cron nicht verfügbar (manueller Trigger)
        $this->process_content( $content_id );
    }

    /**
     * Verarbeitet den Content-Generierungs-Job (blockierend).
     * Führt alle Agenten sequenziell aus.
     */
    public function process_content( int $content_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'aica_content';

        // Job-Daten laden
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $content_id ) );
        if ( ! $row || 'processing' === $row->status ) {
            return;
        }

        $start_time = microtime( true );
        $this->update_status( $content_id, 'processing' );

        $context = [
            'topic'       => $row->topic,
            'keywords'    => $row->keywords ?? '',
            'voice_id'    => (int) $row->voice_id,
            'post_status' => $row->post_status ?? 'draft',
            'category_id' => (int) ( $row->category_id ?? 0 ),
            'job_settings' => $row->job_id ? $this->get_job_settings( (int) $row->job_id ) : [],
        ];

        try {
            $pipeline_id = (int) ( $row->pipeline_id ?? 0 );

            if ( $pipeline_id ) {
                // Custom Pipeline ausführen
                $runner  = new Pipeline_Runner();
                $context = $runner->execute( $pipeline_id, $context, $content_id );
                $this->total_tokens += $runner->get_total_tokens();

                // Writer-Agent-Instanz für Meta-Extraktion
                $writer_agent  = $context['_writer_agent'] ?? new Agents\Content_Writer();
                $final_content = $context['final_content']  ?? '';

                if ( empty( $final_content ) ) {
                    throw new \RuntimeException( 'Custom Pipeline hat keinen Artikel generiert. Stellen Sie sicher, dass ein Content-Writer-Step aktiviert ist.' );
                }
            } else {
                // Standard-Pipeline (hardcodiert)

                // 1. Content-Analyse
                $context = $this->run_agent(
                    new Agents\Content_Analyzer(),
                    $content_id,
                    $context,
                    'analysis_result'
                );

                // 2. Zielgruppenanalyse
                $context = $this->run_agent(
                    new Agents\Audience_Analyzer(),
                    $content_id,
                    $context,
                    'audience_result'
                );

                // 3. Keyword-Recherche
                $context = $this->run_agent(
                    new Agents\Keyword_Researcher(),
                    $content_id,
                    $context,
                    'keyword_result'
                );

                // 4. Recherche
                $context = $this->run_agent(
                    new Agents\Researcher(),
                    $content_id,
                    $context,
                    'research_result'
                );

                // 5. Content-Writer
                $writer_agent = new Agents\Content_Writer();
                $writer_agent->set_content_id( $content_id );

                $content_result = $writer_agent->run( $context );

                if ( is_wp_error( $content_result ) ) {
                    $writer_error = $content_result->get_error_message() ?: 'Unbekannter Fehler beim Schreiben des Artikels';
                    throw new \RuntimeException( 'Content-Writer: ' . $writer_error );
                }

                $this->total_tokens += $writer_agent->get_total_tokens();
                $final_content = $content_result;
            }

            // Meta-Daten extrahieren
            $meta       = $writer_agent->extract_meta( $final_content );
            $word_count = str_word_count( strip_tags( $final_content ) );
            $seo_score  = $writer_agent->calculate_seo_score( $final_content, $context['keywords'] );

            // Zwischenspeichern
            $wpdb->update(
                $table,
                [
                    'analysis_result' => $context['analysis_result'] ?? null,
                    'audience_result' => $context['audience_result'] ?? null,
                    'keyword_result'  => $context['keyword_result']  ?? null,
                    'research_result' => $context['research_result'] ?? null,
                    'final_content'   => $final_content,
                    'meta_title'      => $meta['title']       ?? null,
                    'meta_description' => $meta['description'] ?? null,
                    'word_count'      => $word_count,
                    'seo_score'       => $seo_score,
                    'tokens_used'     => $this->total_tokens,
                ],
                [ 'id' => $content_id ],
                null,
                [ '%d' ]
            );

            // WordPress-Post erstellen
            $post_id = Post_Publisher::publish(
                $final_content,
                $meta,
                $context
            );

            $duration = round( microtime( true ) - $start_time, 2 );

            if ( is_wp_error( $post_id ) ) {
                $this->log( $content_id, 'system', 'warning',
                    'WordPress-Post konnte nicht erstellt werden: ' . $post_id->get_error_message()
                );
            }

            $wpdb->update(
                $table,
                [
                    'status'       => 'completed',
                    'wp_post_id'   => is_wp_error( $post_id ) ? null : $post_id,
                    'duration_sec' => $duration,
                ],
                [ 'id' => $content_id ],
                null,
                [ '%d' ]
            );

            $this->log( $content_id, 'system', 'info',
                "Generierung abgeschlossen. Wörter: {$word_count}, SEO-Score: {$seo_score}, Tokens: {$this->total_tokens}, Dauer: {$duration}s"
            );

            // Job-Statistiken aktualisieren
            if ( $row->job_id ) {
                $this->update_job_stats( (int) $row->job_id );
            }

        } catch ( \Throwable $e ) {
            $error_class = get_class( $e );
            $error_msg   = $e->getMessage();
            if ( empty( $error_msg ) ) {
                $error_msg = 'Unbekannter Fehler (' . $error_class . ')';
            }
            $full_error = sprintf( '%s [%s in %s Zeile %d]', $error_msg, $error_class, basename( $e->getFile() ), $e->getLine() );
            $this->update_status( $content_id, 'error', $full_error );
            $this->log( $content_id, 'system', 'error', 'Kritischer Fehler: ' . $full_error );
        }
    }

    /**
     * Führt einen einzelnen Agenten aus und speichert das Ergebnis im Kontext.
     */
    private function run_agent( Agents\Agent_Base $agent, int $content_id, array $context, string $result_key ): array {
        if ( ! $agent->is_enabled() ) {
            $this->log( $content_id, $agent->get_key(), 'info', "Agent {$agent->get_name()} deaktiviert, übersprungen." );
            return $context;
        }

        $agent->set_content_id( $content_id );
        $result = $agent->run( $context );

        if ( is_wp_error( $result ) ) {
            $this->log( $content_id, $agent->get_key(), 'warning',
                "Agent {$agent->get_name()} fehlgeschlagen: " . $result->get_error_message()
            );
            // Fehler ist nicht fatal – weiter mit anderen Agenten
            $context[ $result_key ] = '';
        } else {
            $context[ $result_key ] = $result;
            $this->total_tokens    += $agent->get_total_tokens();
        }

        return $context;
    }

    /**
     * Aktualisiert den Status in der DB.
     */
    private function update_status( int $content_id, string $status, string $error = '' ): void {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aica_content',
            [
                'status'        => $status,
                'error_message' => $error ?: null,
            ],
            [ 'id' => $content_id ],
            null,
            [ '%d' ]
        );
    }

    /**
     * Schreibt einen System-Log-Eintrag mit optionalem Datenkontext.
     */
    private function log( int $content_id, string $agent, string $level, string $message, array $data = [] ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aica_logs',
            [
                'content_id' => $content_id,
                'agent'      => $agent,
                'level'      => $level,
                'message'    => $message,
                'data'       => ! empty( $data ) ? wp_json_encode( $data ) : null,
            ],
            [ '%d', '%s', '%s', '%s', $data ? '%s' : null ]
        );
    }

    /**
     * Lädt Job-Einstellungen aus der DB.
     */
    private function get_job_settings( int $job_id ): array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT settings FROM {$wpdb->prefix}aica_jobs WHERE id = %d",
            $job_id
        ) );

        if ( $row && $row->settings ) {
            $decoded = json_decode( $row->settings, true );
            return is_array( $decoded ) ? $decoded : [];
        }

        return [];
    }

    /**
     * Aktualisiert run_count und last_run des Jobs.
     */
    private function update_job_stats( int $job_id ): void {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}aica_jobs SET run_count = run_count + 1, last_run = NOW() WHERE id = %d",
            $job_id
        ) );
    }
}
