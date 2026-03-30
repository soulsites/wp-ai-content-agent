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
        <h1>✍️ Artikel generieren</h1>
        <p class="aica-header-sub">Lasse einen hochwertigen SEO-Artikel von der KI-Agenten-Pipeline erstellen.</p>
    </div>

    <?php if ( ! $api_configured ) : ?>
    <div class="notice notice-error">
        <p>⚠️ Kein API-Schlüssel konfiguriert. Bitte zuerst
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-settings' ) ); ?>">Einstellungen</a>
        aufrufen.</p>
    </div>
    <?php return; endif; ?>

    <div class="aica-generate-grid">
        <!-- Formular -->
        <div class="aica-card">
            <h2 class="aica-card-title">📝 Artikel-Konfiguration</h2>
            <form id="aica-generate-form">
                <div class="aica-form-group">
                    <label for="aica-topic" class="aica-label">
                        Thema / Titel * <span class="aica-hint">Beschreibe das Thema möglichst präzise</span>
                    </label>
                    <textarea id="aica-topic" name="topic" rows="3" required
                        placeholder="z.B. 'Die 10 besten Methoden zur Steigerung der Arbeitsproduktivität im Homeoffice 2024'"
                        class="large-text aica-input"></textarea>
                </div>

                <div class="aica-form-group">
                    <label for="aica-keywords" class="aica-label">
                        Ziel-Keywords <span class="aica-hint">Kommagetrennt – optional</span>
                    </label>
                    <input type="text" id="aica-keywords" name="keywords"
                        placeholder="z.B. Produktivität Homeoffice, Remote Work Tipps, Konzentration steigern"
                        class="large-text aica-input">
                </div>

                <div class="aica-form-group">
                    <label for="aica-pipeline" class="aica-label">
                        Pipeline <span class="aica-hint">Wähle die Agenten-Pipeline für die Generierung</span>
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
                        🚀 Artikel jetzt generieren
                    </button>
                    <span class="aica-loading" id="aica-loading" style="display:none;">
                        <span class="aica-spinner"></span> Agenten arbeiten...
                    </span>
                </div>
            </form>
        </div>

        <!-- Status-Panel -->
        <div class="aica-card" id="aica-status-panel" style="display:none;">
            <h2 class="aica-card-title">🔄 Generierungs-Status</h2>
            <div id="aica-pipeline-status">
                <!-- Wird dynamisch von JavaScript gefüllt basierend auf gewählter Pipeline -->
            </div>

            <div id="aica-logs" class="aica-logs">
                <h3>📊 Live-Monitoring – KI-Gedanken & Agent-Aktivität</h3>
                <div id="aica-log-entries" class="aica-log-entries-container"></div>
            </div>

            <div id="aica-result" class="aica-result" style="display:none;">
                <h3>✅ Generierung abgeschlossen!</h3>
                <div id="aica-result-stats"></div>
                <div class="aica-result-actions">
                    <a id="aica-edit-post" href="#" class="aica-btn aica-btn-primary" target="_blank">
                        ✏️ Post bearbeiten
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-content' ) ); ?>"
                       class="aica-btn aica-btn-secondary">
                        📋 Alle Artikel
                    </a>
                    <button id="aica-generate-another" class="aica-btn aica-btn-secondary">
                        ➕ Weiteren Artikel
                    </button>
                </div>
            </div>

            <div id="aica-error" class="aica-error" style="display:none;">
                <h3>❌ Fehler bei der Generierung</h3>
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
    // Standard-Pipeline
    $pipelines_json[0] = [
        'id' => 0,
        'name' => 'Standard-Pipeline',
        'steps' => [
            'content_analyzer',
            'audience_analyzer',
            'keyword_researcher',
            'researcher',
            'content_writer'
        ]
    ];
    // Custom Pipelines
    foreach ( $pipelines as $p ) {
        $steps = json_decode( $p->description, true ) ?: [];
        $decoded_steps = [];

        // Falls das Feld steps Agenten-Keys enthält, verwenden wir die
        // Sonst müssen wir die Schritte aus der DB laden
        $wpdb_steps = $wpdb->get_var( $wpdb->prepare(
            "SELECT steps FROM {$wpdb->prefix}aica_pipelines WHERE id = %d",
            $p->id
        ));

        if ( $wpdb_steps ) {
            $steps_data = json_decode( $wpdb_steps, true ) ?: [];
            foreach ( $steps_data as $step ) {
                if ( isset( $step['agent'] ) ) {
                    $decoded_steps[] = $step['agent'];
                }
            }
        }

        $pipelines_json[ $p->id ] = [
            'id'    => (int) $p->id,
            'name'  => $p->name,
            'steps' => $decoded_steps ?: [ 'content_writer' ] // Fallback
        ];
    }
    echo wp_json_encode( $pipelines_json );
    ?>
    </script>

    <!-- Agent-Info für Dynamic Status UI -->
    <script id="aica-agent-info-generate-data" type="application/json">
    <?php
    $agent_icons = [
        'content_analyzer'   => '🔍',
        'audience_analyzer'  => '👥',
        'keyword_researcher' => '🔑',
        'researcher'         => '📚',
        'content_writer'     => '✍️',
    ];
    $agent_names = [
        'content_analyzer'   => 'Content-Analyse',
        'audience_analyzer'  => 'Zielgruppenanalyse',
        'keyword_researcher' => 'Keyword-Recherche',
        'researcher'         => 'Tiefenrecherche',
        'content_writer'     => 'Artikel schreiben',
    ];

    $agent_info = [];
    foreach ( $agent_icons as $key => $icon ) {
        $agent_info[ $key ] = [
            'icon'  => $icon,
            'name'  => $agent_names[ $key ] ?? $key,
        ];
    }

    // Custom Agenten auch hinzufügen
    $custom = $wpdb->get_results(
        "SELECT agent_key AS `key`, name, icon FROM {$wpdb->prefix}aica_agents"
    ) ?: [];
    foreach ( $custom as $a ) {
        $agent_info[ $a->key ] = [
            'icon' => $a->icon,
            'name' => $a->name,
        ];
    }

    echo wp_json_encode( $agent_info );
    ?>
    </script>
</div>
