<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Abstrakte Basis-Klasse für alle Content-Agenten.
 */
abstract class Agent_Base {

    protected string      $agent_key;
    protected string      $agent_name;
    protected \AICA\API_Client $client;
    protected array       $settings;
    protected ?int        $content_id = null;
    protected int         $total_tokens = 0;

    public function __construct() {
        $this->settings = \AICA\Settings::get_agent_settings( $this->agent_key );
        $this->client   = new \AICA\API_Client();
    }

    /**
     * Führt den Agenten aus – zu implementieren von Unterklassen.
     *
     * @param array $context Kontextdaten (topic, keywords, bisherige Ergebnisse, etc.)
     * @return array|\WP_Error Ergebnis-Array oder WP_Error
     */
    abstract public function run( array $context );

    /**
     * Gibt den eindeutigen Schlüssel des Agenten zurück.
     */
    public function get_key(): string {
        return $this->agent_key;
    }

    /**
     * Gibt den Anzeigenamen des Agenten zurück.
     */
    public function get_name(): string {
        return $this->agent_name;
    }

    /**
     * Setzt die Content-ID für Logging.
     */
    public function set_content_id( int $id ): void {
        $this->content_id = $id;
    }

    /**
     * Gibt die Gesamtzahl der verwendeten Tokens zurück.
     */
    public function get_total_tokens(): int {
        return $this->total_tokens;
    }

    /**
     * Ist dieser Agent aktiviert?
     */
    public function is_enabled(): bool {
        return ! empty( $this->settings['enabled'] );
    }

    /**
     * Sendet eine Anfrage an Claude und loggt das Ergebnis.
     *
     * @param string $user_prompt   Nutzer-Nachricht
     * @param string $system_prompt Optionaler System-Prompt (überschreibt Default)
     * @param array  $extra_params  Zusätzliche API-Parameter
     * @return string|\WP_Error
     */
    protected function ask( string $user_prompt, string $system_prompt = '', array $extra_params = [] ) {
        $system = $system_prompt ?: ( $this->settings['system_prompt'] ?? '' );

        $params = array_merge( [
            'model'       => $this->settings['model']       ?? \AICA\Settings::get_model(),
            'max_tokens'  => $this->settings['max_tokens']  ?? 2000,
            'temperature' => $this->settings['temperature'] ?? 0.5,
        ], $extra_params );

        $this->log( 'info', "Agent gestartet: {$this->agent_name}" );

        $result = $this->client->send_message_tracked(
            $system,
            [ [ 'role' => 'user', 'content' => $user_prompt ] ],
            $params
        );

        if ( ! $result['success'] ) {
            $this->log( 'error', "API-Fehler: " . $result['error'] );
            return new \WP_Error( 'agent_error', $result['error'] );
        }

        // Tokens tracken
        $used = (int) ( $result['usage']['input_tokens'] ?? 0 )
                + (int) ( $result['usage']['output_tokens'] ?? 0 );
        $this->total_tokens += $used;

        $this->log( 'info', "Abgeschlossen. Tokens: {$used}" );

        return $result['text'];
    }

    /**
     * Erstellt ein strukturiertes Prompt aus Thema und Kontext.
     */
    protected function build_prompt( string $topic, array $context = [], string $extra = '' ): string {
        $parts = [];
        $parts[] = "**Thema:** {$topic}";

        if ( ! empty( $context['keywords'] ) ) {
            $parts[] = "**Keywords:** " . $context['keywords'];
        }

        if ( ! empty( $context['audience_result'] ) ) {
            $parts[] = "**Zielgruppenanalyse:**\n" . $this->truncate( $context['audience_result'], 500 );
        }

        if ( ! empty( $context['keyword_result'] ) ) {
            $parts[] = "**Keyword-Recherche:**\n" . $this->truncate( $context['keyword_result'], 500 );
        }

        if ( ! empty( $context['research_result'] ) ) {
            $parts[] = "**Recherche:**\n" . $this->truncate( $context['research_result'], 1000 );
        }

        if ( $extra ) {
            $parts[] = $extra;
        }

        return implode( "\n\n", $parts );
    }

    /**
     * Kürzt einen Text auf eine maximale Zeichenanzahl.
     */
    protected function truncate( string $text, int $max_chars ): string {
        if ( mb_strlen( $text ) <= $max_chars ) {
            return $text;
        }
        return mb_substr( $text, 0, $max_chars ) . '...';
    }

    /**
     * Schreibt einen Log-Eintrag in die Datenbank.
     */
    protected function log( string $level, string $message, array $data = [] ): void {
        if ( ! $this->content_id ) {
            return;
        }

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aica_logs',
            [
                'content_id' => $this->content_id,
                'agent'      => $this->agent_key,
                'level'      => $level,
                'message'    => $message,
                'data'       => ! empty( $data ) ? wp_json_encode( $data ) : null,
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );
    }

    /**
     * Parsed JSON aus einer Antwort (mit Fallback auf Rohtext).
     */
    protected function parse_json_response( string $text ): array {
        // JSON-Block aus Markdown extrahieren
        if ( preg_match( '/```json\s*([\s\S]*?)\s*```/', $text, $m ) ) {
            $decoded = json_decode( $m[1], true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }

        // Direktes JSON
        $decoded = json_decode( $text, true );
        if ( is_array( $decoded ) ) {
            return $decoded;
        }

        // Fallback: als Text-Array
        return [ 'raw' => $text ];
    }
}
