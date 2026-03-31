<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;

$table        = $wpdb->prefix . 'aica_content';
$current_model = \AICA\Settings::get_model();
$pricing       = \AICA\API_Client::PRICING[ $current_model ] ?? \AICA\API_Client::PRICING['claude-sonnet-4-6'];
$cost_per_tok  = ( $pricing['input'] * 0.4 + $pricing['output'] * 0.6 ) / 1_000_000;
$api_client    = new \AICA\API_Client();
$api_key       = \AICA\Settings::get_api_key();

// Standard-Zeitraum: 30 Tage
$days = 30;

// Gesamt-Statistiken (aller Zeiten)
$all_time = $wpdb->get_row(
    "SELECT COUNT(*) AS articles, SUM(tokens_used) AS tokens, SUM(word_count) AS words
     FROM {$table} WHERE status = 'completed'"
);
$alltime_tokens = (int) ( $all_time->tokens ?? 0 );
$alltime_cost   = $alltime_tokens * $cost_per_tok;

// Letzten 30 Tage für initiale Chart-Daten
$daily = $wpdb->get_results( $wpdb->prepare(
    "SELECT DATE(created_at) AS day, COUNT(*) AS articles, SUM(tokens_used) AS tokens
     FROM {$table}
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND status = 'completed'
     GROUP BY DATE(created_at)
     ORDER BY day ASC",
    $days
) );

$chart_labels   = [];
$chart_tokens   = [];
$chart_costs    = [];
$chart_articles = [];

foreach ( $daily as $row ) {
    $chart_labels[]   = wp_date( 'd.m.', strtotime( $row->day ) );
    $chart_tokens[]   = (int) $row->tokens;
    $chart_costs[]    = round( (int) $row->tokens * $cost_per_tok, 4 );
    $chart_articles[] = (int) $row->articles;
}

// Zusammenfassung letzter 30 Tage
$period_summary = $wpdb->get_row( $wpdb->prepare(
    "SELECT COUNT(*) AS articles, SUM(tokens_used) AS tokens, AVG(tokens_used) AS avg_tokens
     FROM {$table}
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY) AND status = 'completed'",
    $days
) );
$period_tokens = (int) ( $period_summary->tokens ?? 0 );
$period_cost   = $period_tokens * $cost_per_tok;

// Kontoguthaben abrufen
$balance_data = null;
if ( $api_key ) {
    $balance_data = $api_client->get_account_balance();
}

$models = \AICA\Settings::get_available_models();
?>
<div class="wrap aica-wrap">
    <div class="aica-header">
        <h1><span class="aica-logo">📊</span> Usage & Kosten</h1>
        <p class="aica-header-sub">Token-Verbrauch, Kostenübersicht und API-Guthaben auf einen Blick</p>
    </div>

    <!-- Kontoguthaben -->
    <div class="aica-usage-balance-row">
        <?php if ( $balance_data && isset( $balance_data['credits_granted'] ) ) : ?>
            <?php
            $granted   = (float) ( $balance_data['credits_granted'] ?? 0 );
            $used_bal  = (float) ( $balance_data['credits_used'] ?? 0 );
            $remaining = (float) ( $balance_data['credits_remaining'] ?? ( $granted - $used_bal ) );
            $pct       = $granted > 0 ? round( ( $remaining / $granted ) * 100 ) : 0;
            ?>
            <div class="aica-balance-card aica-balance-ok">
                <div class="aica-balance-header">
                    <span class="aica-balance-icon">💳</span>
                    <span class="aica-balance-title">API-Guthaben</span>
                    <span class="aica-balance-badge">Live</span>
                </div>
                <div class="aica-balance-amount">$<?php echo number_format( $remaining, 2 ); ?></div>
                <div class="aica-balance-sub">von $<?php echo number_format( $granted, 2 ); ?> verbleibend</div>
                <div class="aica-balance-bar">
                    <div class="aica-balance-fill" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
                </div>
                <div class="aica-balance-pct"><?php echo $pct; ?>% verbleibend</div>
            </div>
        <?php else : ?>
            <div class="aica-balance-card aica-balance-na">
                <div class="aica-balance-header">
                    <span class="aica-balance-icon">💳</span>
                    <span class="aica-balance-title">API-Guthaben</span>
                </div>
                <div class="aica-balance-amount">–</div>
                <div class="aica-balance-sub">
                    Nicht über API verfügbar.<br>
                    <a href="https://console.anthropic.com/settings/billing" target="_blank" rel="noopener">
                        Guthaben im Anthropic Console prüfen →
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Gesamt (aller Zeiten) -->
        <div class="aica-balance-card">
            <div class="aica-balance-header">
                <span class="aica-balance-icon">🔢</span>
                <span class="aica-balance-title">Tokens gesamt (alle Zeit)</span>
            </div>
            <div class="aica-balance-amount"><?php echo number_format_i18n( $alltime_tokens ); ?></div>
            <div class="aica-balance-sub">≈ $<?php echo number_format( $alltime_cost, 2 ); ?> geschätzte Kosten</div>
        </div>

        <!-- Letzter 30 Tage -->
        <div class="aica-balance-card">
            <div class="aica-balance-header">
                <span class="aica-balance-icon">📅</span>
                <span class="aica-balance-title">Kosten letzte 30 Tage</span>
            </div>
            <div class="aica-balance-amount">$<?php echo number_format( $period_cost, 4 ); ?></div>
            <div class="aica-balance-sub">
                <?php echo number_format_i18n( $period_tokens ); ?> Tokens,
                <?php echo (int) ( $period_summary->articles ?? 0 ); ?> Artikel
            </div>
        </div>

        <!-- Modell-Info -->
        <div class="aica-balance-card">
            <div class="aica-balance-header">
                <span class="aica-balance-icon">🤖</span>
                <span class="aica-balance-title">Aktives Modell</span>
            </div>
            <div class="aica-balance-amount aica-balance-model"><?php echo esc_html( $models[ $current_model ] ?? $current_model ); ?></div>
            <div class="aica-balance-sub">
                $<?php echo $pricing['input']; ?>/1M Input &nbsp;·&nbsp; $<?php echo $pricing['output']; ?>/1M Output
            </div>
        </div>
    </div>

    <!-- Filter-Leiste -->
    <div class="aica-card aica-usage-filters">
        <div class="aica-filters-inner">
            <div class="aica-filter-group">
                <label class="aica-label" for="aica-filter-days">Zeitraum</label>
                <div class="aica-period-btns">
                    <button class="aica-period-btn active" data-days="7">7 Tage</button>
                    <button class="aica-period-btn" data-days="14">14 Tage</button>
                    <button class="aica-period-btn active-default" data-days="30">30 Tage</button>
                    <button class="aica-period-btn" data-days="90">90 Tage</button>
                </div>
            </div>
            <div class="aica-filter-group">
                <label class="aica-label" for="aica-filter-model">Kostenberechnung Modell</label>
                <select id="aica-filter-model" class="aica-select">
                    <?php foreach ( $models as $mid => $mlabel ) : ?>
                        <option value="<?php echo esc_attr( $mid ); ?>"
                            <?php selected( $mid, $current_model ); ?>>
                            <?php echo esc_html( $mlabel ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="aica-filter-group aica-filter-note">
                <span>* Kosten sind Schätzungen (40% Input / 60% Output)</span>
            </div>
        </div>
    </div>

    <!-- Hauptdiagramm: Tokens + Kosten -->
    <div class="aica-card">
        <div class="aica-chart-header">
            <h2 class="aica-card-title">Token-Verbrauch & geschätzte Kosten</h2>
            <div id="aica-chart-summary" class="aica-chart-summary-inline">
                <span class="aica-summary-chip">
                    <strong id="sum-tokens"><?php echo number_format_i18n( $period_tokens ); ?></strong> Tokens
                </span>
                <span class="aica-summary-chip aica-chip-cost">
                    ≈ <strong>$<span id="sum-cost"><?php echo number_format( $period_cost, 4 ); ?></span></strong>
                </span>
                <span class="aica-summary-chip">
                    <strong id="sum-articles"><?php echo (int) ( $period_summary->articles ?? 0 ); ?></strong> Artikel
                </span>
            </div>
        </div>
        <div class="aica-chart-container">
            <canvas id="aica-usage-chart"></canvas>
        </div>
    </div>

    <!-- Zweite Reihe: Artikel/Tag + Preistabelle -->
    <div class="aica-dashboard-grid">
        <!-- Artikel pro Tag -->
        <div class="aica-card">
            <h2 class="aica-card-title">Artikel pro Tag</h2>
            <div class="aica-chart-container aica-chart-sm">
                <canvas id="aica-articles-chart"></canvas>
            </div>
        </div>

        <!-- Preistabelle -->
        <div class="aica-card">
            <h2 class="aica-card-title">Modell-Preise (Anthropic, 2025)</h2>
            <table class="aica-table">
                <thead>
                    <tr>
                        <th>Modell</th>
                        <th>Input / 1M</th>
                        <th>Output / 1M</th>
                        <th>Ø Kosten/Artikel*</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( \AICA\API_Client::PRICING as $mid => $p ) :
                        $avg_tokens = 15000; // typischer Schätzwert
                        $art_cost   = ( $avg_tokens * 0.4 * $p['input'] + $avg_tokens * 0.6 * $p['output'] ) / 1_000_000;
                    ?>
                    <tr <?php if ( $mid === $current_model ) echo 'class="aica-row-active"'; ?>>
                        <td>
                            <?php echo esc_html( $models[ $mid ] ?? $mid ); ?>
                            <?php if ( $mid === $current_model ) echo '<span class="aica-badge-active">Aktiv</span>'; ?>
                        </td>
                        <td>$<?php echo number_format( $p['input'], 2 ); ?></td>
                        <td>$<?php echo number_format( $p['output'], 2 ); ?></td>
                        <td>≈ $<?php echo number_format( $art_cost, 4 ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="aica-table-note">* Bei ca. 15.000 Tokens/Artikel (40% Input, 60% Output)</p>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function($) {
    'use strict';

    // Initiale Daten aus PHP
    var initialData = {
        labels:   <?php echo wp_json_encode( $chart_labels ); ?>,
        tokens:   <?php echo wp_json_encode( $chart_tokens ); ?>,
        costs:    <?php echo wp_json_encode( $chart_costs ); ?>,
        articles: <?php echo wp_json_encode( $chart_articles ); ?>
    };

    var activeDays  = 30;
    var activeModel = <?php echo wp_json_encode( $current_model ); ?>;

    // Farben aus CSS-Variablen
    var colorPrimary = '#6C5CE7';
    var colorCost    = '#00B894';
    var colorArticle = '#FDCB6E';

    // --- Haupt-Chart (Tokens + Kosten) ---
    var usageCtx = document.getElementById('aica-usage-chart').getContext('2d');
    var usageChart = new Chart(usageCtx, {
        data: {
            labels: initialData.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Tokens',
                    data: initialData.tokens,
                    backgroundColor: colorPrimary + '99',
                    borderColor: colorPrimary,
                    borderWidth: 1,
                    yAxisID: 'yTokens',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Kosten (USD)',
                    data: initialData.costs,
                    borderColor: colorCost,
                    backgroundColor: colorCost + '22',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.3,
                    yAxisID: 'yCost',
                    order: 1,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            if (ctx.dataset.label === 'Kosten (USD)') {
                                return ' Kosten: $' + ctx.parsed.y.toFixed(4);
                            }
                            return ' Tokens: ' + ctx.parsed.y.toLocaleString('de-DE');
                        }
                    }
                }
            },
            scales: {
                yTokens: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    title: { display: true, text: 'Tokens' },
                    ticks: {
                        callback: function(v) {
                            return v >= 1000 ? (v/1000).toFixed(0) + 'k' : v;
                        }
                    }
                },
                yCost: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    title: { display: true, text: 'Kosten (USD)' },
                    grid: { drawOnChartArea: false },
                    ticks: {
                        callback: function(v) { return '$' + v.toFixed(4); }
                    }
                }
            }
        }
    });

    // --- Artikel-Chart ---
    var artCtx = document.getElementById('aica-articles-chart').getContext('2d');
    var artChart = new Chart(artCtx, {
        type: 'bar',
        data: {
            labels: initialData.labels,
            datasets: [{
                label: 'Artikel',
                data: initialData.articles,
                backgroundColor: colorArticle + 'cc',
                borderColor: colorArticle,
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1, precision: 0 }
                }
            }
        }
    });

    // --- Filter-Logik ---
    function loadUsageData() {
        $.ajax({
            url: aicaData.ajaxUrl,
            method: 'GET',
            data: {
                action: 'aica_get_usage_data',
                nonce:  aicaData.nonce,
                days:   activeDays,
                model:  activeModel,
            },
            success: function(resp) {
                if (!resp.success) return;
                var d = resp.data;

                // Charts aktualisieren
                usageChart.data.labels                    = d.chart.labels;
                usageChart.data.datasets[0].data          = d.chart.tokens;
                usageChart.data.datasets[1].data          = d.chart.costs;
                usageChart.update();

                artChart.data.labels           = d.chart.labels;
                artChart.data.datasets[0].data = d.chart.articles;
                artChart.update();

                // Zusammenfassung aktualisieren
                document.getElementById('sum-tokens').textContent   =
                    d.summary.total_tokens.toLocaleString('de-DE');
                document.getElementById('sum-cost').textContent     =
                    d.summary.estimated_cost.toFixed(4);
                document.getElementById('sum-articles').textContent =
                    d.summary.total_articles;
            }
        });
    }

    // Perioden-Buttons
    document.querySelectorAll('.aica-period-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.aica-period-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            this.classList.add('active');
            activeDays = parseInt(this.dataset.days, 10);
            loadUsageData();
        });
    });

    // Modell-Selektor
    document.getElementById('aica-filter-model').addEventListener('change', function() {
        activeModel = this.value;
        loadUsageData();
    });

    // Initial den 30-Tage-Button markieren
    document.querySelectorAll('.aica-period-btn').forEach(function(btn) {
        btn.classList.remove('active');
        if (parseInt(btn.dataset.days, 10) === 30) {
            btn.classList.add('active');
        }
    });

})(jQuery);
</script>
