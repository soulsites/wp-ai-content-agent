<?php
defined( 'ABSPATH' ) || exit;

$saved          = ! empty( $_GET['saved'] );
$models         = \AICA\Settings::get_available_models();
$current_model  = \AICA\Settings::get_model();
$api_key        = \AICA\Settings::get_api_key();
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>Einstellungen</h1>
        <p class="aica-header-sub">Konfiguriere die Claude API und allgemeine Plugin-Optionen.</p>
    </div>

    <?php if ( $saved ) : ?>
    <div class="notice notice-success is-dismissible"><p>Einstellungen wurden gespeichert.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'aica_save_settings' ); ?>
        <input type="hidden" name="action" value="aica_save_settings">

        <!-- API-Konfiguration -->
        <div class="aica-card">
            <h2 class="aica-card-title">Claude API-Konfiguration</h2>

            <div class="aica-form-group">
                <label for="aica_api_key" class="aica-label">
                    Anthropic API-Schlüssel *
                    <span class="aica-hint">Erhältlich auf <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></span>
                </label>
                <div class="aica-api-key-field">
                    <input type="password" id="aica_api_key" name="aica_api_key"
                        value="<?php echo $api_key ? '***SAVED***' : ''; ?>"
                        placeholder="sk-ant-api03-..."
                        class="regular-text aica-input"
                        autocomplete="new-password">
                    <button type="button" id="aica-toggle-key" class="aica-btn aica-btn-small">Anzeigen</button>
                    <button type="button" id="aica-test-api" class="aica-btn aica-btn-secondary">
                        Verbindung testen
                    </button>
                </div>
                <div id="aica-api-test-result" class="aica-api-test-result"></div>
            </div>

            <div class="aica-form-group">
                <label for="aica_model" class="aica-label">
                    Standard-Modell
                    <span class="aica-hint">Kann pro Agent überschrieben werden</span>
                </label>
                <select id="aica_model" name="aica_model" class="aica-select">
                    <?php foreach ( $models as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_model, $key ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="aica-form-row">
                <div class="aica-form-group aica-form-half">
                    <label for="aica_max_tokens" class="aica-label">
                        Max. Tokens (Standard)
                        <span class="aica-hint">Für den Content-Writer empfohlen: 8000+</span>
                    </label>
                    <input type="number" id="aica_max_tokens" name="aica_max_tokens"
                        value="<?php echo esc_attr( \AICA\Settings::get_max_tokens() ); ?>"
                        min="500" max="100000" step="100"
                        class="small-text aica-input">
                </div>

                <div class="aica-form-group aica-form-half">
                    <label for="aica_temperature" class="aica-label">
                        Temperature (Standard)
                        <span class="aica-hint">0 = deterministisch, 1 = kreativ</span>
                    </label>
                    <input type="number" id="aica_temperature" name="aica_temperature"
                        value="<?php echo esc_attr( \AICA\Settings::get_temperature() ); ?>"
                        min="0" max="1" step="0.1"
                        class="small-text aica-input">
                </div>
            </div>
        </div>

        <!-- Content-Einstellungen -->
        <div class="aica-card">
            <h2 class="aica-card-title">Content-Einstellungen</h2>

            <div class="aica-form-row">
                <div class="aica-form-group aica-form-half">
                    <label for="aica_default_lang" class="aica-label">Standard-Sprache</label>
                    <select id="aica_default_lang" name="aica_default_lang" class="aica-select">
                        <option value="de" <?php selected( \AICA\Settings::get_default_language(), 'de' ); ?>>
                            Deutsch
                        </option>
                        <option value="en" <?php selected( \AICA\Settings::get_default_language(), 'en' ); ?>>
                            Englisch
                        </option>
                    </select>
                </div>

                <div class="aica-form-group aica-form-half">
                    <label for="aica_default_status" class="aica-label">Standard Post-Status</label>
                    <select id="aica_default_status" name="aica_default_status" class="aica-select">
                        <?php
                        $current_status = \AICA\Settings::get( 'aica_default_status', 'draft' );
                        $statuses = [ 'draft' => 'Entwurf', 'publish' => 'Veröffentlicht', 'pending' => 'Ausstehend' ];
                        foreach ( $statuses as $k => $v ) :
                        ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $current_status, $k ); ?>>
                            <?php echo esc_html( $v ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="aica-form-group">
                <label class="aica-label">
                    <input type="checkbox" name="aica_auto_publish" value="1"
                        <?php checked( \AICA\Settings::get( 'aica_auto_publish', 0 ), 1 ); ?>>
                    Automatisch veröffentlichen (überschreibt Post-Status-Einstellung)
                </label>
            </div>
        </div>

        <div class="aica-form-actions">
            <?php submit_button( 'Einstellungen speichern', 'primary aica-btn aica-btn-primary', 'submit', false ); ?>
        </div>
    </form>

    <!-- Git Repositories -->
    <?php $git_repos = \AICA\Settings::get_git_repositories(); ?>
    <div class="aica-card">
        <h2 class="aica-card-title">🔗 Git Repositories</h2>
        <p style="color:var(--aica-text-muted);font-size:13px;margin-top:-8px;margin-bottom:16px;">
            Verknüpfe lokale Git-Repositories. Agenten mit der Fähigkeit <strong>Git Repository</strong> können darauf zugreifen, um z.B. Dokumentation zu erstellen.
        </p>

        <div id="aica-git-repos-list">
            <?php foreach ( $git_repos as $i => $repo ) : ?>
            <div class="aica-git-repo-row">
                <input type="text" class="aica-input aica-git-repo-name" placeholder="Name (z.B. Mein Projekt)"
                       value="<?php echo esc_attr( $repo['name'] ); ?>" style="flex:1;">
                <input type="text" class="aica-input aica-git-repo-path" placeholder="Pfad (z.B. /var/www/html/meinprojekt)"
                       value="<?php echo esc_attr( $repo['path'] ); ?>" style="flex:2;">
                <button type="button" class="aica-btn aica-btn-danger aica-remove-git-repo" style="flex:0 0 auto;">✕</button>
            </div>
            <?php endforeach; ?>
        </div>

        <input type="hidden" name="aica_git_repos_json" id="aica-git-repos-json" value="<?php echo esc_attr( wp_json_encode( $git_repos ) ); ?>">

        <button type="button" id="aica-add-git-repo" class="aica-btn aica-btn-secondary" style="margin-top:12px;">
            + Repository hinzufügen
        </button>
        <p style="font-size:12px;color:var(--aica-text-muted);margin-top:8px;">
            Der Pfad muss ein auf dem Server zugängliches Verzeichnis sein. Ungültige Pfade werden beim Speichern ignoriert.
        </p>
    </div>

    <!-- Gefahrenzone -->
    <div class="aica-card aica-card-danger">
        <h2 class="aica-card-title">Daten zurücksetzen</h2>
        <p>Löscht alle generierten Content-Einträge und Logs (WordPress-Posts bleiben erhalten).</p>
        <button type="button" id="aica-reset-data" class="aica-btn aica-btn-danger">
            Content-Verlauf löschen
        </button>
    </div>
</div>
