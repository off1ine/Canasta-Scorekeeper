<?php
require_once __DIR__ . '/auth.php';
require_login_page();
$pageTitle = t('Stats');
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_locale(), ENT_QUOTES, "UTF-8") ?>">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <main class="wrap">
        <section class="card">
            <div class="form-field">
                <label for="statsSessionSelect"><?= htmlspecialchars(t('Session'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="statsSessionSelect"></select>
            </div>
            <div id="statsMeta" class="muted" style="margin-top: 8px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="form-actions">
                <button class="btn full-width" id="refreshBtn" type="button">
                    <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
                    <?= htmlspecialchars(t('Refresh'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </section>

        <section class="card">
            <p class="section-label"><?= htmlspecialchars(t('Player standings'), ENT_QUOTES, 'UTF-8') ?></p>
            <div id="statsList"></div>
        </section>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 4px;">
                <h2 style="margin: 0;"><?= htmlspecialchars(t('High scores'), ENT_QUOTES, 'UTF-8') ?></h2>
                <span id="highScoresMeta" class="muted-sm"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div id="highScoresList"></div>
        </section>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 4px;">
                <h2 style="margin: 0;"><?= htmlspecialchars(t('Low scores'), ENT_QUOTES, 'UTF-8') ?></h2>
                <span id="lowScoresMeta" class="muted-sm"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div id="lowScoresList"></div>
        </section>

        <section class="card">
            <h2><?= htmlspecialchars(t('Wins over time'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="winsChartMeta" class="muted-sm" style="margin-bottom: 12px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="chart-wrap">
                <canvas id="winsChart"></canvas>
            </div>
        </section>

        <section class="card">
            <h2><?= htmlspecialchars(t('Games per round'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="gprChartMeta" class="muted-sm" style="margin-bottom: 12px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="chart-wrap">
                <canvas id="gprChart"></canvas>
            </div>
        </section>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 4px;">
                <h2 style="margin: 0;" id="spgTitle"><?= htmlspecialchars(t('Score per game'), ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="toggle-group">
                    <button class="toggle-btn active" id="spgModeGame" type="button"><?= htmlspecialchars(t('Per game'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button class="toggle-btn" id="spgModeRound" type="button"><?= htmlspecialchars(t('Per round'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </div>
            <div id="spgChartMeta" class="muted-sm" style="margin-bottom: 12px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="chart-wrap">
                <canvas id="spgChart"></canvas>
            </div>
        </section>

        <section class="card">
            <h2><?= htmlspecialchars(t('Cumulative points per player'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="cumChartMeta" class="muted-sm" style="margin-bottom: 12px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="chart-wrap">
                <canvas id="cumChart"></canvas>
            </div>
        </section>

        <section class="card">
            <h2><?= htmlspecialchars(t('Dealer impact'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="dealerImpactMeta" class="muted-sm" style="margin-bottom: 4px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="dealerImpactList"></div>
        </section>
    </main>

    <?php include __DIR__ . '/_nav.php'; ?>

    <script src="assets/utils.js?v=<?= filemtime(__DIR__.'/assets/utils.js') ?>"></script>
    <script src="assets/stats.js?v=<?= filemtime(__DIR__.'/assets/stats.js') ?>"></script>
</body>
</html>
