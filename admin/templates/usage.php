<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'aica_content';
$days  = 30;

// All-time totals
$all_time = $wpdb->get_row(
    "SELECT COUNT(*) AS articles, SUM(tokens_used) AS tokens, AVG(tokens_used) AS avg_tokens
     FROM {$table} WHERE status = 'completed'"
);

// Period totals
$period = $wpdb->get_row( $wpdb->prepare(
    "SELECT COUNT(*) AS articles, SUM(tokens_used) AS tokens, AVG(tokens_used) AS avg_tokens
     FROM {$table}
     WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
    $days
) );

// Daily chart data
$daily = $wpdb->get_results( $wpdb->prepare(
    "SELECT DATE(created_at) AS day, COUNT(*) AS articles, SUM(tokens_used) AS tokens
     FROM {$table}
     WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
     GROUP BY DATE(created_at)
     ORDER BY day ASC",
    $days
) );

$chart_labels   = [];
$chart_tokens   = [];
$chart_articles = [];
foreach ( $daily as $row ) {
    $chart_labels[]   = wp_date( 'd.m.', strtotime( $row->day ) );
    $chart_tokens[]   = (int) $row->tokens;
    $chart_articles[] = (int) $row->articles;
}
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1>Token-Verbrauch</h1>
        <p class="aica-header-sub">Übersicht über den API-Token-Verbrauch nach Zeitraum.</p>
    </div>

    <!-- Summary Stats -->
    <div class="aica-usage-stats-row">
        <div class="aica-stat-card">
            <div class="aica-stat-value"><?php echo number_format_i18n( (int)( $all_time->tokens ?? 0 ) ); ?></div>
            <div class="aica-stat-label">Tokens gesamt</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-value"><?php echo number_format_i18n( (int)( $all_time->articles ?? 0 ) ); ?></div>
            <div class="aica-stat-label">Artikel gesamt</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-value"><?php echo number_format_i18n( (int)( $all_time->avg_tokens ?? 0 ) ); ?></div>
            <div class="aica-stat-label">Tokens pro Artikel</div>
        </div>
        <div class="aica-stat-card">
            <div class="aica-stat-value"><?php echo number_format_i18n( (int)( $period->tokens ?? 0 ) ); ?></div>
            <div class="aica-stat-label">Tokens letzte 30 Tage</div>
        </div>
    </div>

    <!-- Filter -->
    <div class="aica-card aica-usage-filters">
        <div class="aica-filters-inner">
            <div class="aica-filter-group">
                <label class="aica-label">Zeitraum</label>
                <div class="aica-period-btns">
                    <button class="aica-period-btn" data-days="7">7 Tage</button>
                    <button class="aica-period-btn" data-days="14">14 Tage</button>
                    <button class="aica-period-btn active" data-days="30">30 Tage</button>
                    <button class="aica-period-btn" data-days="90">90 Tage</button>
                </div>
            </div>
            <div class="aica-filter-note">
                Verbrauchsdaten stammen aus der lokalen Datenbank.
                Exakte API-Nutzung unter
                <a href="https://console.anthropic.com/settings/usage" target="_blank" rel="noopener">console.anthropic.com</a>.
            </div>
        </div>
    </div>

    <!-- Token-Chart -->
    <div class="aica-card">
        <div class="aica-chart-header">
            <h2 class="aica-card-title">Token-Verbrauch pro Tag</h2>
            <div class="aica-chart-summary-inline">
                <span class="aica-summary-chip">
                    <strong id="sum-tokens"><?php echo number_format_i18n( (int)( $period->tokens ?? 0 ) ); ?></strong> Tokens
                </span>
                <span class="aica-summary-chip">
                    <strong id="sum-articles"><?php echo (int)( $period->articles ?? 0 ); ?></strong> Artikel
                </span>
                <span class="aica-summary-chip">
                    Ø <strong id="sum-avg"><?php echo number_format_i18n( (int)( $period->avg_tokens ?? 0 ) ); ?></strong> / Artikel
                </span>
            </div>
        </div>
        <div class="aica-chart-container">
            <canvas id="aica-tokens-chart"></canvas>
        </div>
    </div>

    <!-- Artikel-Chart -->
    <div class="aica-card">
        <h2 class="aica-card-title">Artikel pro Tag</h2>
        <div class="aica-chart-container aica-chart-sm">
            <canvas id="aica-articles-chart"></canvas>
        </div>
    </div>

    <!-- Hinweis -->
    <div class="aica-card">
        <h2 class="aica-card-title">Hinweis zu API-Kosten</h2>
        <p style="font-size:14px;color:var(--aica-text-muted);margin:0;line-height:1.6;">
            Die Anthropic API stellt keine Endpunkte zur Abfrage von Kontostand oder exakten Kosten bereit.
            Bitte prüfe Guthaben und Abrechnung direkt im
            <a href="https://console.anthropic.com/settings/billing" target="_blank" rel="noopener">Anthropic Console</a>.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function($) {
    'use strict';

    var primary   = '#6750A4';
    var primary20 = 'rgba(103,80,164,.18)';
    var success   = '#2d6a3f';
    var success20 = 'rgba(45,106,63,.15)';

    var initLabels   = <?php echo wp_json_encode( $chart_labels ); ?>;
    var initTokens   = <?php echo wp_json_encode( $chart_tokens ); ?>;
    var initArticles = <?php echo wp_json_encode( $chart_articles ); ?>;

    var activeDays = 30;

    // --- Token Chart ---
    var tokCtx = document.getElementById('aica-tokens-chart').getContext('2d');
    var tokChart = new Chart(tokCtx, {
        type: 'bar',
        data: {
            labels: initLabels,
            datasets: [{
                label: 'Tokens',
                data: initTokens,
                backgroundColor: primary20,
                borderColor: primary,
                borderWidth: 1.5,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.parsed.y.toLocaleString('de-DE') + ' Tokens';
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            return v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v;
                        }
                    }
                }
            }
        }
    });

    // --- Article Chart ---
    var artCtx = document.getElementById('aica-articles-chart').getContext('2d');
    var artChart = new Chart(artCtx, {
        type: 'bar',
        data: {
            labels: initLabels,
            datasets: [{
                label: 'Artikel',
                data: initArticles,
                backgroundColor: success20,
                borderColor: success,
                borderWidth: 1.5,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
            }
        }
    });

    // --- Filter ---
    function loadData() {
        $.get(aicaData.ajaxUrl, {
            action: 'aica_get_usage_data',
            nonce:  aicaData.nonce,
            days:   activeDays,
        }, function(resp) {
            if (!resp.success) return;
            var d = resp.data;

            tokChart.data.labels           = d.chart.labels;
            tokChart.data.datasets[0].data = d.chart.tokens;
            tokChart.update();

            artChart.data.labels           = d.chart.labels;
            artChart.data.datasets[0].data = d.chart.articles;
            artChart.update();

            document.getElementById('sum-tokens').textContent   =
                d.summary.total_tokens.toLocaleString('de-DE');
            document.getElementById('sum-articles').textContent = d.summary.total_articles;
            document.getElementById('sum-avg').textContent      =
                d.summary.avg_tokens.toLocaleString('de-DE');
        });
    }

    document.querySelectorAll('.aica-period-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.aica-period-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            activeDays = parseInt(this.dataset.days, 10);
            loadData();
        });
    });

})(jQuery);
</script>
