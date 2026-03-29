<?php
namespace AICA\Agents;

defined( 'ABSPATH' ) || exit;

/**
 * Generischer Custom-Agent.
 * Lädt seine Konfiguration (Prompt, Modell, Fähigkeiten) aus der DB.
 */
class Custom_Agent extends Agent_Base {

    protected string $agent_key  = '';
    protected string $agent_name = '';

    /** DB-Konfigurationszeile */
    private array $config;

    public function __construct( array $config ) {
        $this->config     = $config;
        $this->agent_key  = $config['agent_key'];
        $this->agent_name = $config['name'];

        // Settings so befüllen, dass Agent_Base::ask() sie nutzen kann
        $this->settings = [
            'enabled'       => true,
            'model'         => $config['model']       ?? \AICA\Settings::get_model(),
            'max_tokens'    => (int) ( $config['max_tokens']  ?? 2000 ),
            'temperature'   => (float) ( $config['temperature'] ?? 0.5 ),
            'system_prompt' => '',
        ];

        $this->client = new \AICA\API_Client();
    }

    /**
     * Führt den Custom-Agenten aus.
     */
    public function run( array $context ) {
        $capabilities = json_decode( $this->config['capabilities'] ?? '{}', true ) ?: [];

        // Web-Inhalte abrufen (falls Web-Search aktiviert und URLs angegeben)
        $web_context = '';
        if ( ! empty( $capabilities['web_search']['enabled'] ) ) {
            $urls = array_filter( array_map( 'trim', (array) ( $capabilities['web_search']['urls'] ?? [] ) ) );
            foreach ( $urls as $url ) {
                if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    continue;
                }
                $content = $this->fetch_url_content( $url );
                if ( $content ) {
                    $web_context .= "\n\n=== Webinhalt: {$url} ===\n" . $content;
                }
            }
        }

        $user_prompt   = $this->build_user_prompt( $context, $web_context );
        $system_prompt = $this->build_system_with_capabilities( $capabilities );

        $params = [
            'model'       => $this->settings['model'],
            'max_tokens'  => $this->settings['max_tokens'],
            'temperature' => $this->settings['temperature'],
        ];

        return $this->ask( $user_prompt, $system_prompt, $params );
    }

    /**
     * Baut den Nutzer-Prompt aus dem Kontext auf.
     * Injiziert alle verfügbaren Vorgänger-Ergebnisse.
     */
    private function build_user_prompt( array $context, string $web_context ): string {
        $topic    = $context['topic']    ?? '';
        $keywords = $context['keywords'] ?? '';

        $parts = [];
        if ( $topic )    { $parts[] = "**Thema:** {$topic}"; }
        if ( $keywords ) { $parts[] = "**Keywords:** {$keywords}"; }

        // Alle verfügbaren Kontext-Ergebnisse einfügen (result-Keys)
        $result_keys = [
            'analysis_result'  => 'Content-Analyse',
            'audience_result'  => 'Zielgruppenanalyse',
            'keyword_result'   => 'Keyword-Recherche',
            'research_result'  => 'Recherche & Fakten',
        ];

        foreach ( $result_keys as $key => $label ) {
            if ( ! empty( $context[ $key ] ) ) {
                $parts[] = "**{$label}:**\n" . $this->truncate( $context[ $key ], 600 );
            }
        }

        // Custom-Agent-Ergebnisse (alles was auf _result endet, nicht bereits oben erfasst)
        foreach ( $context as $key => $value ) {
            if (
                is_string( $value ) && ! empty( $value )
                && str_ends_with( $key, '_result' )
                && ! isset( $result_keys[ $key ] )
            ) {
                $parts[] = "**{$key}:**\n" . $this->truncate( $value, 600 );
            }
        }

        if ( $web_context ) {
            $parts[] = "**Web-Recherche (aktuelle Inhalte):**\n" . $this->truncate( $web_context, 4000 );
        }

        return implode( "\n\n", $parts );
    }

    /**
     * Erweitert den System-Prompt um Capability-Hinweise.
     */
    private function build_system_with_capabilities( array $capabilities ): string {
        $system = $this->config['system_prompt'] ?? '';

        if ( ! empty( $capabilities['web_search']['enabled'] ) ) {
            $system .= "\n\nDir werden aktuelle Web-Inhalte als Kontext übergeben. Nutze diese Informationen in deiner Analyse und beziehe dich konkret darauf.";
        }

        if ( ! empty( $capabilities['coding'] ) ) {
            $system .= "\n\nDu kannst Code-Beispiele, technische Implementierungen und Programmierkonzepte in deine Ausgabe integrieren. Verwende Markdown-Code-Blöcke (` ``` `) für Code-Snippets und erkläre den Code verständlich.";
        }

        return $system;
    }

    /**
     * Ruft den Inhalt einer URL ab und gibt ihn als bereinigten Text zurück.
     */
    private function fetch_url_content( string $url ): string {
        $response = wp_remote_get( $url, [
            'timeout'    => 15,
            'user-agent' => 'Mozilla/5.0 (compatible; WP-AI-Content-Agent/1.0)',
        ] );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            return '';
        }

        $body = wp_remote_retrieve_body( $response );
        // HTML-Tags entfernen, Whitespace normalisieren
        $text = wp_strip_all_tags( $body );
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( mb_substr( $text, 0, 5000 ) );
    }
}
