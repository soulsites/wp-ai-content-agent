<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

// Statistiken laden
$total_content = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aica_content" );
$completed     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aica_content WHERE status = 'completed'" );
$pending       = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aica_content WHERE status IN ('pending','processing')" );
$total_words   = (int) $wpdb->get_var( "SELECT SUM(word_count) FROM {$wpdb->prefix}aica_content WHERE status = 'completed'" );
$total_tokens  = (int) $wpdb->get_var( "SELECT SUM(tokens_used) FROM {$wpdb->prefix}aica_content" );
$total_jobs    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aica_jobs WHERE active = 1" );
$avg_seo_score = round( (float) $wpdb->get_var( "SELECT AVG(seo_score) FROM {$wpdb->prefix}aica_content WHERE status = 'completed' AND seo_score IS NOT NULL" ) );

// Letzte Generierungen
$recent = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}aica_content ORDER BY created_at DESC LIMIT 5"
);

$api_configured = ! empty( \AICA\Settings::get_api_key() );
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1><span class="aica-logo">🤖</span> WP AI Content Agent</h1>
        <p class="aica-header-sub">Multi-Agenten SEO-Content-Generierung mit Claude AI</p>
    </div>

    <?php if ( ! $api_configured ) : ?>
    <div class="notice notice-warning aica-notice">
        <p><strong>⚠️ API-Schlüssel fehlt!</strong> Bitte konfiguriere deinen
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-settings' ) ); ?>">Anthropic API-Schlüssel</a>
        in den Einstellungen, um loszulegen.</p>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="aica-stats-grid">
        <div class="aica-stat-card">
            <div class="aica-stat-icon">📝</div>
            <div class="aica-stat-value"><?php echo number_format_i18n( $completed ); ?></div>
            <div class="aica-stat-label">Generierte Artikel</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-icon">📊</div>
            <div class="aica-stat-value"><?php echo number_format_i18n( $total_words ); ?></div>
            <div class="aica-stat-label">Generierte Wörter</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-icon">⚡</div>
            <div class="aica-stat-value"><?php echo number_format_i18n( $total_jobs ); ?></div>
            <div class="aica-stat-label">Aktive Cronjobs</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-icon">🎯</div>
            <div class="aica-stat-value"><?php echo $avg_seo_score ? $avg_seo_score . '/100' : '–'; ?></div>
            <div class="aica-stat-label">Ø SEO-Score</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-icon">🔢</div>
            <div class="aica-stat-value"><?php echo number_format_i18n( $total_tokens ); ?></div>
            <div class="aica-stat-label">Verwendete Tokens</div>
        </div>
        <div class="aica-stat-card <?php echo $pending ? 'aica-stat-active' : ''; ?>">
            <div class="aica-stat-icon">⏳</div>
            <div class="aica-stat-value"><?php echo number_format_i18n( $pending ); ?></div>
            <div class="aica-stat-label">In Bearbeitung</div>
        </div>
    </div>

    <div class="aica-dashboard-grid">
        <!-- Schnellstart -->
        <div class="aica-card">
            <h2 class="aica-card-title">⚡ Schnellstart</h2>
            <div class="aica-quick-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-generate' ) ); ?>"
                   class="aica-btn aica-btn-primary aica-btn-large">
                    ✍️ Jetzt Artikel generieren
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-jobs' ) ); ?>"
                   class="aica-btn aica-btn-secondary">
                    ⏰ Cronjob erstellen
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-voices' ) ); ?>"
                   class="aica-btn aica-btn-secondary">
                    🎭 Voice Profile
                </a>
            </div>

            <div class="aica-agent-pipeline">
                <h3>Agenten-Pipeline</h3>
                <div class="aica-pipeline">
                    <div class="aica-pipeline-step">
                        <div class="aica-pipeline-icon">🔍</div>
                        <div class="aica-pipeline-label">Content-Analyse</div>
                    </div>
                    <div class="aica-pipeline-arrow">→</div>
                    <div class="aica-pipeline-step">
                        <div class="aica-pipeline-icon">👥</div>
                        <div class="aica-pipeline-label">Zielgruppe</div>
                    </div>
                    <div class="aica-pipeline-arrow">→</div>
                    <div class="aica-pipeline-step">
                        <div class="aica-pipeline-icon">🔑</div>
                        <div class="aica-pipeline-label">Keywords</div>
                    </div>
                    <div class="aica-pipeline-arrow">→</div>
                    <div class="aica-pipeline-step">
                        <div class="aica-pipeline-icon">📚</div>
                        <div class="aica-pipeline-label">Recherche</div>
                    </div>
                    <div class="aica-pipeline-arrow">→</div>
                    <div class="aica-pipeline-step">
                        <div class="aica-pipeline-icon">✍️</div>
                        <div class="aica-pipeline-label">Schreiben</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Letzte Aktivitäten -->
        <div class="aica-card">
            <h2 class="aica-card-title">🕐 Letzte Generierungen</h2>
            <?php if ( empty( $recent ) ) : ?>
                <p class="aica-empty">Noch keine Artikel generiert.
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-generate' ) ); ?>">Ersten Artikel erstellen →</a>
                </p>
            <?php else : ?>
                <table class="aica-table">
                    <thead>
                        <tr>
                            <th>Thema</th>
                            <th>Status</th>
                            <th>Wörter</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $recent as $item ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( mb_strimwidth( $item->topic, 0, 40, '...' ) ); ?>
                                <?php if ( $item->wp_post_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $item->wp_post_id ) ); ?>"
                                       title="Post bearbeiten" target="_blank">✏️</a>
                                <?php endif; ?>
                            </td>
                            <td><span class="aica-status aica-status-<?php echo esc_attr( $item->status ); ?>">
                                <?php echo esc_html( self::status_label( $item->status ) ); ?>
                            </span></td>
                            <td><?php echo $item->word_count ? number_format_i18n( $item->word_count ) : '–'; ?></td>
                            <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $item->created_at ) ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-content' ) ); ?>">Alle Artikel anzeigen →</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
function self_status_label( string $status ): string {
    $labels = [
        'pending'    => '⏳ Ausstehend',
        'processing' => '🔄 In Bearbeitung',
        'completed'  => '✅ Abgeschlossen',
        'error'      => '❌ Fehler',
    ];
    return $labels[ $status ] ?? $status;
}

function self( $x ) {
    return new class( $x ) {
        private $v;
        public function __construct( $v ) { $this->v = $v; }
        public static function status_label( string $status ): string {
            $labels = [
                'pending'    => '⏳ Ausstehend',
                'processing' => '🔄 In Bearbeitung',
                'completed'  => '✅ Abgeschlossen',
                'error'      => '❌ Fehler',
            ];
            return $labels[ $status ] ?? $status;
        }
    };
}
