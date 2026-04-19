<?php
require_once __DIR__ . '/auth.php';
require_login_page();
$pageTitle = 'Stats';
?>
<!doctype html>
<html lang="en">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <main class="wrap">
        <section class="card">
            <div class="form-field">
                <label for="statsSessionSelect">Session</label>
                <select id="statsSessionSelect"></select>
            </div>
            <div id="statsMeta" class="muted" style="margin-top: 8px;">Loading…</div>
            <div class="form-actions">
                <button class="btn full-width" id="refreshBtn" type="button">
                    <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
                    Refresh
                </button>
            </div>
        </section>

        <section class="card">
            <p class="section-label">Player standings</p>
            <div id="statsList"></div>
        </section>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 4px;">
                <h2 style="margin: 0;">High scores</h2>
                <span id="highScoresMeta" class="muted-sm">Loading…</span>
            </div>
            <div id="highScoresList"></div>
        </section>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 4px;">
                <h2 style="margin: 0;">Low scores</h2>
                <span id="lowScoresMeta" class="muted-sm">Loading…</span>
            </div>
            <div id="lowScoresList"></div>
        </section>

        <section class="card">
            <h2>Wins over time</h2>
            <div id="winsChartMeta" class="muted-sm" style="margin-bottom: 12px;">Loading…</div>
            <div class="chart-wrap">
                <canvas id="winsChart"></canvas>
            </div>
        </section>

        <section class="card">
            <h2>Games per round</h2>
            <div id="gprChartMeta" class="muted-sm" style="margin-bottom: 12px;">Loading…</div>
            <div class="chart-wrap">
                <canvas id="gprChart"></canvas>
            </div>
        </section>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 4px;">
                <h2 style="margin: 0;" id="spgTitle">Score per game</h2>
                <div class="toggle-group">
                    <button class="toggle-btn active" id="spgModeGame" type="button">Per game</button>
                    <button class="toggle-btn" id="spgModeRound" type="button">Per round</button>
                </div>
            </div>
            <div id="spgChartMeta" class="muted-sm" style="margin-bottom: 12px;">Loading…</div>
            <div class="chart-wrap">
                <canvas id="spgChart"></canvas>
            </div>
        </section>

        <section class="card">
            <h2>Cumulative points per player</h2>
            <div id="cumChartMeta" class="muted-sm" style="margin-bottom: 12px;">Loading…</div>
            <div class="chart-wrap">
                <canvas id="cumChart"></canvas>
            </div>
        </section>

        <section class="card">
            <h2>Dealer impact</h2>
            <div id="dealerImpactMeta" class="muted-sm" style="margin-bottom: 4px;">Loading…</div>
            <div id="dealerImpactList"></div>
        </section>
    </main>

    <?php include __DIR__ . '/_nav.php'; ?>

    <script src="assets/utils.js?v=<?= filemtime(__DIR__.'/assets/utils.js') ?>"></script>
    <script src="assets/stats.js?v=<?= filemtime(__DIR__.'/assets/stats.js') ?>"></script>
</body>
</html>
