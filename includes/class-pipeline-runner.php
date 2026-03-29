<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Führt eine Custom-Pipeline aus.
 * Lädt die Steps aus der DB und führt die konfigurierten Agenten der Reihe nach aus.
 */
class Pipeline_Runner {

    /** Agent-Key → Context-Key-Mapping */
    const RESULT_KEYS = [
        'content_analyzer'   => 'analysis_result',
        'audience_analyzer'  => 'audience_result',
        'keyword_researcher' => 'keyword_result',
        'researcher'         => 'research_result',
    ];

    private int $total_tokens = 0;

    /**
     * Führt die Pipeline für eine Content-Generierung aus.
     *
     * @param int   $pipeline_id  ID der Pipeline in wp_aica_pipelines
     * @param array $context      Initialer Kontext (topic, keywords, …)
     * @param int   $content_id   Für Logging
     * @return array              Erweiterter Kontext nach Pipeline-Ausführung
     * @throws \RuntimeException  Bei kritischem Fehler im Content_Writer
     */
    public function execute( int $pipeline_id, array $context, int $content_id ): array {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT steps FROM {$wpdb->prefix}aica_pipelines WHERE id = %d",
            $pipeline_id
        ) );

        if ( ! $row ) {
            throw new \RuntimeException( "Pipeline {$pipeline_id} nicht gefunden." );
        }

        $steps = json_decode( $row->steps, true );
        if ( ! is_array( $steps ) || empty( $steps ) ) {
            throw new \RuntimeException( "Pipeline {$pipeline_id} hat keine gültigen Steps." );
        }

        foreach ( $steps as $step ) {
            $agent_key = $step['agent']    ?? '';
            $enabled   = $step['enabled']  ?? true;
            $conditions = $step['conditions'] ?? [];

            if ( ! $enabled ) {
                $this->log( $content_id, $agent_key, 'info', "Step '{$agent_key}' deaktiviert, übersprungen." );
                continue;
            }

            // Bedingungen auswerten
            if ( ! empty( $conditions ) ) {
                $action = $this->evaluate_conditions( $conditions, $context );
                if ( 'skip' === $action ) {
                    $this->log( $content_id, $agent_key, 'info', "Step '{$agent_key}' per Bedingung übersprungen." );
                    continue;
                }
                if ( 'stop' === $action ) {
                    $this->log( $content_id, 'system', 'info', "Pipeline durch Bedingung in Step '{$agent_key}' gestoppt." );
                    break;
                }
            }

            // Agent ausführen
            $agent = $this->create_agent( $agent_key );
            if ( null === $agent ) {
                $this->log( $content_id, $agent_key, 'warning', "Unbekannter Agent-Key '{$agent_key}', übersprungen." );
                continue;
            }

            $agent->set_content_id( $content_id );

            // Content_Writer: fatal, schreibt Ergebnis direkt in Context zurück
            if ( 'content_writer' === $agent_key ) {
                $result = $agent->run( $context );
                if ( is_wp_error( $result ) ) {
                    throw new \RuntimeException( 'Content-Writer: ' . $result->get_error_message() );
                }
                $this->total_tokens += $agent->get_total_tokens();
                $context['final_content']  = $result;
                $context['_writer_agent']  = $agent; // Für Meta-Extraktion im Orchestrator
                continue;
            }

            // Alle anderen Agenten: non-fatal
            $result_key = self::RESULT_KEYS[ $agent_key ] ?? null;
            if ( ! $result_key ) {
                continue;
            }

            $result = $agent->run( $context );
            if ( is_wp_error( $result ) ) {
                $this->log( $content_id, $agent_key, 'warning',
                    "Agent '{$agent_key}' fehlgeschlagen: " . $result->get_error_message()
                );
                $context[ $result_key ] = '';
            } else {
                $context[ $result_key ]  = $result;
                $this->total_tokens     += $agent->get_total_tokens();
            }
        }

        return $context;
    }

    public function get_total_tokens(): int {
        return $this->total_tokens;
    }

    /**
     * Wertet alle Bedingungen eines Steps aus.
     * Gibt 'continue', 'skip' oder 'stop' zurück.
     */
    private function evaluate_conditions( array $conditions, array $context ): string {
        foreach ( $conditions as $condition ) {
            $source      = $condition['source']      ?? '';
            $operator    = $condition['operator']    ?? 'contains';
            $value       = $condition['value']       ?? '';
            $on_match    = $condition['on_match']    ?? 'continue';
            $on_no_match = $condition['on_no_match'] ?? 'continue';

            $haystack = $context[ $source ] ?? '';
            $matches  = $this->check_condition( $operator, $haystack, $value );

            $action = $matches ? $on_match : $on_no_match;

            // Erste Bedingung die eine Nicht-Continue-Aktion liefert, gewinnt
            if ( 'continue' !== $action ) {
                return $action;
            }
        }

        return 'continue';
    }

    /**
     * Prüft eine einzelne Bedingung.
     */
    private function check_condition( string $operator, string $haystack, string $value ): bool {
        switch ( $operator ) {
            case 'contains':
                return str_contains( mb_strtolower( $haystack ), mb_strtolower( $value ) );
            case 'not_contains':
                return ! str_contains( mb_strtolower( $haystack ), mb_strtolower( $value ) );
            case 'length_gt':
                return mb_strlen( $haystack ) > (int) $value;
            case 'length_lt':
                return mb_strlen( $haystack ) < (int) $value;
            default:
                return true;
        }
    }

    /**
     * Instanziiert einen Agenten anhand seines Keys.
     */
    private function create_agent( string $key ): ?Agents\Agent_Base {
        switch ( $key ) {
            case 'content_analyzer':   return new Agents\Content_Analyzer();
            case 'audience_analyzer':  return new Agents\Audience_Analyzer();
            case 'keyword_researcher': return new Agents\Keyword_Researcher();
            case 'researcher':         return new Agents\Researcher();
            case 'content_writer':     return new Agents\Content_Writer();
            default:                   return null;
        }
    }

    private function log( int $content_id, string $agent, string $level, string $message ): void {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aica_logs',
            [
                'content_id' => $content_id,
                'agent'      => $agent,
                'level'      => $level,
                'message'    => $message,
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }
}
