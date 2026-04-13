<?php
defined( 'ABSPATH' ) || exit;

$memory_enabled = \AICA\Settings::is_memory_enabled();
$memory_model   = \AICA\Settings::get_memory_model();
$models         = \AICA\Settings::get_available_models();
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div>
                <h1>Website-Gedächtnis</h1>
                <p class="aica-header-sub">
                    Analysiere deine bestehenden Inhalte, damit die KI deinen Schreibstil, deine Themen und deine Zielgruppe kennt und bei neuen Texten berücksichtigt.
                </p>
            </div>
            <div style="margin-left:auto;display:flex;align-items:center;gap:12px;flex-shrink:0;">
                <label class="aica-memory-toggle-label" for="aica-memory-toggle">
                    <span id="aica-memory-status-text" style="font-size:13px;color:var(--aica-text-muted);">
                        <?php echo $memory_enabled ? 'Gedächtnis aktiv' : 'Gedächtnis deaktiviert'; ?>
                    </span>
                    <div class="aica-toggle <?php echo $memory_enabled ? 'aica-toggle-on' : ''; ?>" id="aica-memory-toggle" data-enabled="<?php echo (int) $memory_enabled; ?>">
                        <div class="aica-toggle-thumb"></div>
                    </div>
                </label>
            </div>
        </div>
    </div>

    <!-- Modell-Auswahl -->
    <div class="aica-card">
        <h2 class="aica-card-title">Analyse-Einstellungen</h2>
        <div class="aica-form-row">
            <div class="aica-form-group aica-form-half">
                <label for="aica-memory-model" class="aica-label">
                    Modell für Analyse
                    <span class="aica-hint">Sonnet ist ausreichend und kostengünstig</span>
                </label>
                <select id="aica-memory-model" class="aica-select">
                    <?php foreach ( $models as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $memory_model, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="aica-form-group aica-form-half" style="display:flex;align-items:flex-end;padding-bottom:4px;">
                <button type="button" id="aica-save-memory-model" class="aica-btn aica-btn-secondary">
                    Modell speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Beiträge & Seiten auswählen -->
    <div class="aica-card">
        <h2 class="aica-card-title">Beiträge & Seiten analysieren</h2>
        <p style="color:var(--aica-text-muted);font-size:13px;margin-top:-8px;margin-bottom:16px;">
            Klicke auf einen Eintrag zum Auswählen, nochmal zum Abwählen.
            Bereits analysierte Einträge sind grau und nicht auswählbar.
        </p>

        <div class="aica-memory-list-toolbar">
            <div class="aica-memory-tabs">
                <button type="button" class="aica-memory-tab active" data-tab="posts">Beiträge</button>
                <button type="button" class="aica-memory-tab" data-tab="pages">Seiten</button>
            </div>
            <div class="aica-memory-list-actions">
                <span id="aica-memory-selected-count" style="font-size:13px;color:var(--aica-text-muted);">0 ausgewählt</span>
                <button type="button" id="aica-memory-select-all" class="aica-btn aica-btn-small">Alle auswählen</button>
                <button type="button" id="aica-memory-deselect-all" class="aica-btn aica-btn-small">Alle abwählen</button>
            </div>
        </div>

        <div id="aica-memory-list-loading" style="padding:20px;text-align:center;color:var(--aica-text-muted);">
            <span class="aica-spinner" style="display:inline-block;margin-right:8px;"></span> Inhalte werden geladen…
        </div>

        <div id="aica-memory-tab-posts" class="aica-memory-post-list" style="display:none;"></div>
        <div id="aica-memory-tab-pages" class="aica-memory-post-list" style="display:none;"></div>
    </div>

    <!-- Manueller Text -->
    <div class="aica-card">
        <h2 class="aica-card-title">Manuellen Text analysieren</h2>
        <div class="aica-form-group">
            <label for="aica-memory-custom-text" class="aica-label">
                Beliebiger Text
                <span class="aica-hint">Wenn leer, wird dieses Feld ignoriert. Min. 50 Zeichen.</span>
            </label>
            <textarea id="aica-memory-custom-text" class="aica-textarea" rows="8"
                placeholder="Füge hier einen Text ein, der analysiert werden soll – z.B. ein Muster-Artikel, Firmentexte, Produktbeschreibungen …"></textarea>
        </div>
    </div>

    <!-- Start-Button -->
    <div class="aica-form-actions" style="margin-bottom:0;">
        <button type="button" id="aica-memory-start" class="aica-btn aica-btn-primary" style="font-size:15px;padding:10px 28px;">
            Analyse starten
        </button>
        <span id="aica-memory-queue-info" style="display:none;font-size:13px;color:var(--aica-text-muted);margin-left:12px;"></span>
    </div>

    <!-- Analyse-Ergebnisse (Live-Info-Box) -->
    <div id="aica-memory-results" class="aica-card" style="display:none;">
        <h2 class="aica-card-title">Analyse-Ergebnisse</h2>
        <div id="aica-memory-results-list"></div>
    </div>

    <!-- Vorhandene Analysen -->
    <div class="aica-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
            <h2 class="aica-card-title" style="margin:0;">Gespeicherte Analysen</h2>
            <button type="button" id="aica-memory-reload-entries" class="aica-btn aica-btn-small">Aktualisieren</button>
        </div>
        <div id="aica-memory-entries-loading" style="padding:12px;color:var(--aica-text-muted);font-size:13px;">Wird geladen…</div>
        <div id="aica-memory-entries-list"></div>
    </div>
</div>
