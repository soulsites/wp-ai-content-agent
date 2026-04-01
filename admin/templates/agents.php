<?php
defined( 'ABSPATH' ) || exit;

$agent_settings = \AICA\Settings::get_all_agent_settings();
$models         = \AICA\Settings::get_available_models();

// Custom Agents aus DB
global $wpdb;
$custom_agents = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}aica_agents ORDER BY name ASC"
) ?: [];

$builtin_agents = [
    'content_analyzer'   => [ 'icon' => 'CA', 'name' => 'Content-Analyst',    'description' => 'Analysiert das Thema: Suchintention, Content-Lücken, Unique Angles und Struktur-Empfehlungen.' ],
    'audience_analyzer'  => [ 'icon' => 'ZA', 'name' => 'Zielgruppenanalyst', 'description' => 'Identifiziert Zielgruppen, Bedürfnisse, Schmerzpunkte und Nutzerfragen.' ],
    'keyword_researcher' => [ 'icon' => 'KW', 'name' => 'Keyword-Rechercheur', 'description' => 'Erstellt eine vollständige SEO-Keyword-Strategie mit Longtails, LSI-Keywords und Meta-Daten.' ],
    'researcher'         => [ 'icon' => 'TR', 'name' => 'Tiefenrechercheur',   'description' => 'Sammelt Fakten, Statistiken, Expertenmeinungen, Trends und Praxisbeispiele.' ],
    'content_writer'     => [ 'icon' => 'CW', 'name' => 'Content-Autor',       'description' => 'Schreibt den finalen Artikel mit Voice-Profil, SEO-Optimierung und vollständiger Struktur.' ],
];
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>Agenten</h1>
        <p class="aica-header-sub">Verwalte alle Agenten – Standard-Agenten und eigene Agenten. Füge Agenten per Klick zu Pipelines hinzu.</p>
    </div>

    <!-- ====================================================
         Agenten-Grid
    ==================================================== -->
    <div class="aica-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <div>
                <h2 class="aica-card-title" style="margin-bottom:2px;">Alle Agenten</h2>
                <p style="margin:0;font-size:13px;color:var(--aica-text-muted);">
                    Klicke auf Einstellungen zu bearbeiten. Agenten werden in Pipelines eingesetzt.
                </p>
            </div>
            <button type="button" id="aica-add-agent-btn" class="aica-btn aica-btn-primary">
                Neuen Agenten erstellen
            </button>
        </div>

        <div class="aica-agents-grid">

            <!-- Standard-Agenten -->
            <?php foreach ( $builtin_agents as $key => $info ) :
                $s     = $agent_settings[ $key ] ?? [];
                $model = $s['model'] ?? \AICA\Settings::get_model();
                $model_label = $models[ $model ] ?? $model;
            ?>
            <div class="aica-agent-card aica-agent-card-builtin">
                <div class="aica-agent-card-head">
                    <span class="aica-agent-card-icon"><?php echo esc_html( $info['icon'] ); ?></span>
                    <div>
                        <div class="aica-agent-card-title">
                            <?php echo esc_html( $info['name'] ); ?>
                            <span class="aica-agent-card-badge">Built-in</span>
                        </div>
                    </div>
                </div>
                <p class="aica-agent-card-desc"><?php echo esc_html( $info['description'] ); ?></p>
                <div class="aica-agent-card-meta">
                    <span><?php echo esc_html( $model_label ); ?></span>
                </div>
                <div class="aica-agent-card-actions">
                    <button type="button"
                            class="aica-btn aica-btn-small aica-btn-secondary aica-edit-builtin-agent"
                            data-key="<?php echo esc_attr( $key ); ?>"
                            data-name="<?php echo esc_attr( $info['name'] ); ?>">
                        Einstellungen
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Eigene Agenten -->
            <?php foreach ( $custom_agents as $agent ) :
                $caps       = json_decode( $agent->capabilities ?? '{}', true ) ?: [];
                $has_web    = ! empty( $caps['web_search']['enabled'] );
                $has_coding = ! empty( $caps['coding'] );
                $model_label = $models[ $agent->model ] ?? $agent->model;
            ?>
            <div class="aica-agent-card" id="aica-agent-row-<?php echo esc_attr( $agent->id ); ?>">
                <div class="aica-agent-card-head">
                    <span class="aica-agent-card-icon"><?php echo esc_html( $agent->icon ); ?></span>
                    <div>
                        <div class="aica-agent-card-title"><?php echo esc_html( $agent->name ); ?></div>
                        <div style="font-size:11px;color:var(--aica-text-muted);margin-top:2px;">
                            <code><?php echo esc_html( $agent->agent_key ); ?></code>
                        </div>
                    </div>
                </div>
                <?php if ( $agent->description ) : ?>
                <p class="aica-agent-card-desc"><?php echo esc_html( $agent->description ); ?></p>
                <?php endif; ?>
                <div class="aica-agent-card-meta">
                    <span><?php echo esc_html( $model_label ); ?></span>
                    <?php if ( $has_web )    : ?> &nbsp;<span class="aica-cap-badge">🔍 Web</span><?php endif; ?>
                    <?php if ( $has_coding ) : ?> &nbsp;<span class="aica-cap-badge">💻 Coding</span><?php endif; ?>
                </div>
                <div class="aica-agent-card-actions">
                    <button type="button"
                            class="aica-btn aica-btn-small aica-btn-secondary aica-edit-agent"
                            data-id="<?php echo esc_attr( $agent->id ); ?>">
                        Bearbeiten
                    </button>
                    <button type="button"
                            class="aica-btn aica-btn-small aica-btn-danger aica-delete-agent"
                            data-id="<?php echo esc_attr( $agent->id ); ?>">
                        🗑️
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ( empty( $custom_agents ) ) : ?>
            <!-- Platzhalter-Karte für neuen Agenten -->
            <div class="aica-agent-card aica-agent-card-empty" id="aica-agent-empty-hint">
                <div style="text-align:center;padding:20px 10px;color:var(--aica-text-muted);">
                    <div style="font-size:32px;margin-bottom:8px;">➕</div>
                    <div style="font-size:13px;font-weight:500;margin-bottom:6px;">Eigenen Agenten erstellen</div>
                    <div style="font-size:12px;">Definiere Prompt, Modell und Fähigkeiten</div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- ====================================================
         Modal: Standard-Agent bearbeiten
    ==================================================== -->
    <div id="aica-builtin-agent-modal" class="aica-modal" style="display:none;">
        <div class="aica-modal-backdrop"></div>
        <div class="aica-modal-content">
            <div class="aica-modal-header">
                <h2 id="aica-builtin-agent-modal-title">Agent-Einstellungen</h2>
                <button type="button" class="aica-modal-close">✕</button>
            </div>
            <div class="aica-modal-body">
                <input type="hidden" id="aica-builtin-agent-key" value="">

                <div class="aica-form-row three-col">
                    <div class="aica-form-group">
                        <label class="aica-label">Modell</label>
                        <select id="aica-builtin-agent-model" class="aica-select" style="width:100%">
                            <?php foreach ( $models as $mk => $ml ) : ?>
                            <option value="<?php echo esc_attr( $mk ); ?>"><?php echo esc_html( $ml ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="aica-form-group">
                        <label class="aica-label">Max. Tokens</label>
                        <input type="number" id="aica-builtin-agent-max-tokens" class="small-text aica-input"
                               value="2000" min="100" max="50000" step="100" style="width:100%">
                    </div>
                    <div class="aica-form-group">
                        <label class="aica-label">Temperature <span class="aica-hint">(0–1)</span></label>
                        <input type="number" id="aica-builtin-agent-temperature" class="small-text aica-input"
                               value="0.5" min="0" max="1" step="0.05" style="width:100%">
                    </div>
                </div>

                <div class="aica-form-group">
                    <label class="aica-label">System-Prompt
                        <span class="aica-hint">Leer lassen = Standard-Prompt</span>
                    </label>
                    <textarea id="aica-builtin-agent-system-prompt" rows="7"
                              class="large-text aica-input aica-textarea-mono"
                              placeholder="Leer lassen um den eingebauten Standard-Prompt zu verwenden."></textarea>
                </div>
            </div>
            <div class="aica-modal-footer">
                <button type="button" class="aica-btn aica-btn-secondary aica-modal-close">Abbrechen</button>
                <button type="button" id="aica-save-builtin-agent" class="aica-btn aica-btn-primary">💾 Speichern</button>
            </div>
        </div>
    </div>

    <!-- ====================================================
         Modal: Eigenen Agenten erstellen/bearbeiten
    ==================================================== -->
    <div id="aica-agent-modal" class="aica-modal" style="display:none;">
        <div class="aica-modal-backdrop"></div>
        <div class="aica-modal-content aica-modal-large">
            <div class="aica-modal-header">
                <h2 id="aica-agent-modal-title">Neuen Agenten erstellen</h2>
                <button type="button" class="aica-modal-close">✕</button>
            </div>
            <div class="aica-modal-body">
                <form id="aica-agent-form">
                    <input type="hidden" id="aica-agent-id" value="0">

                    <div class="aica-form-row">
                        <div class="aica-form-group" style="flex:0 0 56px;">
                            <label class="aica-label">Icon</label>
                            <input type="text" id="aica-agent-icon" class="aica-input"
                                   value="🤖" style="font-size:22px;width:56px;text-align:center;">
                        </div>
                        <div class="aica-form-group" style="flex:1;">
                            <label class="aica-label">Name *</label>
                            <input type="text" id="aica-agent-name" class="large-text aica-input"
                                   placeholder="z.B. 'Trend-Analyst'">
                        </div>
                    </div>

                    <div class="aica-form-row">
                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">
                                Agent-Key *
                                <span class="aica-hint">Eindeutige ID (a-z, 0-9, _), wird automatisch generiert</span>
                            </label>
                            <input type="text" id="aica-agent-key" class="aica-input large-text"
                                   placeholder="trend_analyst">
                        </div>
                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">
                                Result-Key
                                <span class="aica-hint">Context-Key für die Ausgabe dieses Agenten</span>
                            </label>
                            <input type="text" id="aica-agent-result-key" class="aica-input large-text"
                                   placeholder="trend_result">
                        </div>
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">Beschreibung</label>
                        <input type="text" id="aica-agent-description" class="large-text aica-input"
                               placeholder="Kurze Erläuterung was dieser Agent macht">
                    </div>

                    <div class="aica-form-row three-col">
                        <div class="aica-form-group">
                            <label class="aica-label">Modell</label>
                            <select id="aica-agent-model" class="aica-select" style="width:100%">
                                <?php foreach ( $models as $mk => $ml ) : ?>
                                <option value="<?php echo esc_attr( $mk ); ?>"><?php echo esc_html( $ml ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aica-form-group">
                            <label class="aica-label">Max. Tokens</label>
                            <input type="number" id="aica-agent-max-tokens" class="small-text aica-input"
                                   value="2000" min="100" max="50000" step="100" style="width:100%">
                        </div>
                        <div class="aica-form-group">
                            <label class="aica-label">Temperature <span class="aica-hint">(0–1)</span></label>
                            <input type="number" id="aica-agent-temperature" class="small-text aica-input"
                                   value="0.5" min="0" max="1" step="0.05" style="width:100%">
                        </div>
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">
                            System-Prompt
                            <span class="aica-hint">Persönlichkeit und Aufgabe des Agenten</span>
                        </label>
                        <textarea id="aica-agent-system-prompt" rows="7"
                                  class="large-text aica-input aica-textarea-mono"
                                  placeholder="Du bist ein spezialisierter Analyst für..."></textarea>
                    </div>

                    <!-- Fähigkeiten -->
                    <div class="aica-capabilities-section">
                        <h3 class="aica-cap-section-title">⚡ Fähigkeiten</h3>

                        <div class="aica-cap-cards">
                        <!-- Web-Suche -->
                        <div class="aica-cap-card" id="aica-cap-websearch-card">
                            <div class="aica-cap-header">
                                <label class="aica-toggle">
                                    <input type="checkbox" id="aica-cap-web-search">
                                    <span class="aica-toggle-slider"></span>
                                </label>
                                <div class="aica-cap-info">
                                    <strong>🔍 Websuche / URL-Abruf</strong>
                                    <span class="aica-cap-desc">Ruft Inhalte von angegebenen URLs ab und stellt sie dem Agenten als Kontext bereit.</span>
                                </div>
                            </div>
                            <div class="aica-cap-body" id="aica-cap-web-body" style="display:none;">
                                <label class="aica-label">
                                    URLs <span class="aica-hint">(eine URL pro Zeile)</span>
                                </label>
                                <textarea id="aica-cap-web-urls" rows="4"
                                          class="large-text aica-input aica-textarea-mono"
                                          placeholder="https://example.com/seite1&#10;https://example.com/seite2"></textarea>
                            </div>
                        </div>

                        <!-- Coding -->
                        <div class="aica-cap-card">
                            <div class="aica-cap-header">
                                <label class="aica-toggle">
                                    <input type="checkbox" id="aica-cap-coding">
                                    <span class="aica-toggle-slider"></span>
                                </label>
                                <div class="aica-cap-info">
                                    <strong>💻 Programmier-Modus</strong>
                                    <span class="aica-cap-desc">Aktiviert Code-Beispiele und technische Implementierungen in der Ausgabe (Markdown Code-Blöcke).</span>
                                </div>
                            </div>
                        </div>
                        </div><!-- /.aica-cap-cards -->
                    </div>
                </form>
            </div>
            <div class="aica-modal-footer">
                <button type="button" class="aica-btn aica-btn-secondary aica-modal-close">Abbrechen</button>
                <button type="button" id="aica-save-agent" class="aica-btn aica-btn-primary">💾 Agenten speichern</button>
            </div>
        </div>
    </div>

    <!-- Custom-Agent-Daten für JS -->
    <script id="aica-custom-agents-data" type="application/json">
    <?php
    $agents_js = array_map( function( $a ) {
        $caps = json_decode( $a->capabilities ?? '{}', true ) ?: [];
        return [
            'id'           => (int) $a->id,
            'agent_key'    => $a->agent_key,
            'name'         => $a->name,
            'icon'         => $a->icon,
            'description'  => $a->description ?? '',
            'system_prompt'=> $a->system_prompt ?? '',
            'model'        => $a->model,
            'max_tokens'   => (int) $a->max_tokens,
            'temperature'  => (float) $a->temperature,
            'result_key'   => $a->result_key,
            'capabilities' => $caps,
        ];
    }, $custom_agents );
    echo wp_json_encode( array_values( $agents_js ) );
    ?>
    </script>
</div>
