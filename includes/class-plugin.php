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
        require_once AICA_PLUGIN_DIR . 'includes/class-installer.php';
        Installer::maybe_upgrade();
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
        require_once AICA_PLUGIN_DIR . 'includes/agents/class-custom-agent.php';
        require_once AICA_PLUGIN_DIR . 'includes/class-orchestrator.php';
        require_once AICA_PLUGIN_DIR . 'includes/class-pipeline-runner.php';
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
        add_action( 'wp_ajax_aica_save_pipeline',    [ $this, 'ajax_save_pipeline' ] );
        add_action( 'wp_ajax_aica_delete_pipeline',  [ $this, 'ajax_delete_pipeline' ] );
        add_action( 'wp_ajax_aica_get_pipelines',    [ $this, 'ajax_get_pipelines' ] );
        add_action( 'wp_ajax_aica_save_agent',                  [ $this, 'ajax_save_agent' ] );
        add_action( 'wp_ajax_aica_delete_agent',                [ $this, 'ajax_delete_agent' ] );
        add_action( 'wp_ajax_aica_save_builtin_agent_settings', [ $this, 'ajax_save_builtin_agent_settings' ] );
    }

    public function ajax_generate_content(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ], 403 );
        }

        $topic       = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
        $keywords    = sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) );
        $pipeline_id = absint( $_POST['pipeline_id'] ?? 0 );
        $voice_id    = absint( $_POST['voice_id'] ?? 0 );
        $post_status = sanitize_key( $_POST['post_status'] ?? 'draft' );
        $category_id = absint( $_POST['category_id'] ?? 0 );

        if ( empty( $topic ) ) {
            wp_send_json_error( [ 'message' => 'Bitte ein Thema angeben.' ] );
        }

        $orchestrator = new Orchestrator();
        $content_id   = $orchestrator->create_generation_job( [
            'topic'       => $topic,
            'keywords'    => $keywords,
            'pipeline_id' => $pipeline_id,
            'voice_id'    => $voice_id,
            'post_status' => $post_status,
            'category_id' => $category_id,
        ] );

        if ( is_wp_error( $content_id ) ) {
            wp_send_json_error( [ 'message' => $content_id->get_error_message() ] );
        }

        // Generierung erst NACH dem Senden der JSON-Antwort starten (via shutdown-Hook).
        // Dadurch kann kein PHP-Output (Fatal Errors, Notices, Debug-HTML) die
        // AJAX-Antwort korrumpieren – JSON ist bereits clean gesendet, bevor die
        // eigentliche Generierung beginnt.
        $cid  = $content_id;
        $orch = $orchestrator;
        add_action( 'shutdown', static function () use ( $cid, $orch ) {
            // Verbindung zum Browser schließen; PHP-Prozess läuft im Hintergrund weiter.
            if ( function_exists( 'fastcgi_finish_request' ) ) {
                fastcgi_finish_request();
            } elseif ( function_exists( 'litespeed_finish_request' ) ) {
                litespeed_finish_request();
            }
            ignore_user_abort( true );
            set_time_limit( 0 );
            $orch->process_content( $cid );
        } );

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
            'pipeline_id' => absint( $_POST['pipeline_id'] ?? 0 ) ?: null,
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

    public function ajax_save_pipeline(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        global $wpdb;
        $table       = $wpdb->prefix . 'aica_pipelines';
        $pipeline_id = absint( $_POST['pipeline_id'] ?? 0 );
        $name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );

        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => 'Bitte einen Namen angeben.' ] );
        }

        // Steps validieren und sanitieren
        $raw_steps = wp_unslash( $_POST['steps'] ?? '[]' );
        $steps     = json_decode( $raw_steps, true );

        if ( ! is_array( $steps ) ) {
            wp_send_json_error( [ 'message' => 'Ungültige Steps.' ] );
        }

        // Builtin + alle custom agents aus DB als gültig einstufen
        $builtin_agents   = [ 'content_analyzer', 'audience_analyzer', 'keyword_researcher', 'researcher', 'content_writer' ];
        $custom_agent_keys = $wpdb->get_col( "SELECT agent_key FROM {$wpdb->prefix}aica_agents" ) ?: [];
        $valid_agents    = array_merge( $builtin_agents, $custom_agent_keys );
        $valid_operators = [ 'contains', 'not_contains', 'length_gt', 'length_lt' ];
        $valid_actions   = [ 'continue', 'skip', 'stop' ];

        $clean_steps = [];
        foreach ( $steps as $step ) {
            $agent = sanitize_key( $step['agent'] ?? '' );
            if ( ! in_array( $agent, $valid_agents, true ) ) {
                continue;
            }

            $clean_conditions = [];
            foreach ( (array) ( $step['conditions'] ?? [] ) as $cond ) {
                $operator = sanitize_key( $cond['operator'] ?? 'contains' );
                if ( ! in_array( $operator, $valid_operators, true ) ) {
                    $operator = 'contains';
                }
                $on_match    = sanitize_key( $cond['on_match']    ?? 'continue' );
                $on_no_match = sanitize_key( $cond['on_no_match'] ?? 'continue' );
                if ( ! in_array( $on_match,    $valid_actions, true ) ) { $on_match    = 'continue'; }
                if ( ! in_array( $on_no_match, $valid_actions, true ) ) { $on_no_match = 'continue'; }

                $clean_conditions[] = [
                    'source'      => sanitize_key( $cond['source'] ?? '' ),
                    'operator'    => $operator,
                    'value'       => sanitize_text_field( $cond['value'] ?? '' ),
                    'on_match'    => $on_match,
                    'on_no_match' => $on_no_match,
                ];
            }

            $clean_steps[] = [
                'id'         => sanitize_key( $step['id'] ?? uniqid( 'step_' ) ),
                'agent'      => $agent,
                'enabled'    => ! empty( $step['enabled'] ),
                'conditions' => $clean_conditions,
            ];
        }

        $data = [
            'name'        => $name,
            'description' => $description,
            'steps'       => wp_json_encode( $clean_steps ),
        ];

        if ( $pipeline_id ) {
            $wpdb->update( $table, $data, [ 'id' => $pipeline_id ], null, [ '%d' ] );
        } else {
            $wpdb->insert( $table, $data );
            $pipeline_id = (int) $wpdb->insert_id;
        }

        wp_send_json_success( [ 'pipeline_id' => $pipeline_id ] );
    }

    public function ajax_delete_pipeline(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $pipeline_id = absint( $_POST['pipeline_id'] ?? 0 );
        if ( ! $pipeline_id ) {
            wp_send_json_error( [ 'message' => 'Keine Pipeline-ID.' ] );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'aica_pipelines', [ 'id' => $pipeline_id ], [ '%d' ] );
        // Jobs auf Standard-Pipeline zurücksetzen
        $wpdb->update(
            $wpdb->prefix . 'aica_jobs',
            [ 'pipeline_id' => null ],
            [ 'pipeline_id' => $pipeline_id ],
            [ null ],
            [ '%d' ]
        );

        wp_send_json_success();
    }

    public function ajax_get_pipelines(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, name, description, steps, created_at FROM {$wpdb->prefix}aica_pipelines ORDER BY name ASC"
        );

        $pipelines = array_map( function ( $row ) {
            $steps = json_decode( $row->steps, true ) ?: [];
            return [
                'id'          => (int) $row->id,
                'name'        => $row->name,
                'description' => $row->description,
                'steps'       => $steps,
                'step_count'  => count( $steps ),
                'created_at'  => $row->created_at,
            ];
        }, $rows ?: [] );

        wp_send_json_success( [ 'pipelines' => $pipelines ] );
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

    public function ajax_save_agent(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        global $wpdb;
        $table    = $wpdb->prefix . 'aica_agents';
        $agent_id = absint( $_POST['agent_id'] ?? 0 );

        $agent_key = sanitize_key( wp_unslash( $_POST['agent_key'] ?? '' ) );
        if ( empty( $agent_key ) ) {
            wp_send_json_error( [ 'message' => 'Bitte einen Agent-Key angeben.' ] );
        }

        // Prüfen ob agent_key schon vergeben ist (bei neuem Agenten)
        if ( ! $agent_id ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE agent_key = %s",
                $agent_key
            ) );
            if ( $exists ) {
                wp_send_json_error( [ 'message' => "Agent-Key '{$agent_key}' ist bereits vergeben." ] );
            }
        }

        // Capabilities: JS sendet als JSON-String
        $caps_raw     = wp_unslash( $_POST['capabilities'] ?? '{}' );
        $caps_decoded = json_decode( $caps_raw, true );

        if ( ! is_array( $caps_decoded ) ) {
            $caps_decoded = [];
        }

        // URLs in capabilities bereinigen
        if ( ! empty( $caps_decoded['web_search']['urls'] ) && is_array( $caps_decoded['web_search']['urls'] ) ) {
            $caps_decoded['web_search']['urls'] = array_values(
                array_filter( array_map( 'esc_url_raw', $caps_decoded['web_search']['urls'] ) )
            );
        }

        $capabilities = wp_json_encode( $caps_decoded );

        $data = [
            'name'          => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'icon'          => sanitize_text_field( wp_unslash( $_POST['icon'] ?? '🤖' ) ),
            'description'   => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'system_prompt' => sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ?? '' ) ),
            'model'         => sanitize_key( $_POST['model'] ?? 'claude-opus-4-6' ),
            'max_tokens'    => min( 50000, max( 100, absint( $_POST['max_tokens'] ?? 2000 ) ) ),
            'temperature'   => min( 1.0, max( 0.0, (float) ( $_POST['temperature'] ?? 0.5 ) ) ),
            'result_key'    => sanitize_key( wp_unslash( $_POST['result_key'] ?? $agent_key . '_result' ) ),
            'capabilities'  => $capabilities,
        ];

        if ( $agent_id ) {
            $result = $wpdb->update( $table, $data, [ 'id' => $agent_id ], null, [ '%d' ] );
        } else {
            $data['agent_key'] = $agent_key;
            $result   = $wpdb->insert( $table, $data );
            $agent_id = (int) $wpdb->insert_id;
        }

        if ( $result === false || ( ! $agent_id && empty( $_POST['agent_id'] ) ) ) {
            wp_send_json_error( [ 'message' => 'Datenbankfehler: ' . $wpdb->last_error ] );
        }

        wp_send_json_success( [ 'agent_id' => $agent_id ] );
    }

    public function ajax_save_builtin_agent_settings(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $valid_keys = [ 'content_analyzer', 'audience_analyzer', 'keyword_researcher', 'researcher', 'content_writer' ];
        $agent_key  = sanitize_key( wp_unslash( $_POST['agent_key'] ?? '' ) );

        if ( ! in_array( $agent_key, $valid_keys, true ) ) {
            wp_send_json_error( [ 'message' => 'Ungültiger Agent-Key.' ] );
        }

        $settings               = Settings::get_all_agent_settings();
        $settings[ $agent_key ] = [
            'model'         => sanitize_key( $_POST['model'] ?? Settings::get_model() ),
            'max_tokens'    => min( 50000, max( 100, absint( $_POST['max_tokens'] ?? 2000 ) ) ),
            'temperature'   => min( 1.0, max( 0.0, (float) ( $_POST['temperature'] ?? 0.5 ) ) ),
            'system_prompt' => sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ?? '' ) ),
        ];

        update_option( 'aica_agent_settings', $settings );
        wp_send_json_success( [ 'message' => 'Einstellungen gespeichert.' ] );
    }

    public function ajax_delete_agent(): void {
        check_ajax_referer( 'aica_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [], 403 );
        }

        $agent_id = absint( $_POST['agent_id'] ?? 0 );
        if ( ! $agent_id ) {
            wp_send_json_error( [ 'message' => 'Keine Agent-ID.' ] );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'aica_agents', [ 'id' => $agent_id ], [ '%d' ] );

        wp_send_json_success();
    }
}
