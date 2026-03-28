<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Haupt-Plugin-Klasse (Singleton).
 */
class Plugin {

    private static ?Plugin $instance = null;

    private function __construct() {}

    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void {
        require_once AICA_PLUGIN_DIR . 'includes/class-settings.php';
        require_once AICA_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-agent-base.php';
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-content-analyzer.php';
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-audience-analyzer.php';
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-keyword-researcher.php';
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-researcher.php';
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-content-writer.php';
        require_once AICA_PLUGIN_DIR . 'includes/class-orchestrator.php';
        require_once AICA_PLUGIN_DIR . 'includes/class-cron-manager.php';
        require_once AICA_PLUGIN_DIR . 'includes/class-post-publisher.php';

        if ( is_admin() ) {
            require_once AICA_PLUGIN_DIR . 'admin/class-admin.php';
            Admin\Admin::get_instance()->init();
        }
    }

    private function init_hooks(): void {
        // Cron-Manager initialisieren
        Cron_Manager::get_instance()->init();

        // AJAX-Handler für Frontend-/Admin-Requests
        add_action( 'wp_ajax_aica_generate_content', [ $this, 'ajax_generate_content' ] );
        add_action( 'wp_ajax_aica_get_job_status',   [ $this, 'ajax_get_job_status' ] );
        add_action( 'wp_ajax_aica_delete_content',   [ $this, 'ajax_delete_content' ] );
        add_action( 'wp_ajax_aica_test_api',         [ $this, 'ajax_test_api' ] );
        add_action( 'wp_ajax_aica_save_voice',       [ $this, 'ajax_save_voice' ] );
        add_action( 'wp_ajax_aica_delete_voice',     [ $this, 'ajax_delete_voice' ] );
        add_action( 'wp_ajax_aica_save_job',         [ $this, 'ajax_save_job' ] );
        add_action( 'wp_ajax_aica_delete_job',       [ $this, 'ajax_delete_job' ] );
        add_action( 'wp_ajax_aica_run_job_now',      [ $this, 'ajax_run_job_now' ] );
    }

    public function ajax_generate_content(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
        }

        $topic    = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
        $keywords = sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) );
        $voice_id = absint( $_POST['voice_id'] ?? 0 );
        $post_status = sanitize_key( $_POST['post_status'] ?? 'draft' );
        $category_id = absint( $_POST['category_id'] ?? 0 );

        if ( empty( $topic ) ) {
            wp_send_json_error( [ 'message' => 'Bitte ein Thema angeben.' ] );
        }

        $orchestrator = new Orchestrator();
        $content_id   = $orchestrator->create_generation_job( [
            'topic'       => $topic,
            'keywords'    => $keywords,
            'voice_id'    => $voice_id,
            'post_status' => $post_status,
            'category_id' => $category_id,
        ] );

        if ( is_wp_error( $content_id ) ) {
            wp_send_json_error( [ 'message' => $content_id->get_error_message() ] );
        }

        // Sofort im Background starten
        $orchestrator->run_async( $content_id );

        wp_send_json_success( [
            'content_id' => $content_id,
            'message'    => 'Generierung gestartet.',
        ] );
    }

    public function ajax_get_job_status(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [], 403 );
        }

        $content_id = absint( $_GET['content_id'] ?? 0 );
        if ( ! $content_id ) {
            wp_send_json_error( [ 'message' => 'Keine Content-ID.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'aica_content';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $content_id ) );

        if ( ! $row ) {
            wp_send_json_error( [ 'message' => 'Content nicht gefunden.' ] );
        }

        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT agent, level, message, created_at FROM {$wpdb->prefix}aica_logs WHERE content_id = %d ORDER BY id ASC",
            $content_id
        ) );

        wp_send_json_success( [
            'status'        => $row->status,
            'word_count'    => $row->word_count,
            'seo_score'     => $row->seo_score,
            'tokens_used'   => $row->tokens_used,
            'duration_sec'  => $row->duration_sec,
            'wp_post_id'    => $row->wp_post_id,
            'error_message' => $row->error_message,
            'logs'          => $logs,
        ] );
    }

    public function ajax_delete_content(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [], 403 );
        }

        $content_id = absint( $_POST['content_id'] ?? 0 );
        if ( ! $content_id ) {
            wp_send_json_error( [ 'message' => 'Keine Content-ID.' ] );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'aica_content', [ 'id' => $content_id ], [ '%d' ] );
        $wpdb->delete( $wpdb->prefix . 'aica_logs', [ 'content_id' => $content_id ], [ '%d' ] );

        wp_send_json_success( [ 'message' => 'Eintrag gelöscht.' ] );
    }

    public function ajax_test_api(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $api_key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
        $client  = new API_Client( $api_key );
        $result  = $client->test_connection();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Verbindung erfolgreich! Modell: ' . $result ] );
    }

    public function ajax_save_voice(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $profiles = get_option( 'aica_voice_profiles', [] );
        $voice_id = absint( $_POST['voice_id'] ?? 0 );

        $profile = [
            'id'          => $voice_id ?: ( count( $profiles ) > 0 ? max( array_column( $profiles, 'id' ) ) + 1 : 1 ),
            'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'tone'        => sanitize_text_field( wp_unslash( $_POST['tone'] ?? '' ) ),
            'style'       => sanitize_textarea_field( wp_unslash( $_POST['style'] ?? '' ) ),
            'example'     => sanitize_textarea_field( wp_unslash( $_POST['example'] ?? '' ) ),
            'avoid'       => sanitize_textarea_field( wp_unslash( $_POST['avoid'] ?? '' ) ),
            'evaluations' => [],
        ];

        if ( $voice_id ) {
            foreach ( $profiles as $k => $p ) {
                if ( (int) $p['id'] === $voice_id ) {
                    $profile['evaluations'] = $p['evaluations'] ?? [];
                    $profiles[ $k ]         = $profile;
                    break;
                }
            }
        } else {
            $profiles[] = $profile;
        }

        update_option( 'aica_voice_profiles', $profiles );
        wp_send_json_success( [ 'profile' => $profile ] );
    }

    public function ajax_delete_voice(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $voice_id = absint( $_POST['voice_id'] ?? 0 );
        $profiles = get_option( 'aica_voice_profiles', [] );
        $profiles = array_values( array_filter( $profiles, fn( $p ) => (int) $p['id'] !== $voice_id ) );
        update_option( 'aica_voice_profiles', $profiles );

        wp_send_json_success();
    }

    public function ajax_save_job(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'aica_jobs';
        $job_id   = absint( $_POST['job_id'] ?? 0 );
        $schedule = sanitize_key( $_POST['schedule'] ?? 'manual' );

        $data = [
            'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'topic'       => sanitize_textarea_field( wp_unslash( $_POST['topic'] ?? '' ) ),
            'keywords'    => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
            'voice_id'    => absint( $_POST['voice_id'] ?? 0 ) ?: null,
            'post_status' => sanitize_key( $_POST['post_status'] ?? 'draft' ),
            'category_id' => absint( $_POST['category_id'] ?? 0 ) ?: null,
            'schedule'    => $schedule,
            'active'      => 1,
            'settings'    => wp_json_encode( [
                'add_featured_image' => ! empty( $_POST['add_featured_image'] ),
                'min_word_count'     => absint( $_POST['min_word_count'] ?? 800 ),
            ] ),
        ];

        if ( $job_id ) {
            $wpdb->update( $table, $data, [ 'id' => $job_id ], null, [ '%d' ] );
        } else {
            $wpdb->insert( $table, $data );
            $job_id = $wpdb->insert_id;
        }

        // Cron-Hook setzen
        if ( 'manual' !== $schedule ) {
            $hook = 'aica_run_job_' . $job_id;
            $wpdb->update( $table, [ 'cron_hook' => $hook ], [ 'id' => $job_id ] );
            Cron_Manager::get_instance()->schedule_job( $job_id, $hook, $schedule );
        }

        wp_send_json_success( [ 'job_id' => $job_id ] );
    }

    public function ajax_delete_job(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $job_id = absint( $_POST['job_id'] ?? 0 );
        Cron_Manager::get_instance()->unschedule_job( $job_id );

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'aica_jobs', [ 'id' => $job_id ], [ '%d' ] );

        wp_send_json_success();
    }

    public function ajax_run_job_now(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [], 403 );
        }

        $job_id = absint( $_POST['job_id'] ?? 0 );
        if ( ! $job_id ) {
            wp_send_json_error( [ 'message' => 'Keine Job-ID.' ] );
        }

        $content_id = Cron_Manager::get_instance()->run_job( $job_id );
        if ( is_wp_error( $content_id ) ) {
            wp_send_json_error( [ 'message' => $content_id->get_error_message() ] );
        }

        wp_send_json_success( [ 'content_id' => $content_id, 'message' => 'Job gestartet.' ] );
    }
}
