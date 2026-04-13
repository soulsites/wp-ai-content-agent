<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Installiert und deinstalliert das Plugin (Datenbanktabellen, Standardoptionen).
 */
class Installer {

    /** Tabellenpräfix für Custom Tables */
    const TABLE_JOBS      = 'aica_jobs';
    const TABLE_CONTENT   = 'aica_content';
    const TABLE_LOGS      = 'aica_logs';
    const TABLE_PIPELINES = 'aica_pipelines';
    const TABLE_AGENTS    = 'aica_agents';
    const TABLE_MEMORY    = 'aica_memory';
    const DB_VERSION      = '1.3.0';

    /**
     * Prüft ob das DB-Schema aktuell ist und führt ggf. ein Upgrade durch.
     * Wird bei jedem Plugin-Laden aufgerufen (nur aktiv wenn Version veraltet).
     */
    public static function maybe_upgrade(): void {
        if ( get_option( 'aica_db_version' ) !== self::DB_VERSION ) {
            self::install();
            self::add_missing_columns();
        }
        // Standard-Agenten immer sicherstellen (idempotent)
        self::insert_default_agents();
    }

    /**
     * Fügt fehlende Spalten per ALTER TABLE direkt hinzu.
     * Zuverlässiger als dbDelta() für bestehende Tabellen.
     */
    private static function add_missing_columns(): void {
        global $wpdb;

        // pipeline_id zu wp_aica_jobs hinzufügen
        $jobs_table      = $wpdb->prefix . self::TABLE_JOBS;
        $jobs_existing   = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
                $jobs_table
            )
        );
        if ( ! in_array( 'pipeline_id', $jobs_existing, true ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$jobs_table}` ADD COLUMN `pipeline_id` BIGINT UNSIGNED DEFAULT NULL" );
        }

        $table = $wpdb->prefix . self::TABLE_CONTENT;

        $existing = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
                $table
            )
        );

        $to_add = [
            'job_id'           => 'BIGINT UNSIGNED DEFAULT NULL',
            'pipeline_id'      => 'BIGINT UNSIGNED DEFAULT NULL',
            'voice_id'         => 'BIGINT UNSIGNED DEFAULT NULL',
            'post_status'      => "VARCHAR(20) NOT NULL DEFAULT 'draft'",
            'category_id'      => 'BIGINT UNSIGNED DEFAULT NULL',
            'wp_post_id'       => 'BIGINT UNSIGNED DEFAULT NULL',
            'analysis_result'  => 'LONGTEXT DEFAULT NULL',
            'audience_result'  => 'LONGTEXT DEFAULT NULL',
            'keyword_result'   => 'LONGTEXT DEFAULT NULL',
            'research_result'  => 'LONGTEXT DEFAULT NULL',
            'final_content'    => 'LONGTEXT DEFAULT NULL',
            'meta_title'       => 'VARCHAR(255) DEFAULT NULL',
            'meta_description' => 'TEXT DEFAULT NULL',
            'word_count'       => 'INT UNSIGNED DEFAULT NULL',
            'seo_score'        => 'TINYINT UNSIGNED DEFAULT NULL',
            'tokens_used'      => 'INT UNSIGNED DEFAULT NULL',
            'duration_sec'     => 'FLOAT DEFAULT NULL',
            'error_message'    => 'TEXT DEFAULT NULL',
        ];

        foreach ( $to_add as $column => $definition ) {
            if ( ! in_array( $column, $existing, true ) ) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" );
            }
        }
    }

    public static function install(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Tabelle: Content-Jobs (Cronjob-Definitionen)
        $table_jobs = $wpdb->prefix . self::TABLE_JOBS;
        dbDelta( "CREATE TABLE {$table_jobs} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(255)    NOT NULL,
            topic       TEXT            NOT NULL,
            keywords    TEXT            DEFAULT NULL,
            voice_id    BIGINT UNSIGNED DEFAULT NULL,
            post_status VARCHAR(20)     NOT NULL DEFAULT 'draft',
            category_id BIGINT UNSIGNED DEFAULT NULL,
            schedule    VARCHAR(50)     NOT NULL DEFAULT 'manual',
            cron_hook   VARCHAR(100)    DEFAULT NULL,
            active      TINYINT(1)      NOT NULL DEFAULT 1,
            last_run    DATETIME        DEFAULT NULL,
            next_run    DATETIME        DEFAULT NULL,
            run_count   INT UNSIGNED    NOT NULL DEFAULT 0,
            settings    LONGTEXT        DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_schedule (schedule),
            KEY idx_active  (active)
        ) {$charset_collate};" );

        // Tabelle: Generierter Content (History)
        $table_content = $wpdb->prefix . self::TABLE_CONTENT;
        dbDelta( "CREATE TABLE {$table_content} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id          BIGINT UNSIGNED DEFAULT NULL,
            topic           TEXT            NOT NULL,
            keywords        TEXT            DEFAULT NULL,
            voice_id        BIGINT UNSIGNED DEFAULT NULL,
            post_status     VARCHAR(20)     NOT NULL DEFAULT 'draft',
            category_id     BIGINT UNSIGNED DEFAULT NULL,
            status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
            wp_post_id      BIGINT UNSIGNED DEFAULT NULL,
            analysis_result LONGTEXT        DEFAULT NULL,
            audience_result LONGTEXT        DEFAULT NULL,
            keyword_result  LONGTEXT        DEFAULT NULL,
            research_result LONGTEXT        DEFAULT NULL,
            final_content   LONGTEXT        DEFAULT NULL,
            meta_title      VARCHAR(255)    DEFAULT NULL,
            meta_description TEXT           DEFAULT NULL,
            word_count      INT UNSIGNED    DEFAULT NULL,
            seo_score       TINYINT UNSIGNED DEFAULT NULL,
            tokens_used     INT UNSIGNED    DEFAULT NULL,
            duration_sec    FLOAT           DEFAULT NULL,
            error_message   TEXT            DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_job_id (job_id),
            KEY idx_status (status),
            KEY idx_wp_post_id (wp_post_id)
        ) {$charset_collate};" );

        // Tabelle: Custom Pipelines
        $table_pipelines = $wpdb->prefix . self::TABLE_PIPELINES;
        dbDelta( "CREATE TABLE {$table_pipelines} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(255)    NOT NULL,
            description TEXT            DEFAULT NULL,
            steps       LONGTEXT        NOT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};" );

        // Tabelle: Custom Agents
        $table_agents = $wpdb->prefix . self::TABLE_AGENTS;
        dbDelta( "CREATE TABLE {$table_agents} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            agent_key       VARCHAR(100)    NOT NULL,
            name            VARCHAR(255)    NOT NULL,
            icon            VARCHAR(50)     NOT NULL DEFAULT '🤖',
            description     TEXT            DEFAULT NULL,
            system_prompt   TEXT            DEFAULT NULL,
            model           VARCHAR(100)    NOT NULL DEFAULT 'claude-opus-4-6',
            max_tokens      INT UNSIGNED    NOT NULL DEFAULT 2000,
            temperature     DECIMAL(3,2)    NOT NULL DEFAULT 0.50,
            result_key      VARCHAR(100)    NOT NULL DEFAULT 'custom_result',
            capabilities    LONGTEXT        DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_agent_key (agent_key)
        ) {$charset_collate};" );

        // Tabelle: Agent-Logs
        $table_logs = $wpdb->prefix . self::TABLE_LOGS;
        dbDelta( "CREATE TABLE {$table_logs} (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            content_id  BIGINT UNSIGNED NOT NULL,
            agent       VARCHAR(50)     NOT NULL,
            level       VARCHAR(10)     NOT NULL DEFAULT 'info',
            message     TEXT            NOT NULL,
            data        LONGTEXT        DEFAULT NULL,
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_content_id (content_id),
            KEY idx_agent (agent)
        ) {$charset_collate};" );

        // Tabelle: Website-Gedächtnis (Memory)
        $table_memory = $wpdb->prefix . self::TABLE_MEMORY;
        dbDelta( "CREATE TABLE {$table_memory} (
            id                BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
            source_type       VARCHAR(20)      NOT NULL DEFAULT 'post',
            source_id         BIGINT UNSIGNED  DEFAULT NULL,
            source_url        VARCHAR(2048)    DEFAULT NULL,
            source_title      VARCHAR(500)     DEFAULT NULL,
            source_date       DATETIME         DEFAULT NULL,
            summary           TEXT             DEFAULT NULL,
            keywords          LONGTEXT         DEFAULT NULL,
            writing_style     TEXT             DEFAULT NULL,
            tone              VARCHAR(255)     DEFAULT NULL,
            target_audience   TEXT             DEFAULT NULL,
            content_type      VARCHAR(100)     DEFAULT NULL,
            topics            LONGTEXT         DEFAULT NULL,
            unique_features   TEXT             DEFAULT NULL,
            language          VARCHAR(10)      DEFAULT NULL,
            sentiment         VARCHAR(20)      DEFAULT NULL,
            reading_level     VARCHAR(20)      DEFAULT NULL,
            recurring_phrases LONGTEXT         DEFAULT NULL,
            cta_style         VARCHAR(50)      DEFAULT NULL,
            structure         VARCHAR(500)     DEFAULT NULL,
            word_count        INT UNSIGNED     DEFAULT NULL,
            tokens_used       INT UNSIGNED     DEFAULT NULL,
            error_message     TEXT             DEFAULT NULL,
            analyzed_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source_type (source_type),
            KEY idx_source_id   (source_id)
        ) {$charset_collate};" );

        update_option( 'aica_db_version', self::DB_VERSION );
        update_option( 'aica_plugin_version', AICA_VERSION );

        // Standardoptionen setzen (nur wenn noch nicht vorhanden)
        self::set_default_options();

        // Standard-Agenten einfügen (nur wenn noch nicht vorhanden)
        self::insert_default_agents();
    }

    public static function deactivate(): void {
        // Alle AICA-Cronjobs entfernen
        $jobs = self::get_active_cron_hooks();
        foreach ( $jobs as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }

    private static function get_active_cron_hooks(): array {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_JOBS;

        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return [];
        }

        $hooks = $wpdb->get_col( "SELECT cron_hook FROM {$table} WHERE cron_hook IS NOT NULL AND active = 1" );
        return $hooks ?: [];
    }

    private static function set_default_options(): void {
        $defaults = [
            'aica_api_key'         => '',
            'aica_model'           => 'claude-opus-4-6',
            'aica_max_tokens'      => 8000,
            'aica_temperature'     => 0.7,
            'aica_default_lang'    => 'de',
            'aica_auto_publish'    => 0,
            'aica_default_status'  => 'draft',
            'aica_voice_profiles'  => self::get_default_voice_profiles(),
            'aica_agent_settings'  => self::get_default_agent_settings(),
            'aica_memory_enabled'  => 1,
            'aica_memory_model'    => 'claude-sonnet-4-6',
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }
    }

    public static function get_default_voice_profiles(): array {
        return [
            [
                'id'          => 1,
                'name'        => 'Expertenautorität',
                'description' => 'Fachlich fundiert, präzise, vertrauenswürdig. Ideal für B2B und Fachthemen.',
                'tone'        => 'professionell, sachlich, kompetent',
                'style'       => 'klar strukturiert, Fakten-orientiert, keine Umgangssprache',
                'example'     => 'Die digitale Transformation erfordert nicht nur technologischen Wandel, sondern einen grundlegenden Paradigmenwechsel in der Unternehmenskultur. Unternehmen, die nachhaltig erfolgreich sein wollen, müssen Agilität als strategischen Kernwert verankern.',
                'avoid'       => 'Slang, übertriebene Adjektive, leere Versprechen',
                'evaluations' => [],
            ],
            [
                'id'          => 2,
                'name'        => 'Freundlicher Ratgeber',
                'description' => 'Nahbar, motivierend, lösungsorientiert. Ideal für Lifestyle, Ratgeber, B2C.',
                'tone'        => 'warm, verständnisvoll, ermutigend',
                'style'       => 'direkte Ansprache (Du), kurze Sätze, praktische Tipps',
                'example'     => 'Du möchtest endlich produktiver werden, aber weißt nicht wo anfangen? Das kenne ich! In diesem Artikel zeige ich dir 5 Methoden, die ich selbst täglich nutze – und die wirklich funktionieren.',
                'avoid'       => 'Fachbegriffe ohne Erklärung, passive Formulierungen, zu lange Sätze',
                'evaluations' => [],
            ],
            [
                'id'          => 3,
                'name'        => 'Journalistisch neutral',
                'description' => 'Objektiv, informativ, ausgewogen. Ideal für News, Reportagen, Analysen.',
                'tone'        => 'neutral, informativ, ausgewogen',
                'style'       => 'journalistischer Stil, Inverted Pyramid, Quellenangaben',
                'example'     => 'Laut einer aktuellen Studie des Instituts für Wirtschaftsforschung sank die Arbeitslosenquote im dritten Quartal auf 4,2 Prozent – den niedrigsten Stand seit 15 Jahren. Experten warnen jedoch vor voreiligen Schlüssen.',
                'avoid'       => 'Wertungen, Superlative, Buzzwords',
                'evaluations' => [],
            ],
        ];
    }

    private static function insert_default_agents(): void {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_AGENTS;

        // Prüfen ob die Tabelle überhaupt existiert
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return;
        }

        $defaults = [
            [
                'agent_key'     => 'code_doku_writer',
                'name'          => 'Code Doku Writer',
                'icon'          => '📝',
                'description'   => 'Erstellt automatisch technische Dokumentation für Code-Repositories. Analysiert Quellcode, Klassen und Funktionen und schreibt klare, entwicklerfreundliche Dokumentation.',
                'system_prompt' => 'Du bist ein erfahrener technischer Redakteur, spezialisiert auf Code-Dokumentation. Deine Aufgabe ist es, aus vorhandenem Quellcode verständliche, vollständige und gut strukturierte technische Dokumentation zu erstellen.

Deine Dokumentation umfasst:
- Eine Übersicht der Architektur und des Gesamtzwecks des Projekts
- Beschreibung aller wichtigen Klassen, Module und deren Verantwortlichkeiten
- Dokumentation wichtiger Funktionen/Methoden mit Parametern, Rückgabewerten und Verwendungsbeispielen
- Abhängigkeiten und Zusammenspiel der Komponenten
- Einrichtungs- und Installationshinweise (wenn erkennbar)
- Codebeispiele für die häufigsten Anwendungsfälle

Halte dich an folgende Prinzipien:
- Schreibe für Entwickler: präzise, technisch korrekt, ohne unnötigen Fülltext
- Verwende Markdown-Formatierung: Überschriften (##, ###), Code-Blöcke (```), Listen
- Erkläre das "Warum" hinter Designentscheidungen, nicht nur das "Was"
- Weise auf potenzielle Fallstricke oder wichtige Hinweise hin
- Antworte auf Deutsch, Code-Snippets bleiben in der Originalsprache',
                'model'         => 'claude-opus-4-6',
                'max_tokens'    => 8000,
                'temperature'   => 0.30,
                'result_key'    => 'documentation_result',
                'capabilities'  => wp_json_encode( [
                    'coding'      => true,
                    'claude_code' => true,
                ] ),
            ],
        ];

        foreach ( $defaults as $agent ) {
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE agent_key = %s",
                $agent['agent_key']
            ) );

            if ( ! $exists ) {
                $wpdb->insert( $table, $agent );
            }
        }
    }

    public static function get_default_agent_settings(): array {
        return [
            'content_analyzer' => [
                'enabled'      => true,
                'model'        => 'claude-opus-4-6',
                'max_tokens'   => 2000,
                'temperature'  => 0.3,
                'system_prompt' => 'Du bist ein erfahrener Content-Analyst mit Expertise in SEO und Content-Marketing. Analysiere das gegebene Thema hinsichtlich: bestehender Content-Arten, Content-Lücken, Potenzial für Unique Angles, Suchintention der Nutzer. Antworte immer auf Deutsch.',
            ],
            'audience_analyzer' => [
                'enabled'      => true,
                'model'        => 'claude-opus-4-6',
                'max_tokens'   => 1500,
                'temperature'  => 0.4,
                'system_prompt' => 'Du bist ein Zielgruppenanalyse-Experte. Identifiziere basierend auf dem Thema: primäre und sekundäre Zielgruppen, deren Bedürfnisse, Schmerzpunkte, Fragen und Informationsbedarf. Antworte immer auf Deutsch.',
            ],
            'keyword_researcher' => [
                'enabled'      => true,
                'model'        => 'claude-opus-4-6',
                'max_tokens'   => 2000,
                'temperature'  => 0.2,
                'system_prompt' => 'Du bist ein SEO-Experte für Keyword-Recherche. Erstelle basierend auf dem Thema eine umfassende Keyword-Liste mit: Hauptkeyword, Longtail-Keywords, LSI-Keywords, Fragewörtern, Suchintentionen (informational/transactional/navigational). Antworte immer auf Deutsch.',
            ],
            'researcher' => [
                'enabled'      => true,
                'model'        => 'claude-opus-4-6',
                'max_tokens'   => 3000,
                'temperature'  => 0.5,
                'system_prompt' => 'Du bist ein tiefgründiger Rechercheur. Erstelle basierend auf dem Thema einen umfassenden Recherche-Report mit: wichtigen Fakten, Statistiken, Expertenmeinungen, aktuellen Trends, häufigen Missverständnissen, einzigartigen Perspektiven. Antworte immer auf Deutsch.',
            ],
            'content_writer' => [
                'enabled'      => true,
                'model'        => 'claude-opus-4-6',
                'max_tokens'   => 8000,
                'temperature'  => 0.8,
                'system_prompt' => 'Du bist ein hochqualifizierter Content-Autor, spezialisiert auf SEO-optimierte, hochwertige Artikel. Verfasse ausschließlich auf Deutsch. Nutze die bereitgestellten Analysen und Recherchen, um einen herausragenden Artikel zu schreiben.',
            ],
        ];
    }
}
