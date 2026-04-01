<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$pipelines = $wpdb->get_results(
    "SELECT id, name, description, steps, created_at FROM {$wpdb->prefix}aica_pipelines ORDER BY name ASC"
) ?: [];

// Alle verfügbaren Agenten (Built-in + Custom aus DB)
$builtin_agents = [
    [ 'key' => 'content_analyzer',   'name' => 'Content-Analyst',    'icon' => '🔍', 'result_key' => 'analysis_result' ],
    [ 'key' => 'audience_analyzer',  'name' => 'Zielgruppenanalyst', 'icon' => '👥', 'result_key' => 'audience_result' ],
    [ 'key' => 'keyword_researcher', 'name' => 'Keyword-Rechercheur','icon' => '🔑', 'result_key' => 'keyword_result' ],
    [ 'key' => 'researcher',         'name' => 'Tiefenrechercheur',  'icon' => '📚', 'result_key' => 'research_result' ],
    [ 'key' => 'content_writer',     'name' => 'Content-Autor',      'icon' => '✍️', 'result_key' => 'final_content' ],
];
$custom_db_agents = $wpdb->get_results(
    "SELECT agent_key AS `key`, name, icon, result_key FROM {$wpdb->prefix}aica_agents ORDER BY name ASC"
) ?: [];
$all_agents = array_merge( $builtin_agents, array_map( 'get_object_vars', $custom_db_agents ) );

// Agent-Info-Map für die Schritt-Vorschau (key → info)
$agent_info = [];
foreach ( $all_agents as $a ) {
    $agent_info[ $a['key'] ] = $a;
}

// Source-Options für Bedingungen (aus allen Agenten dynamisch)
$source_options = [ '' => '— Quelle wählen —' ];
foreach ( $all_agents as $a ) {
    if ( ! empty( $a['result_key'] ) && $a['result_key'] !== 'final_content' ) {
        $source_options[ $a['result_key'] ] = $a['name'] . ' (' . $a['result_key'] . ')';
    }
}

// Beispiel-Pipeline-Templates
$pipeline_templates = [
    [
        'id'          => 'tpl_full',
        'name'        => 'Standard SEO-Pipeline',
        'description' => 'Vollständige Analyse + Keyword-Recherche + Schreiben',
        'steps'       => [
            [ 'agent' => 'content_analyzer',   'enabled' => true ],
            [ 'agent' => 'audience_analyzer',  'enabled' => true ],
            [ 'agent' => 'keyword_researcher', 'enabled' => true ],
            [ 'agent' => 'researcher',         'enabled' => true ],
            [ 'agent' => 'content_writer',     'enabled' => true ],
        ],
    ],
    [
        'id'          => 'tpl_express',
        'name'        => 'Express-Pipeline',
        'description' => 'Schnelle Inhaltserstellung ohne tiefe Analyse',
        'steps'       => [
            [ 'agent' => 'content_analyzer', 'enabled' => true ],
            [ 'agent' => 'content_writer',   'enabled' => true ],
        ],
    ],
    [
        'id'          => 'tpl_research',
        'name'        => 'Research & Write',
        'description' => 'Tiefe Recherche mit Zielgruppenanalyse',
        'steps'       => [
            [ 'agent' => 'audience_analyzer', 'enabled' => true ],
            [ 'agent' => 'researcher',        'enabled' => true ],
            [ 'agent' => 'content_writer',    'enabled' => true ],
        ],
    ],
    [
        'id'          => 'tpl_seo',
        'name'        => 'SEO-fokussiert',
        'description' => 'Keyword-Strategie im Mittelpunkt',
        'steps'       => [
            [ 'agent' => 'content_analyzer',   'enabled' => true ],
            [ 'agent' => 'keyword_researcher', 'enabled' => true ],
            [ 'agent' => 'content_writer',     'enabled' => true ],
        ],
    ],
];
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>Pipelines</h1>
        <p class="aica-header-sub">Baue eigene Agenten-Pipelines per Drag &amp; Drop und verwende sie in Automatisierungs-Jobs.</p>
    </div>

    <!-- ====================================================
         Beispiel-Pipelines
    ==================================================== -->
    <div class="aica-card" id="aica-pipeline-templates-card">
        <h2 class="aica-card-title" style="margin-bottom:4px;">Schnellstart: Beispiel-Pipelines</h2>
        <p style="margin:0 0 16px;font-size:13px;color:var(--aica-text-muted);">
            Klicke auf eine Vorlage um sie im Builder zu öffnen und anzupassen.
        </p>
        <div class="aica-pipeline-templates-grid">
            <?php foreach ( $pipeline_templates as $tpl ) : ?>
            <button type="button"
                    class="aica-pipeline-template"
                    data-name="<?php echo esc_attr( $tpl['name'] ); ?>"
                    data-description="<?php echo esc_attr( $tpl['description'] ); ?>"
                    data-steps="<?php echo esc_attr( wp_json_encode( $tpl['steps'] ) ); ?>">
                <div class="aica-pipeline-template-name"><?php echo esc_html( $tpl['name'] ); ?></div>
                <div class="aica-pipeline-template-desc"><?php echo esc_html( $tpl['description'] ); ?></div>
                <div class="aica-pipeline-template-steps">
                    <?php
                    $step_icons = array_column( $tpl['steps'], 'agent' );
                    foreach ( $step_icons as $i => $akey ) :
                        $info = $agent_info[ $akey ] ?? null;
                        if ( ! $info ) continue;
                    ?>
                    <?php if ( $i > 0 ) : ?><span class="aica-pipeline-template-arrow">→</span><?php endif; ?>
                    <span class="aica-pipeline-template-step" title="<?php echo esc_attr( $info['name'] ); ?>">
                        <?php echo esc_html( $info['icon'] ); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ====================================================
         Gespeicherte Pipelines
    ==================================================== -->
    <div class="aica-card" id="aica-pipeline-list-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h2 class="aica-card-title" style="margin-bottom:0;">Meine Pipelines</h2>
            <button type="button" id="aica-new-pipeline-btn" class="aica-btn aica-btn-primary"> Neue Pipeline
            </button>
        </div>

        <?php if ( empty( $pipelines ) ) : ?>
        <div class="aica-empty-state" style="text-align:center;padding:32px 0;color:var(--aica-text-muted);">
            <p style="font-size:32px;margin-bottom:8px;">🔀</p>
            <p>Noch keine eigenen Pipelines. Wähle eine Vorlage oben oder klicke auf „Neue Pipeline".</p>
        </div>
        <?php else : ?>
        <table class="aica-table aica-table-full">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Beschreibung</th>
                    <th>Schritte</th>
                    <th>Erstellt</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="aica-pipeline-table-body">
                <?php foreach ( $pipelines as $pipeline ) :
                    $steps = json_decode( $pipeline->steps, true ) ?: [];
                ?>
                <tr id="aica-pipeline-row-<?php echo esc_attr( $pipeline->id ); ?>">
                    <td><strong><?php echo esc_html( $pipeline->name ); ?></strong></td>
                    <td><?php echo esc_html( $pipeline->description ?: '—' ); ?></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
                            <?php foreach ( $steps as $i => $step ) :
                                $info    = $agent_info[ $step['agent'] ] ?? null;
                                $enabled = $step['enabled'] ?? true;
                                if ( ! $info ) continue;
                            ?>
                            <?php if ( $i > 0 ) : ?><span style="color:var(--aica-text-muted);font-size:11px;">→</span><?php endif; ?>
                            <span title="<?php echo esc_attr( $info['name'] ); ?>"
                                  style="opacity:<?php echo $enabled ? '1' : '.4'; ?>;font-size:16px;">
                                <?php echo esc_html( $info['icon'] ); ?>
                            </span>
                            <?php endforeach; ?>
                            <span style="color:var(--aica-text-muted);font-size:11px;">(<?php echo count( $steps ); ?>)</span>
                        </div>
                    </td>
                    <td><?php echo esc_html( wp_date( 'd.m.Y', strtotime( $pipeline->created_at ) ) ); ?></td>
                    <td class="aica-table-actions">
                        <button type="button"
                                class="aica-btn aica-btn-small aica-btn-secondary aica-edit-pipeline"
                                data-id="<?php echo esc_attr( $pipeline->id ); ?>"> Bearbeiten
                        </button>
                        <button type="button"
                                class="aica-btn aica-btn-small aica-btn-danger aica-delete-pipeline"
                                data-id="<?php echo esc_attr( $pipeline->id ); ?>">
                            
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ====================================================
         Pipeline Builder (initial versteckt)
    ==================================================== -->
    <div id="aica-pipeline-builder" class="aica-card" style="display:none;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2 class="aica-card-title" style="margin-bottom:0;" id="aica-builder-title">Neue Pipeline</h2>
            <button type="button" id="aica-builder-close" class="aica-btn aica-btn-secondary">✕ Schließen</button>
        </div>

        <input type="hidden" id="aica-pipeline-id" value="0">

        <!-- Name & Beschreibung -->
        <div class="aica-form-row">
            <div class="aica-form-group aica-form-half">
                <label class="aica-label">Pipeline-Name *</label>
                <input type="text" id="aica-pipeline-name" class="aica-input large-text"
                       placeholder="z.B. 'Schnelle SEO-Pipeline'">
            </div>
            <div class="aica-form-group aica-form-half">
                <label class="aica-label">Beschreibung</label>
                <input type="text" id="aica-pipeline-description" class="aica-input large-text"
                       placeholder="Optional: Kurze Erläuterung">
            </div>
        </div>

        <!-- Builder Layout -->
        <div class="aica-pipeline-builder-layout">

            <!-- Linke Palette -->
            <div class="aica-pipeline-palette">
                <h3 style="font-size:13px;font-weight:600;margin-bottom:12px;color:var(--aica-text-muted);">
                    VERFÜGBARE AGENTEN
                </h3>
                <p style="font-size:12px;color:var(--aica-text-muted);margin-bottom:16px;">
                    Klicke auf einen Agenten, um ihn zur Pipeline hinzuzufügen.
                </p>
                <?php foreach ( $all_agents as $agent ) : ?>
                <div class="aica-palette-item" data-agent="<?php echo esc_attr( $agent['key'] ); ?>">
                    <span class="aica-palette-icon"><?php echo esc_html( $agent['icon'] ); ?></span>
                    <span class="aica-palette-name"><?php echo esc_html( $agent['name'] ); ?></span>
                    <span class="aica-palette-add">＋</span>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Rechter Canvas -->
            <div class="aica-pipeline-canvas-wrap">
                <h3 style="font-size:13px;font-weight:600;margin-bottom:12px;color:var(--aica-text-muted);">
                    PIPELINE-SCHRITTE
                    <span style="font-weight:400;margin-left:8px;">(Reihenfolge per Drag &amp; Drop ändern)</span>
                </h3>

                <div id="aica-pipeline-canvas" class="aica-pipeline-canvas">
                    <div class="aica-canvas-placeholder" id="aica-canvas-placeholder">
                        <p>👈 Agenten aus der Palette hinzufügen</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Speichern -->
        <div class="aica-form-actions" style="margin-top:24px;">
            <button type="button" id="aica-save-pipeline" class="aica-btn aica-btn-primary aica-btn-large">
                 Pipeline speichern
            </button>
            <button type="button" id="aica-builder-cancel" class="aica-btn aica-btn-secondary">
                Abbrechen
            </button>
        </div>
    </div>
</div>

<!-- Agent-Info für JavaScript -->
<script id="aica-agent-info-data" type="application/json">
<?php echo wp_json_encode( $agent_info ); ?>
</script>

<!-- Bestehende Pipelines für JavaScript -->
<script id="aica-pipelines-data" type="application/json">
<?php
$pipelines_js = array_map( function( $p ) {
    return [
        'id'          => (int) $p->id,
        'name'        => $p->name,
        'description' => $p->description ?? '',
        'steps'       => json_decode( $p->steps, true ) ?: [],
    ];
}, $pipelines );
echo wp_json_encode( array_values( $pipelines_js ) );
?>
</script>

<!-- Source-Options für Bedingungen -->
<script id="aica-source-options-data" type="application/json">
<?php echo wp_json_encode( $source_options ); ?>
</script>
