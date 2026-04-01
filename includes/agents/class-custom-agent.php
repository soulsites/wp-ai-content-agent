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

        // Git-Repository-Kontext einlesen (falls Git Repo aktiviert)
        $git_context = '';
        if ( ! empty( $capabilities['git_repo']['enabled'] ) ) {
            $repo_index = (int) ( $capabilities['git_repo']['repo_index'] ?? 0 );
            $repos      = \AICA\Settings::get_git_repositories();
            if ( isset( $repos[ $repo_index ] ) ) {
                $git_context = $this->read_repository_context(
                    $repos[ $repo_index ]['path'],
                    $repos[ $repo_index ]['name']
                );
            }
        }

        $user_prompt   = $this->build_user_prompt( $context, $web_context, $git_context );
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
    private function build_user_prompt( array $context, string $web_context, string $git_context = '' ): string {
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

        if ( $git_context ) {
            $parts[] = "**Git-Repository (Quellcode & Struktur):**\n" . $this->truncate( $git_context, 12000 );
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

        if ( ! empty( $capabilities['claude_code'] ) ) {
            $system .= "\n\nDu arbeitest als spezialisierter Code-Dokumentations-Experte im Claude-Code-Stil. Deine Ausgaben sind präzise, strukturiert und entwicklerfreundlich: Erkläre Architekturen, Klassen, Funktionen und deren Zusammenspiel klar und verständlich. Verwende Markdown-Formatierung mit Überschriften, Code-Blöcken und Listen. Halte dich an das DRY-Prinzip in deinen Erklärungen und fokussiere dich auf das Wesentliche.";
        }

        if ( ! empty( $capabilities['git_repo']['enabled'] ) ) {
            $system .= "\n\nDir wird der Inhalt eines Git-Repositories als Kontext bereitgestellt (Dateistruktur und Quelldateien). Nutze diesen Code als Basis für deine Analyse und Dokumentation. Beziehe dich konkret auf vorhandene Klassen, Funktionen und Dateien.";
        }

        return $system;
    }

    /**
     * Liest den Inhalt eines Repositories und gibt ihn als formatierten Kontext zurück.
     */
    private function read_repository_context( string $path, string $name ): string {
        if ( ! is_dir( $path ) ) {
            return '';
        }

        $files = $this->scan_repository( $path );
        if ( empty( $files ) ) {
            return '';
        }

        // Dateibaum aufbauen
        $tree_lines = [];
        foreach ( $files as $file ) {
            $rel = ltrim( str_replace( $path, '', $file ), DIRECTORY_SEPARATOR );
            $tree_lines[] = '  ' . $rel;
        }
        $tree = implode( "\n", $tree_lines );

        // Dateiinhalte lesen (bis zum Zeichenlimit)
        $contents   = '';
        $total_chars = 0;
        $max_chars   = 18000;

        foreach ( $files as $file ) {
            if ( $total_chars >= $max_chars ) {
                break;
            }
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents( $file );
            if ( false === $content || '' === trim( $content ) ) {
                continue;
            }

            $rel     = ltrim( str_replace( $path, '', $file ), DIRECTORY_SEPARATOR );
            $allowed = $max_chars - $total_chars;
            $excerpt = mb_substr( $content, 0, min( 3000, $allowed ) );

            $contents   .= "\n\n--- {$rel} ---\n" . $excerpt;
            $total_chars += mb_strlen( $excerpt );
        }

        return "Repository: {$name}\nPfad: {$path}\n\nDateistruktur:\n{$tree}\n\nDateiinhalte:{$contents}";
    }

    /**
     * Scannt ein Repository rekursiv und gibt eine Liste relevanter Dateipfade zurück.
     */
    private function scan_repository( string $path, int $depth = 0 ): array {
        if ( $depth > 5 ) {
            return [];
        }

        static $skip_dirs = [ '.git', 'vendor', 'node_modules', '.cache', 'dist', 'build', '__pycache__', '.idea', '.vscode', 'coverage', 'tmp' ];
        static $code_exts = [ 'php', 'js', 'ts', 'tsx', 'jsx', 'py', 'rb', 'java', 'go', 'rs', 'cs', 'cpp', 'c', 'h', 'css', 'scss', 'html', 'md', 'txt', 'yaml', 'yml', 'json', 'xml', 'sql', 'sh', 'env.example' ];

        $files = [];
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $items = @scandir( $path );
        if ( false === $items ) {
            return [];
        }

        foreach ( $items as $item ) {
            if ( '.' === $item || '..' === $item ) {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;

            // Sicherstellen, dass der Pfad innerhalb des Repositories bleibt
            $real = realpath( $full );
            if ( ! $real || strpos( $real, realpath( $path ) ) !== 0 ) {
                continue;
            }

            if ( is_dir( $full ) ) {
                if ( ! in_array( $item, $skip_dirs, true ) ) {
                    $sub   = $this->scan_repository( $full, $depth + 1 );
                    $files = array_merge( $files, $sub );
                }
            } elseif ( is_file( $full ) ) {
                $ext = strtolower( pathinfo( $item, PATHINFO_EXTENSION ) );
                if ( in_array( $ext, $code_exts, true ) ) {
                    $files[] = $full;
                }
                // Sonderdatei: README ohne Extension
                if ( in_array( strtolower( $item ), [ 'readme', 'makefile', 'dockerfile', '.env.example' ], true ) ) {
                    $files[] = $full;
                }
            }

            // Max. 200 Dateien pro Repository
            if ( count( $files ) >= 200 ) {
                break;
            }
        }

        return $files;
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
