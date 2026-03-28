<?php
defined( 'ABSPATH' ) || exit;

$saved          = ! empty( $_GET['saved'] );
$agent_settings = \AICA\Settings::get_all_agent_settings();
$models         = \AICA\Settings::get_available_models();

$agent_info = [
    'content_analyzer'   => [
        'icon'        => '🔍',
        'name'        => 'Content-Analyst',
        'description' => 'Analysiert das Thema: Suchintention, Content-Lücken, Unique Angles und Struktur-Empfehlungen.',
    ],
    'audience_analyzer'  => [
        'icon'        => '👥',
        'name'        => 'Zielgruppenanalyst',
        'description' => 'Identifiziert Zielgruppen, Bedürfnisse, Schmerzpunkte und Nutzerfragen.',
    ],
    'keyword_researcher' => [
        'icon'        => '🔑',
        'name'        => 'Keyword-Rechercheur',
        'description' => 'Erstellt eine vollständige SEO-Keyword-Strategie mit Longtails, LSI-Keywords und Meta-Daten.',
    ],
    'researcher'         => [
        'icon'        => '📚',
        'name'        => 'Tiefenrechercheur',
        'description' => 'Sammelt Fakten, Statistiken, Expertenmeinungen, Trends und Praxisbeispiele.',
    ],
    'content_writer'     => [
        'icon'        => '✍️',
        'name'        => 'Content-Autor',
        'description' => 'Schreibt den finalen Artikel mit Voice-Profil, SEO-Optimierung und vollständiger Struktur.',
    ],
];
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>🤖 Agenten-Konfiguration</h1>
        <p class="aica-header-sub">Konfiguriere jeden Agenten individuell – Prompts, Modell, Tokens und Kreativität.</p>
    </div>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>✅ Agenten-Einstellungen gespeichert!</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'aica_save_settings' ); ?>
        <input type="hidden" name="action" value="aica_save_settings">

        <!-- Tab Navigation -->
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
            $settings = $agent_settings[ $key ] ?? [];
            $enabled     = ! empty( $settings['enabled'] );
            $model       = $settings['model']       ?? \AICA\Settings::get_model();
            $max_tokens  = $settings['max_tokens']  ?? 2000;
            $temperature = $settings['temperature'] ?? 0.5;
            $sys_prompt  = $settings['system_prompt'] ?? '';
        ?>
        <div class="aica-tab-content <?php echo $first ? 'active' : ''; ?>"
             id="agent-<?php echo esc_attr( $key ); ?>">
            <div class="aica-card">
                <div class="aica-agent-header">
                    <span class="aica-agent-icon"><?php echo esc_html( $info['icon'] ); ?></span>
                    <div>
                        <h2 class="aica-card-title"><?php echo esc_html( $info['name'] ); ?></h2>
                        <p class="aica-agent-desc"><?php echo esc_html( $info['description'] ); ?></p>
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
                        <input type="number"
                               name="agents[<?php echo esc_attr( $key ); ?>][max_tokens]"
                               value="<?php echo esc_attr( $max_tokens ); ?>"
                               min="100" max="50000" step="100"
                               class="small-text aica-input">
                    </div>

                    <div class="aica-form-group aica-form-third">
                        <label class="aica-label">Temperature <span class="aica-hint">(0–1)</span></label>
                        <input type="number"
                               name="agents[<?php echo esc_attr( $key ); ?>][temperature]"
                               value="<?php echo esc_attr( $temperature ); ?>"
                               min="0" max="1" step="0.05"
                               class="small-text aica-input">
                    </div>
                </div>

                <div class="aica-form-group">
                    <label class="aica-label">
                        System-Prompt
                        <span class="aica-hint">Definiert die Persönlichkeit und Aufgabe des Agenten</span>
                    </label>
                    <textarea name="agents[<?php echo esc_attr( $key ); ?>][system_prompt]"
                              rows="6" class="large-text aica-input aica-textarea-mono"
                              ><?php echo esc_textarea( $sys_prompt ); ?></textarea>
                </div>
            </div>
        </div>
        <?php $first = false; endforeach; ?>

        <div class="aica-form-actions">
            <?php submit_button( '💾 Agenten-Einstellungen speichern', 'primary aica-btn aica-btn-primary', 'submit', false ); ?>
        </div>
    </form>
</div>
