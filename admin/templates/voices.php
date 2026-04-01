<?php
defined( 'ABSPATH' ) || exit;

$profiles = \AICA\Settings::get_voice_profiles();
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>Voice Profile</h1>
        <p class="aica-header-sub">Definiere Schreibstile und Tonalitäten für deine Content-Generierung.</p>
    </div>

    <div class="aica-toolbar">
        <button type="button" id="aica-add-voice" class="aica-btn aica-btn-primary">
            Neues Voice Profil
        </button>
    </div>

    <!-- Profil-Liste -->
    <div id="aica-voices-list">
        <?php if ( empty( $profiles ) ) : ?>
        <div class="aica-card aica-empty-state">
            <p>Noch keine Voice Profile erstellt.</p>
            <button type="button" id="aica-add-voice-empty" class="aica-btn aica-btn-primary">
                Erstes Profil erstellen
            </button>
        </div>
        <?php else : ?>
        <div class="aica-voices-grid">
            <?php foreach ( $profiles as $profile ) : ?>
            <div class="aica-voice-card" data-id="<?php echo esc_attr( $profile['id'] ); ?>">
                <div class="aica-voice-card-header">
                    <h3><?php echo esc_html( $profile['name'] ); ?></h3>
                    <div class="aica-voice-card-actions">
                        <button type="button" class="aica-btn aica-btn-small aica-btn-secondary aica-edit-voice"
                                data-id="<?php echo esc_attr( $profile['id'] ); ?>">
                            Bearbeiten
                        </button>
                        <button type="button" class="aica-btn aica-btn-small aica-btn-danger aica-delete-voice"
                                data-id="<?php echo esc_attr( $profile['id'] ); ?>">
                            
                        </button>
                    </div>
                </div>
                <p class="aica-voice-desc"><?php echo esc_html( $profile['description'] ?? '' ); ?></p>
                <div class="aica-voice-meta">
                    <span class="aica-voice-meta-item"><?php echo esc_html( $profile['tone'] ?? '' ); ?></span>
                </div>
                <?php if ( ! empty( $profile['example'] ) ) : ?>
                <blockquote class="aica-voice-example">
                    <?php echo esc_html( mb_strimwidth( $profile['example'], 0, 150, '...' ) ); ?>
                </blockquote>
                <?php endif; ?>
                <?php if ( ! empty( $profile['evaluations'] ) ) :
                    $ratings = array_column( $profile['evaluations'], 'rating' );
                    $avg = count( $ratings ) ? round( array_sum( $ratings ) / count( $ratings ), 1 ) : 0;
                ?>
                <div class="aica-voice-stats">
                    Ø <?php echo esc_html( $avg ); ?> aus <?php echo count( $ratings ); ?> Bewertungen
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Modal: Voice Profil bearbeiten/erstellen -->
    <div id="aica-voice-modal" class="aica-modal" style="display:none;">
        <div class="aica-modal-backdrop"></div>
        <div class="aica-modal-content aica-modal-large">
            <div class="aica-modal-header">
                <h2 id="aica-voice-modal-title">Voice Profil erstellen</h2>
                <button type="button" class="aica-modal-close">✕</button>
            </div>
            <div class="aica-modal-body">
                <form id="aica-voice-form">
                    <input type="hidden" id="aica-voice-id" name="voice_id" value="0">

                    <div class="aica-form-group">
                        <label class="aica-label">Name des Profils *</label>
                        <input type="text" id="aica-voice-name" name="name" required
                               class="large-text aica-input"
                               placeholder="z.B. 'Expertenautorität'">
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">Kurzbeschreibung</label>
                        <input type="text" id="aica-voice-description" name="description"
                               class="large-text aica-input"
                               placeholder="z.B. 'Fachlich fundiert, präzise, für B2B'">
                    </div>

                    <div class="aica-form-row">
                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">Tonalität</label>
                            <input type="text" id="aica-voice-tone" name="tone"
                                   class="regular-text aica-input"
                                   placeholder="professionell, sachlich, kompetent">
                        </div>
                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">Zu vermeiden</label>
                            <input type="text" id="aica-voice-avoid" name="avoid"
                                   class="regular-text aica-input"
                                   placeholder="Slang, Übertreibungen, Passivformulierungen">
                        </div>
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">Schreibstil-Beschreibung</label>
                        <textarea id="aica-voice-style" name="style" rows="3"
                                  class="large-text aica-input"
                                  placeholder="klar strukturiert, Fakten-orientiert, direkte Ansprache..."></textarea>
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">
                            Beispieltext
                            <span class="aica-hint">Ein Absatz, der den gewünschten Stil perfekt repräsentiert. Die KI wird diesen als Referenz nutzen.</span>
                        </label>
                        <textarea id="aica-voice-example" name="example" rows="5"
                                  class="large-text aica-input"
                                  placeholder="Füge hier einen Beispieltext ein, der deinen gewünschten Schreibstil zeigt..."></textarea>
                        <p class="description">💡 Je besser der Beispieltext deinen Stil trifft, desto konsistenter wird der generierte Content.</p>
                    </div>

                    <!-- Evaluierungsabschnitt (für bestehende Profile) -->
                    <div id="aica-voice-evaluations-section" style="display:none;">
                        <hr>
                        <h3>📊 Content-Performance Tracking</h3>
                        <p class="description">Bewerte generierten Content, um zukünftige Generierungen zu verbessern.</p>
                        <div id="aica-evaluations-list"></div>
                        <div class="aica-form-group">
                            <label class="aica-label">Neuen Erfolgstext hinzufügen</label>
                            <textarea id="aica-new-eval-excerpt" rows="4" class="large-text aica-input"
                                      placeholder="Füge einen Ausschnitt aus einem gut performenden Artikel ein..."></textarea>
                            <div class="aica-form-row">
                                <div class="aica-form-group">
                                    <label class="aica-label">Bewertung (1-5 ⭐)</label>
                                    <select id="aica-new-eval-rating" class="aica-select">
                                        <?php for ( $i = 5; $i >= 1; $i-- ) : ?>
                                        <option value="<?php echo $i; ?>"><?php echo str_repeat( '⭐', $i ); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="aica-form-group">
                                    <label class="aica-label">Notiz</label>
                                    <input type="text" id="aica-new-eval-note" class="regular-text aica-input"
                                           placeholder="z.B. 'Beste CTR im Monat'">
                                </div>
                            </div>
                            <button type="button" id="aica-add-evaluation" class="aica-btn aica-btn-secondary">
                                ➕ Evaluierung hinzufügen
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="aica-modal-footer">
                <button type="button" class="aica-btn aica-btn-secondary aica-modal-close">Abbrechen</button>
                <button type="button" id="aica-save-voice" class="aica-btn aica-btn-primary">
                    💾 Profil speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Profil-Daten für JS -->
    <script id="aica-profiles-data" type="application/json">
    <?php echo wp_json_encode( $profiles ); ?>
    </script>
</div>
