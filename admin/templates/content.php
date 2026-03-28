<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

// Pagination
$per_page    = 20;
$current_page = max( 1, absint( $_GET['paged'] ?? 1 ) );
$offset      = ( $current_page - 1 ) * $per_page;
$status_filter = sanitize_key( $_GET['status'] ?? '' );

// Query
$where = $status_filter ? $wpdb->prepare( "WHERE status = %s", $status_filter ) : '';
$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}aica_content {$where}" );
$items = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}aica_content {$where} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}"
);

$status_counts = $wpdb->get_results(
    "SELECT status, COUNT(*) as count FROM {$wpdb->prefix}aica_content GROUP BY status"
);
$counts = [];
foreach ( $status_counts as $sc ) {
    $counts[ $sc->status ] = $sc->count;
}

$status_labels = [
    ''           => 'Alle (' . $total . ')',
    'pending'    => 'Ausstehend (' . ( $counts['pending'] ?? 0 ) . ')',
    'processing' => 'In Bearbeitung (' . ( $counts['processing'] ?? 0 ) . ')',
    'completed'  => 'Abgeschlossen (' . ( $counts['completed'] ?? 0 ) . ')',
    'error'      => 'Fehler (' . ( $counts['error'] ?? 0 ) . ')',
];
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>📋 Content-Verlauf</h1>
        <p class="aica-header-sub">Alle generierten Artikel und deren Status.</p>
    </div>

    <!-- Status-Filter -->
    <div class="aica-filter-bar">
        <?php foreach ( $status_labels as $key => $label ) : ?>
        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'aica-content', 'status' => $key, 'paged' => 1 ], admin_url( 'admin.php' ) ) ); ?>"
           class="aica-filter-link <?php echo $status_filter === $key ? 'active' : ''; ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aica-generate' ) ); ?>"
           class="aica-btn aica-btn-primary aica-btn-small" style="margin-left:auto;">
            ➕ Neuer Artikel
        </a>
    </div>

    <!-- Content-Tabelle -->
    <div class="aica-card">
        <?php if ( empty( $items ) ) : ?>
        <p class="aica-empty">Keine Einträge gefunden.</p>
        <?php else : ?>
        <table class="aica-table aica-table-full">
            <thead>
                <tr>
                    <th>Thema</th>
                    <th>Status</th>
                    <th>Wörter</th>
                    <th>SEO</th>
                    <th>Tokens</th>
                    <th>Dauer</th>
                    <th>Erstellt</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) : ?>
                <tr id="aica-content-row-<?php echo esc_attr( $item->id ); ?>">
                    <td>
                        <strong><?php echo esc_html( mb_strimwidth( $item->topic, 0, 60, '...' ) ); ?></strong>
                        <?php if ( $item->keywords ) : ?>
                        <br><small class="aica-keywords">🔑 <?php echo esc_html( mb_strimwidth( $item->keywords, 0, 50, '...' ) ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="aica-status aica-status-<?php echo esc_attr( $item->status ); ?>"
                              id="status-<?php echo esc_attr( $item->id ); ?>">
                            <?php
                            $sl = [ 'pending' => '⏳ Ausstehend', 'processing' => '🔄 Läuft', 'completed' => '✅ Fertig', 'error' => '❌ Fehler' ];
                            echo esc_html( $sl[ $item->status ] ?? $item->status );
                            ?>
                        </span>
                        <?php if ( $item->status === 'error' && $item->error_message ) : ?>
                        <br><small class="aica-error-msg" title="<?php echo esc_attr( $item->error_message ); ?>">
                            <?php echo esc_html( mb_strimwidth( $item->error_message, 0, 40, '...' ) ); ?>
                        </small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item->word_count ? number_format_i18n( $item->word_count ) : '–'; ?></td>
                    <td>
                        <?php if ( $item->seo_score ) : ?>
                        <span class="aica-seo-score aica-seo-<?php echo $item->seo_score >= 70 ? 'good' : ( $item->seo_score >= 50 ? 'medium' : 'poor' ); ?>">
                            <?php echo esc_html( $item->seo_score ); ?>/100
                        </span>
                        <?php else : ?>–<?php endif; ?>
                    </td>
                    <td><?php echo $item->tokens_used ? number_format_i18n( $item->tokens_used ) : '–'; ?></td>
                    <td><?php echo $item->duration_sec ? round( $item->duration_sec ) . 's' : '–'; ?></td>
                    <td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $item->created_at ) ) ); ?></td>
                    <td class="aica-table-actions">
                        <?php if ( $item->wp_post_id ) : ?>
                        <a href="<?php echo esc_url( get_edit_post_link( $item->wp_post_id ) ); ?>"
                           class="aica-btn aica-btn-small aica-btn-secondary" target="_blank"
                           title="Post bearbeiten">✏️</a>
                        <a href="<?php echo esc_url( get_permalink( $item->wp_post_id ) ); ?>"
                           class="aica-btn aica-btn-small aica-btn-secondary" target="_blank"
                           title="Post anzeigen">👁️</a>
                        <?php endif; ?>
                        <button type="button" class="aica-btn aica-btn-small aica-btn-secondary aica-view-details"
                                data-id="<?php echo esc_attr( $item->id ); ?>"
                                title="Details anzeigen">📋</button>
                        <button type="button" class="aica-btn aica-btn-small aica-btn-danger aica-delete-content"
                                data-id="<?php echo esc_attr( $item->id ); ?>"
                                title="Löschen">🗑️</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <?php if ( $total > $per_page ) : ?>
        <div class="aica-pagination">
            <?php
            $total_pages = ceil( $total / $per_page );
            for ( $i = 1; $i <= $total_pages; $i++ ) :
                $url = add_query_arg( [ 'page' => 'aica-content', 'status' => $status_filter, 'paged' => $i ], admin_url( 'admin.php' ) );
            ?>
            <a href="<?php echo esc_url( $url ); ?>"
               class="aica-page-link <?php echo $current_page === $i ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Details Modal -->
    <div id="aica-details-modal" class="aica-modal" style="display:none;">
        <div class="aica-modal-backdrop"></div>
        <div class="aica-modal-content aica-modal-xlarge">
            <div class="aica-modal-header">
                <h2>📋 Generierungs-Details</h2>
                <button type="button" class="aica-modal-close">✕</button>
            </div>
            <div class="aica-modal-body" id="aica-details-body">
                <div class="aica-loading-spinner">Lade Details...</div>
            </div>
        </div>
    </div>
</div>
