<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$categories     = get_categories( [ 'hide_empty' => false ] );
$voice_profiles = \AICA\Settings::get_voice_profiles();
$api_configured = ! empty( \AICA\Settings::get_api_key() );
$pipelines      = $wpdb->get_results(
    "SELECT id, name, description FROM {$wpdb->prefix}aica_pipelines ORDER BY name ASC"
) ?: [];
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>Artikel generieren</h1>
        <p class="aica-header-sub">Lasse einen hochwertigen SEO-Artikel von der KI-Agenten-Pipeline erstellen.</p>
    </div>

    <?php if ( ! $api_configured ) : ?>
    <div class="notice notice-error">
        <p>Kein API-Schlüssel konfiguriert. Bitte zuerst
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-settings' ) ); ?>">Einstellungen</a>
        aufrufen.</p>
    </div>
    <?php return; endif; ?>

    <div class="aica-generate-grid">
        <!-- Formular -->
        <div class="aica-card">
            <h2 class="aica-card-title">Artikel-Konfiguration</h2>
            <form id="aica-generate-form">
                <div class="aica-form-group">
                    <label for="aica-topic" class="aica-label">
                        Thema / Titel *
                        <span class="aica-hint">Beschreibe das Thema möglichst präzise</span>
                    </label>
                    <textarea id="aica-topic" name="topic" rows="3" required
                        placeholder="z.B. 'Die 10 besten Methoden zur Steigerung der Arbeitsproduktivität im Homeoffice 2024'"
                        class="large-text aica-input"></textarea>
                </div>

                <div class="aica-form-group">
                    <label for="aica-keywords" class="aica-label">
                        Ziel-Keywords
                        <span class="aica-hint">Kommagetrennt – optional</span>
                    </label>
                    <input type="text" id="aica-keywords" name="keywords"
                        placeholder="z.B. Produktivität Homeoffice, Remote Work Tipps, Konzentration steigern"
                        class="large-text aica-input">
                </div>

                <div class="aica-form-group">
                    <label for="aica-pipeline" class="aica-label">
                        Pipeline
                        <span class="aica-hint">Wähle die Agenten-Pipeline für die Generierung</span>
                    </label>
                    <select id="aica-pipeline" name="pipeline_id" class="aica-select">
                        <option value="0">— Standard-Pipeline (alle Agenten) —</option>
                        <?php foreach ( $pipelines as $pipeline ) : ?>
                        <option value="<?php echo esc_attr( $pipeline->id ); ?>">
                            <?php echo esc_html( $pipeline->name ); ?>
                            <?php if ( $pipeline->description ) : ?>
                                (<?php echo esc_html( $pipeline->description ); ?>)
                            <?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aica-form-row">
                    <div class="aica-form-group aica-form-half">
                        <label for="aica-voice" class="aica-label">Voice Profil</label>
                        <select id="aica-voice" name="voice_id" class="aica-select">
                            <option value="0">— Standard (kein Profil) —</option>
                            <?php foreach ( $voice_profiles as $profile ) : ?>
                            <option value="<?php echo esc_attr( $profile['id'] ); ?>">
                                <?php echo esc_html( $profile['name'] ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="aica-voice-preview" class="aica-voice-preview" style="display:none;"></div>
                    </div>

                    <div class="aica-form-group aica-form-half">
                        <label for="aica-post-status" class="aica-label">Post-Status nach Generierung</label>
                        <select id="aica-post-status" name="post_status" class="aica-select">
                            <option value="draft">Entwurf</option>
                            <option value="publish">Veröffentlicht</option>
                            <option value="pending">Ausstehend</option>
                            <option value="private">Privat</option>
                        </select>
                    </div>
                </div>

                <div class="aica-form-group">
                    <label for="aica-category" class="aica-label">Kategorie</label>
                    <select id="aica-category" name="category_id" class="aica-select">
                        <option value="0">— Keine Kategorie —</option>
                        <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                            <?php echo esc_html( $cat->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="aica-form-actions">
                    <button type="submit" id="aica-submit-btn" class="aica-btn aica-btn-primary aica-btn-large"
                        <?php disabled( ! $api_configured ); ?>>
                        Artikel jetzt generieren
                    </button>
                    <span class="aica-loading" id="aica-loading" style="display:none;">
                        <span class="aica-spinner"></span> Agenten arbeiten…
                    </span>
                </div>
            </form>
        </div>

        <!-- Status-Panel -->
        <div class="aica-card" id="aica-status-panel" style="display:none;">
            <h2 class="aica-card-title">Generierungs-Status</h2>
            <div id="aica-pipeline-status"></div>

            <div id="aica-logs" class="aica-logs">
                <h3>Live-Monitoring</h3>
                <div id="aica-log-entries" class="aica-log-entries-container"></div>
            </div>

            <div id="aica-result" class="aica-result" style="display:none;">
                <h3>Generierung abgeschlossen</h3>
                <div id="aica-result-stats"></div>
                <div class="aica-result-actions">
                    <a id="aica-edit-post" href="#" class="aica-btn aica-btn-primary" target="_blank">
                        Post bearbeiten
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-content' ) ); ?>"
                       class="aica-btn aica-btn-secondary">
                        Alle Artikel
                    </a>
                    <button id="aica-generate-another" class="aica-btn aica-btn-secondary">
                        Weiteren Artikel
                    </button>
                </div>
            </div>

            <div id="aica-error" class="aica-error" style="display:none;">
                <h3>Fehler bei der Generierung</h3>
                <p id="aica-error-message"></p>
            </div>
        </div>
    </div>

    <!-- Voice-Profile Vorschau-Daten (JS) -->
    <script id="aica-voice-data" type="application/json">
    <?php
    $voice_json = [];
    foreach ( $voice_profiles as $p ) {
        $voice_json[ $p['id'] ] = [
            'name'        => $p['name'],
            'description' => $p['description'],
            'tone'        => $p['tone'],
            'example'     => $p['example'],
        ];
    }
    echo wp_json_encode( $voice_json );
    ?>
    </script>

    <!-- Pipelines-Daten (JS) -->
    <script id="aica-pipelines-generate-data" type="application/json">
    <?php
    $pipelines_json = [];
    // Steps als Objekte {id, agent, result_key} statt als reine Strings,
    // damit der Generator doppelte Agenten anhand der Step-ID unterscheiden kann.
    $pipelines_json[0] = [
        'id' => 0,
        'name' => 'Standard-Pipeline',
        'steps' => [
            [ 'id' => 'std_0', 'agent' => 'content_analyzer',   'result_key' => null ],
            [ 'id' => 'std_1', 'agent' => 'audience_analyzer',  'result_key' => null ],
            [ 'id' => 'std_2', 'agent' => 'keyword_researcher', 'result_key' => null ],
            [ 'id' => 'std_3', 'agent' => 'researcher',         'result_key' => null ],
            [ 'id' => 'std_4', 'agent' => 'content_writer',     'result_key' => null ],
        ],
    ];
    foreach ( $pipelines as $p ) {
        $decoded_steps = [];
        $wpdb_steps = $wpdb->get_var( $wpdb->prepare(
            "SELECT steps FROM {$wpdb->prefix}aica_pipelines WHERE id = %d",
            $p->id
        ));
        if ( $wpdb_steps ) {
            $steps_data = json_decode( $wpdb_steps, true ) ?: [];
            foreach ( $steps_data as $i => $step ) {
                if ( isset( $step['agent'] ) ) {
                    $decoded_steps[] = [
                        'id'         => $step['id'] ?? ( 'step_' . $i ),
                        'agent'      => $step['agent'],
                        'result_key' => $step['result_key'] ?? null,
                    ];
                }
            }
        }
        $pipelines_json[ $p->id ] = [
            'id'    => (int) $p->id,
            'name'  => $p->name,
            'steps' => $decoded_steps ?: [ [ 'id' => 'step_0', 'agent' => 'content_writer', 'result_key' => null ] ],
        ];
    }
    echo wp_json_encode( $pipelines_json );
    ?>
    </script>

    <!-- Agent-Info (letter abbreviations, no emoji) -->
    <script id="aica-agent-info-generate-data" type="application/json">
    <?php
    $agent_abbr = [
        'content_analyzer'   => 'CA',
        'audience_analyzer'  => 'ZA',
        'keyword_researcher' => 'KW',
        'researcher'         => 'TR',
        'content_writer'     => 'CW',
    ];
    $agent_names = [
        'content_analyzer'   => 'Content-Analyse',
        'audience_analyzer'  => 'Zielgruppenanalyse',
        'keyword_researcher' => 'Keyword-Recherche',
        'researcher'         => 'Tiefenrecherche',
        'content_writer'     => 'Artikel schreiben',
    ];

    $agent_info = [];
    foreach ( $agent_abbr as $key => $abbr ) {
        $agent_info[ $key ] = [
            'icon' => $abbr,
            'name' => $agent_names[ $key ] ?? $key,
        ];
    }

    $custom = $wpdb->get_results(
        "SELECT agent_key AS `key`, name FROM {$wpdb->prefix}aica_agents"
    ) ?: [];
    foreach ( $custom as $a ) {
        $words = explode( ' ', $a->name );
        $abbr  = strtoupper( mb_substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? mb_substr( $words[1], 0, 1 ) : mb_substr( $words[0], 1, 1 ) ) );
        $agent_info[ $a->key ] = [
            'icon' => $abbr,
            'name' => $a->name,
        ];
    }

    echo wp_json_encode( $agent_info );
    ?>
    </script>
</div>
