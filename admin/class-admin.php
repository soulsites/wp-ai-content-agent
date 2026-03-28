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
        add_submenu_page( 'aica', 'Agenten',               'Agenten',               'manage_options',  'aica-agents',        [ $this, 'page_agents' ] );
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

        wp_enqueue_script(
            'aica-admin',
            AICA_PLUGIN_URL . 'admin/assets/js/admin.js',
            [ 'jquery' ],
            AICA_VERSION,
            true
        );

        wp_localize_script( 'aica-admin', 'aicaData', [
            'nonce'    => wp_create_nonce( 'aica_nonce' ),
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'adminUrl' => admin_url( 'admin.php' ),
            'i18n'     => [
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
    public function page_agents():    void { $this->render( 'agents' );    }
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
