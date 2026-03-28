<?php
defined( 'ABSPATH' ) || exit;

$jobs           = \AICA\Cron_Manager::get_instance()->get_scheduled_jobs();
$categories     = get_categories( [ 'hide_empty' => false ] );
$voice_profiles = \AICA\Settings::get_voice_profiles();
$schedules      = \AICA\Settings::get_available_schedules();
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>⏰ Automatisierungs-Jobs</h1>
        <p class="aica-header-sub">Erstelle und verwalte geplante Content-Generierungen via WP-Cron.</p>
    </div>

    <div class="aica-toolbar">
        <button type="button" id="aica-add-job" class="aica-btn aica-btn-primary">
            ➕ Neuen Job erstellen
        </button>
    </div>

    <!-- Job-Tabelle -->
    <?php if ( empty( $jobs ) ) : ?>
    <div class="aica-card aica-empty-state">
        <p>Noch keine Jobs erstellt. Automatisierte Content-Generierung via WP-Cron einrichten.</p>
        <button type="button" id="aica-add-job-empty" class="aica-btn aica-btn-primary">
            ➕ Ersten Job erstellen
        </button>
    </div>
    <?php else : ?>
    <div class="aica-card">
        <table class="aica-table aica-table-full">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Thema</th>
                    <th>Schedule</th>
                    <th>Status</th>
                    <th>Nächste Ausführung</th>
                    <th>Ausführungen</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $jobs as $job ) : ?>
                <tr data-job-id="<?php echo esc_attr( $job->id ); ?>">
                    <td><strong><?php echo esc_html( $job->name ); ?></strong></td>
                    <td><?php echo esc_html( mb_strimwidth( $job->topic, 0, 50, '...' ) ); ?></td>
                    <td>
                        <span class="aica-badge aica-badge-schedule">
                            <?php echo esc_html( $schedules[ $job->schedule ] ?? $job->schedule ); ?>
                        </span>
                    </td>
                    <td>
                        <span class="aica-status <?php echo $job->active ? 'aica-status-completed' : 'aica-status-error'; ?>">
                            <?php echo $job->active ? '✅ Aktiv' : '⏸️ Pausiert'; ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        if ( $job->next_scheduled ) {
                            echo esc_html( wp_date( 'd.m.Y H:i', $job->next_scheduled ) );
                        } elseif ( 'manual' === $job->schedule ) {
                            echo '<em>Manuell</em>';
                        } else {
                            echo '<em>Nicht geplant</em>';
                        }
                        ?>
                    </td>
                    <td><?php echo number_format_i18n( $job->run_count ); ?></td>
                    <td class="aica-table-actions">
                        <button type="button" class="aica-btn aica-btn-small aica-btn-secondary aica-run-job-now"
                                data-id="<?php echo esc_attr( $job->id ); ?>"
                                title="Jetzt ausführen">
                            ▶️ Jetzt
                        </button>
                        <button type="button" class="aica-btn aica-btn-small aica-btn-secondary aica-edit-job"
                                data-id="<?php echo esc_attr( $job->id ); ?>"
                                title="Bearbeiten">
                            ✏️
                        </button>
                        <button type="button" class="aica-btn aica-btn-small aica-btn-danger aica-delete-job"
                                data-id="<?php echo esc_attr( $job->id ); ?>"
                                title="Löschen">
                            🗑️
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Modal: Job erstellen/bearbeiten -->
    <div id="aica-job-modal" class="aica-modal" style="display:none;">
        <div class="aica-modal-backdrop"></div>
        <div class="aica-modal-content aica-modal-large">
            <div class="aica-modal-header">
                <h2 id="aica-job-modal-title">Neuen Job erstellen</h2>
                <button type="button" class="aica-modal-close">✕</button>
            </div>
            <div class="aica-modal-body">
                <form id="aica-job-form">
                    <input type="hidden" id="aica-job-id" name="job_id" value="0">

                    <div class="aica-form-group">
                        <label class="aica-label">Job-Name *</label>
                        <input type="text" id="aica-job-name" name="name" required
                               class="large-text aica-input"
                               placeholder="z.B. 'Wöchentliche SEO-Artikel'">
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">
                            Thema *
                            <span class="aica-hint">Kannst du variieren – nutze Platzhalter wie {Monat} für dynamische Themen</span>
                        </label>
                        <textarea id="aica-job-topic" name="topic" rows="3" required
                                  class="large-text aica-input"
                                  placeholder="z.B. 'Aktuelle SEO-Trends und Best Practices für Online-Marketing'"></textarea>
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">Keywords</label>
                        <input type="text" id="aica-job-keywords" name="keywords"
                               class="large-text aica-input"
                               placeholder="Kommagetrennte Ziel-Keywords">
                    </div>

                    <div class="aica-form-row">
                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">Ausführungsplan *</label>
                            <select id="aica-job-schedule" name="schedule" class="aica-select">
                                <?php foreach ( $schedules as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">Post-Status</label>
                            <select id="aica-job-post-status" name="post_status" class="aica-select">
                                <option value="draft">Entwurf</option>
                                <option value="publish">Veröffentlicht</option>
                                <option value="pending">Ausstehend</option>
                            </select>
                        </div>
                    </div>

                    <div class="aica-form-row">
                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">Voice Profil</label>
                            <select id="aica-job-voice" name="voice_id" class="aica-select">
                                <option value="0">— Standard —</option>
                                <?php foreach ( $voice_profiles as $p ) : ?>
                                <option value="<?php echo esc_attr( $p['id'] ); ?>">
                                    <?php echo esc_html( $p['name'] ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="aica-form-group aica-form-half">
                            <label class="aica-label">Kategorie</label>
                            <select id="aica-job-category" name="category_id" class="aica-select">
                                <option value="0">— Keine —</option>
                                <?php foreach ( $categories as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="aica-form-group">
                        <label class="aica-label">Mindest-Wortanzahl</label>
                        <input type="number" name="min_word_count" value="800"
                               min="300" max="5000" step="100"
                               class="small-text aica-input">
                    </div>
                </form>
            </div>
            <div class="aica-modal-footer">
                <button type="button" class="aica-btn aica-btn-secondary aica-modal-close">Abbrechen</button>
                <button type="button" id="aica-save-job" class="aica-btn aica-btn-primary">
                    💾 Job speichern
                </button>
            </div>
        </div>
    </div>

    <!-- Job-Daten für JS -->
    <script id="aica-jobs-data" type="application/json">
    <?php echo wp_json_encode( $jobs ); ?>
    </script>
</div>
