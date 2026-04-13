<?php
namespace AICA\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin-Controller: Registriert Menüs, Seiten und Assets.
 */
class Admin {

    private static ?Admin $instance = null;

    private function __construct() {}

    public static function get_instance(): Admin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_aica_save_settings', [ $this, 'handle_save_settings' ] );
        add_filter( 'plugin_action_links_' . AICA_PLUGIN_BASENAME, [ $this, 'add_plugin_links' ] );
    }

    public function register_menu(): void {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 11H9v-2h2v2zm0-4H9V5h2v4z"/></svg>'
        );

        add_menu_page(
            'WP AI Content Agent',
            'AI Content Agent',
            'edit_posts',
            'aica',
            [ $this, 'page_dashboard' ],
            $icon,
            30
        );

        add_submenu_page( 'aica', 'Dashboard',             'Dashboard',             'edit_posts',      'aica',               [ $this, 'page_dashboard' ] );
        add_submenu_page( 'aica', 'Artikel generieren',    'Artikel generieren',    'edit_posts',      'aica-generate',      [ $this, 'page_generate' ] );
        add_submenu_page( 'aica', 'Automatisierungs-Jobs', 'Automatisierungs-Jobs', 'manage_options',  'aica-jobs',          [ $this, 'page_jobs' ] );
        add_submenu_page( 'aica', 'Generierter Content',   'Content-Verlauf',       'edit_posts',      'aica-content',       [ $this, 'page_content' ] );
        add_submenu_page( 'aica', 'Voice Profile',         'Voice Profile',         'manage_options',  'aica-voices',        [ $this, 'page_voices' ] );
        add_submenu_page( 'aica', 'Pipelines',              'Pipelines',             'manage_options',  'aica-pipelines',     [ $this, 'page_pipelines' ] );
        add_submenu_page( 'aica', 'Agenten',               'Agenten',               'manage_options',  'aica-agents',        [ $this, 'page_agents' ] );
        add_submenu_page( 'aica', 'Usage & Kosten',          'Usage & Kosten',        'manage_options',  'aica-usage',         [ $this, 'page_usage' ] );
        add_submenu_page( 'aica', 'Website-Gedächtnis',    'Website-Gedächtnis',    'manage_options',  'aica-memory',        [ $this, 'page_memory' ] );
        add_submenu_page( 'aica', 'Einstellungen',         'Einstellungen',         'manage_options',  'aica-settings',      [ $this, 'page_settings' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( strpos( $hook, 'aica' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'aica-admin',
            AICA_PLUGIN_URL . 'admin/assets/css/admin.css',
            [],
            AICA_VERSION
        );

        // SortableJS für den Pipeline-Builder
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
            [],
            '1.15.6',
            true
        );

        wp_enqueue_script(
            'aica-admin',
            AICA_PLUGIN_URL . 'admin/assets/js/admin.js',
            [ 'jquery', 'sortablejs' ],
            AICA_VERSION,
            true
        );

        global $wpdb;

        // Alle gespeicherten Pipelines für Job-Modal und Pipeline-Seite
        $pipelines_raw = $wpdb->get_results(
            "SELECT id, name, description, steps FROM {$wpdb->prefix}aica_pipelines ORDER BY name ASC"
        ) ?: [];
        $pipelines = array_map( function( $p ) {
            return [
                'id'          => (int) $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'steps'       => json_decode( $p->steps, true ) ?: [],
            ];
        }, $pipelines_raw );

        // Built-in Agenten (PHP-Klassen)
        $builtin_agents = [
            [ 'key' => 'content_analyzer',   'name' => 'Content-Analyst',    'icon' => 'CA', 'result_key' => 'analysis_result', 'is_builtin' => true ],
            [ 'key' => 'audience_analyzer',  'name' => 'Zielgruppenanalyst', 'icon' => 'ZA', 'result_key' => 'audience_result', 'is_builtin' => true ],
            [ 'key' => 'keyword_researcher', 'name' => 'Keyword-Rechercheur','icon' => 'KW', 'result_key' => 'keyword_result',  'is_builtin' => true ],
            [ 'key' => 'researcher',         'name' => 'Tiefenrechercheur',  'icon' => 'TR', 'result_key' => 'research_result', 'is_builtin' => true ],
            [ 'key' => 'content_writer',     'name' => 'Content-Autor',      'icon' => 'CW', 'result_key' => 'final_content',   'is_builtin' => true ],
        ];

        // Custom Agenten aus DB
        $custom_agents_raw = $wpdb->get_results(
            "SELECT id, agent_key, name, icon, description, model, max_tokens, temperature, result_key, capabilities
             FROM {$wpdb->prefix}aica_agents ORDER BY name ASC"
        ) ?: [];
        $custom_agents = array_map( function( $a ) {
            $caps = json_decode( $a->capabilities ?? '{}', true ) ?: [];
            return [
                'id'           => (int) $a->id,
                'key'          => $a->agent_key,
                'name'         => $a->name,
                'icon'         => $a->icon,
                'description'  => $a->description ?? '',
                'model'        => $a->model,
                'max_tokens'   => (int) $a->max_tokens,
                'temperature'  => (float) $a->temperature,
                'result_key'   => $a->result_key,
                'is_builtin'   => false,
                'capabilities' => $caps,
            ];
        }, $custom_agents_raw );

        wp_localize_script( 'aica-admin', 'aicaData', [
            'nonce'          => wp_create_nonce( 'aica_nonce' ),
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'adminUrl'       => admin_url( 'admin.php' ),
            'pipelines'      => $pipelines,
            'agents'         => array_merge( $builtin_agents, array_values( $custom_agents ) ),
            'agentSettings'  => \AICA\Settings::get_all_agent_settings(),
            'defaultModel'   => \AICA\Settings::get_model(),
            'models'         => \AICA\Settings::get_available_models(),
            'gitRepos'       => \AICA\Settings::get_git_repositories(),
            'memoryEnabled'  => \AICA\Settings::is_memory_enabled(),
            'memoryModel'    => \AICA\Settings::get_memory_model(),
            'i18n'           => [
                'generating'   => 'Generierung läuft...',
                'done'         => 'Abgeschlossen!',
                'error'        => 'Fehler aufgetreten.',
                'confirm_del'  => 'Wirklich löschen?',
                'testing'      => 'Verbindung wird getestet...',
                'saving'       => 'Wird gespeichert...',
            ],
        ] );
    }

    public function handle_save_settings(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        check_admin_referer( 'aica_save_settings' );

        \AICA\Settings::save_from_request( $_POST );

        wp_safe_redirect( admin_url( 'admin.php?page=aica-settings&saved=1' ) );
        exit;
    }

    public function add_plugin_links( array $links ): array {
        $links[] = '<a href="' . admin_url( 'admin.php?page=aica-settings' ) . '">Einstellungen</a>';
        $links[] = '<a href="' . admin_url( 'admin.php?page=aica-generate' ) . '">Artikel generieren</a>';
        return $links;
    }

    // Page Callbacks
    public function page_dashboard(): void { $this->render( 'dashboard' ); }
    public function page_generate():  void { $this->render( 'generate' );  }
    public function page_jobs():      void { $this->render( 'jobs' );      }
    public function page_content():   void { $this->render( 'content' );   }
    public function page_voices():    void { $this->render( 'voices' );    }
    public function page_pipelines(): void { $this->render( 'pipelines' ); }
    public function page_agents():    void { $this->render( 'agents' );    }
    public function page_usage():     void { $this->render( 'usage' );     }
    public function page_memory():    void { $this->render( 'memory' );    }
    public function page_settings():  void { $this->render( 'settings' );  }

    private function render( string $template ): void {
        $file = AICA_PLUGIN_DIR . "admin/templates/{$template}.php";
        if ( file_exists( $file ) ) {
            include $file;
        } else {
            echo '<div class="wrap"><p>Template nicht gefunden: ' . esc_html( $template ) . '</p></div>';
        }
    }
}
