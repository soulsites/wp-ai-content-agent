<?php
defined( 'ABSPATH' ) || exit;

$saved          = ! empty( $_GET['saved'] );
$agent_settings = \AICA\Settings::get_all_agent_settings();
$models         = \AICA\Settings::get_available_models();

// Custom Agents aus DB
global $wpdb;
$custom_agents = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}aica_agents ORDER BY name ASC"
) ?: [];

$agent_info = [
    'content_analyzer'   => [ 'icon' => '🔍', 'name' => 'Content-Analyst',    'description' => 'Analysiert das Thema: Suchintention, Content-Lücken, Unique Angles und Struktur-Empfehlungen.' ],
    'audience_analyzer'  => [ 'icon' => '👥', 'name' => 'Zielgruppenanalyst', 'description' => 'Identifiziert Zielgruppen, Bedürfnisse, Schmerzpunkte und Nutzerfragen.' ],
    'keyword_researcher' => [ 'icon' => '🔑', 'name' => 'Keyword-Rechercheur','description' => 'Erstellt eine vollständige SEO-Keyword-Strategie mit Longtails, LSI-Keywords und Meta-Daten.' ],
    'researcher'         => [ 'icon' => '📚', 'name' => 'Tiefenrechercheur',  'description' => 'Sammelt Fakten, Statistiken, Expertenmeinungen, Trends und Praxisbeispiele.' ],
    'content_writer'     => [ 'icon' => '✍️', 'name' => 'Content-Autor',      'description' => 'Schreibt den finalen Artikel mit Voice-Profil, SEO-Optimierung und vollständiger Struktur.' ],
];
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>🤖 Agenten</h1>
        <p class="aica-header-sub">Konfiguriere die Standard-Agenten und erstelle eigene Agenten mit individuellen Fähigkeiten.</p>
    </div>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Agenten-Einstellungen gespeichert!</p></div>
    <?php endif; ?>

    <!-- ====================================================
         SECTION 1: Standard-Agenten
    ==================================================== -->
    <div class="aica-card">
        <h2 class="aica-card-title">Standard-Agenten
            <span class="aica-hint" style="font-weight:400;font-size:13px;margin-left:8px;">— vordefiniert, immer verfügbar</span>
        </h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'aica_save_settings' ); ?>
            <input type="hidden" name="action" value="aica_save_settings">

            <div class="aica-tabs">
                <?php $first = true; ?>
                <?php foreach ( $agent_info as $key => $info ) : ?>
                <button type="button" class="aica-tab <?php echo $first ? 'active' : ''; ?>"
                        data-tab="agent-<?php echo esc_attr( $key ); ?>">
                    <?php echo esc_html( $info['icon'] . ' ' . $info['name'] ); ?>
                </button>
                <?php $first = false; endforeach; ?>
            </div>

            <?php $first = true; foreach ( $agent_info as $key => $info ) :
                $settings    = $agent_settings[ $key ] ?? [];
                $enabled     = ! empty( $settings['enabled'] );
                $model       = $settings['model']       ?? \AICA\Settings::get_model();
                $max_tokens  = $settings['max_tokens']  ?? 2000;
                $temperature = $settings['temperature'] ?? 0.5;
                $sys_prompt  = $settings['system_prompt'] ?? '';
            ?>
            <div class="aica-tab-content <?php echo $first ? 'active' : ''; ?>"
                 id="agent-<?php echo esc_attr( $key ); ?>">
                <div class="aica-agent-header">
                    <span class="aica-agent-icon"><?php echo esc_html( $info['icon'] ); ?></span>
                    <div>
                        <h3 class="aica-card-title" style="margin-bottom:2px;"><?php echo esc_html( $info['name'] ); ?></h3>
                        <p class="aica-agent-desc" style="margin:0;"><?php echo esc_html( $info['description'] ); ?></p>
                    </div>
                    <label class="aica-toggle">
                        <input type="checkbox" name="agents[<?php echo esc_attr( $key ); ?>][enabled]"
                               value="1" <?php checked( $enabled ); ?>>
                        <span class="aica-toggle-slider"></span>
                        <span class="aica-toggle-label">Aktiviert</span>
                    </label>
                </div>

                <div class="aica-form-row">
                    <div class="aica-form-group aica-form-third">
                        <label class="aica-label">Modell</label>
                        <select name="agents[<?php echo esc_attr( $key ); ?>][model]" class="aica-select">
                            <?php foreach ( $models as $mk => $ml ) : ?>
                            <option value="<?php echo esc_attr( $mk ); ?>" <?php selected( $model, $mk ); ?>>
                                <?php echo esc_html( $ml ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="aica-form-group aica-form-third">
                        <label class="aica-label">Max. Tokens</label>
                        <input type="number" name="agents[<?php echo esc_attr( $key ); ?>][max_tokens]"
                               value="<?php echo esc_attr( $max_tokens ); ?>"
                               min="100" max="50000" step="100" class="small-text aica-input">
                    </div>
                    <div class="aica-form-group aica-form-third">
                        <label class="aica-label">Temperature <span class="aica-hint">(0–1)</span></label>
                        <input type="number" name="agents[<?php echo esc_attr( $key ); ?>][temperature]"
                               value="<?php echo esc_attr( $temperature ); ?>"
                               min="0" max="1" step="0.05" class="small-text aica-input">
                    </div>
                </div>

                <div class="aica-form-group">
                    <label class="aica-label">System-Prompt
                        <span class="aica-hint">Definiert Persönlichkeit und Aufgabe des Agenten</span>
                    </label>
                    <textarea name="agents[<?php echo esc_attr( $key ); ?>][system_prompt]"
                              rows="6" class="large-text aica-input aica-textarea-mono"
                              ><?php echo esc_textarea( $sys_prompt ); ?></textarea>
                </div>
            </div>
            <?php $first = false; endforeach; ?>

            <div class="aica-form-actions">
                <?php submit_button( '💾 Standard-Agenten speichern', 'primary aica-btn aica-btn-primary', 'submit', false ); ?>
            </div>
        </form>
    </div>

    <!-- ====================================================
         SECTION 2: Eigene Agenten
    ==================================================== -->
    <div class="aica-card" style="margin-top:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <div>
                <h2 class="aica-card-title" style="margin-bottom:2px;">Eigene Agenten</h2>
                <p style="margin:0;font-size:13px;color:var(--aica-text-muted);">
                    Erstelle zusätzliche Agenten mit eigenem Prompt und Fähigkeiten. Sie stehen im Pipeline-Builder zur Verfügung.
                </p>
            </div>
            <button type="button" id="aica-add-agent-btn" class="aica-btn aica-btn-primary">
                ➕ Neuen Agenten erstellen
            </button>
        </div>

        <?php if ( empty( $custom_agents ) ) : ?>
        <div class="aica-empty-state" style="text-align:center;padding:24px 0;color:var(--aica-text-muted);">
            <p>Noch keine eigenen Agenten. Klicke auf „Neuen Agenten erstellen" um loszulegen.</p>
        </div>
        <?php else : ?>
        <table class="aica-table aica-table-full" id="aica-custom-agents-table">
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Result-Key</th>
                    <th>Modell</th>
                    <th>Fähigkeiten</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $custom_agents as $agent ) :
                    $caps = json_decode( $agent->capabilities ?? '{}', true ) ?: [];
                    $has_web    = ! empty( $caps['web_search']['enabled'] );
                    $has_coding = ! empty( $caps['coding'] );
                ?>
                <tr id="aica-agent-row-<?php echo esc_attr( $agent->id ); ?>">
                    <td>
                        <span style="font-size:18px;margin-right:6px;"><?php echo esc_html( $agent->icon ); ?></span>
                        <strong><?php echo esc_html( $agent->name ); ?></strong>
                        <br><small style="color:var(--aica-text-muted);"><?php echo esc_html( $agent->agent_key ); ?></small>
                    </td>
                    <td><code><?php echo esc_html( $agent->result_key ); ?></code></td>
                    <td><?php echo esc_html( \AICA\Settings::get_available_models()[ $agent->model ] ?? $agent->model ); ?></td>
                    <td>
                        <?php if ( $has_web )    : ?><span class="aica-cap-badge">🔍 Web</span><?php endif; ?>
                        <?php if ( $has_coding ) : ?><span class="aica-cap-badge">💻 Coding</span><?php endif; ?>
                        <?php if ( ! $has_web && ! $has_coding ) : ?><span style="color:var(--aica-text-muted);">—</span><?php endif; ?>
                    </td>
                    <td class="aica-table-actions">
                        <button type="button" class="aica-btn aica-btn-small aica-btn-secondary aica-edit-agent"
                                data-id="<?php echo esc_attr( $agent->id ); ?>">✏️</button>
                        <button type="button" class="aica-btn aica-btn-small aica-btn-danger aica-delete-agent"
                                data-id="<?php echo esc_attr( $agent->id ); ?>">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Modal: Agent erstellen/bearbeiten -->
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

                    <div class="aica-form-row">
                        <div class="aica-form-group aica-form-third">
                            <label class="aica-label">Modell</label>
                            <select id="aica-agent-model" class="aica-select">
                                <?php foreach ( $models as $mk => $ml ) : ?>
                                <option value="<?php echo esc_attr( $mk ); ?>"><?php echo esc_html( $ml ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aica-form-group aica-form-third">
                            <label class="aica-label">Max. Tokens</label>
                            <input type="number" id="aica-agent-max-tokens" class="small-text aica-input"
                                   value="2000" min="100" max="50000" step="100">
                        </div>
                        <div class="aica-form-group aica-form-third">
                            <label class="aica-label">Temperature <span class="aica-hint">(0–1)</span></label>
                            <input type="number" id="aica-agent-temperature" class="small-text aica-input"
                                   value="0.5" min="0" max="1" step="0.05">
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
                                <p class="description" style="font-size:12px;margin-top:4px;">
                                    Optional: Leer lassen um nur den Hinweis im Prompt zu setzen. Mit URLs wird der Seiteninhalt abgerufen und als Wissensquelle übergeben.
                                </p>
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
            'id'          => (int) $a->id,
            'agent_key'   => $a->agent_key,
            'name'        => $a->name,
            'icon'        => $a->icon,
            'description' => $a->description ?? '',
            'system_prompt' => $a->system_prompt ?? '',
            'model'       => $a->model,
            'max_tokens'  => (int) $a->max_tokens,
            'temperature' => (float) $a->temperature,
            'result_key'  => $a->result_key,
            'capabilities' => $caps,
        ];
    }, $custom_agents );
    echo wp_json_encode( array_values( $agents_js ) );
    ?>
    </script>
</div>
