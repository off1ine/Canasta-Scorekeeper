<?php
require_once __DIR__ . '/auth.php';
require_login_page();
$pageTitle = 'Overview';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_locale(), ENT_QUOTES, "UTF-8") ?>">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <main class="wrap">
        <details class="card is-collapsible">
            <summary>
                <div class="collapsible-summary-main">
                    <span id="summaryTitle" class="collapsible-summary-title">Loading…</span>
                    <span id="summaryMeta" class="muted-sm"></span>
                </div>
                <span class="material-symbols-outlined collapsible-chevron" aria-hidden="true">expand_more</span>
            </summary>
            <div class="collapsible-body">
                <div class="flex-between">
                    <label for="sessionSelect" style="margin-bottom: 0;">Session</label>
                    <button class="btn btn-link btn-sm" id="startRoundBtn" type="button">Start new round</button>
                </div>
                <select id="sessionSelect" class="pill-select" style="margin-top: 6px;"></select>

                <label for="roundSelect" style="margin-top: 14px;">Round</label>
                <select id="roundSelect" class="pill-select"></select>

                <div id="sessionMeta" class="muted" style="margin-top: 12px;"></div>
            </div>
        </details>

        <section class="card">
            <div class="flex-between" style="margin-bottom: 14px;">
                <p class="section-label" style="margin: 0;">Live scoreboard</p>
                <span id="roundTitle" class="muted"></span>
            </div>
            <div id="scoreboard" class="sb-list"></div>
        </section>

        <section class="card">
            <h2>Add game</h2>
            <div id="addGameInputs" class="ag-list"></div>

            <div class="form-field" style="margin-top: 16px;">
                <label>Game winner</label>
                <div id="winnerSelect" class="pill-group" data-selected=""></div>
            </div>
            <div class="form-field">
                <label>Dealer</label>
                <div id="dealerSelect" class="pill-group" data-selected=""></div>
            </div>

            <div id="saveStatus" class="form-helper"></div>
            <div class="sticky-action">
                <button class="btn primary full-width" id="saveGameBtn" type="button">+ Add game</button>
            </div>
        </section>

        <section class="card">
            <h2>Games this round</h2>
            <div id="gamesList"></div>
        </section>

        <section class="card" id="editCard" hidden>
            <h2>Edit game <span id="editGameTitle"></span></h2>

            <div class="form-field">
                <label>Winner</label>
                <div id="editWinnerSelect" class="pill-group" data-selected=""></div>
            </div>
            <div class="form-field">
                <label>Dealer</label>
                <div id="editDealerSelect" class="pill-group" data-selected=""></div>
            </div>

            <div id="editScoresInputs" class="ag-list" style="margin-top: 16px;"></div>
            <div id="editStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveEditBtn" type="button">Save changes</button>
                <button class="btn full-width" id="cancelEditBtn" type="button">Cancel</button>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/_nav.php'; ?>

    <script src="assets/utils.js?v=<?= filemtime(__DIR__.'/assets/utils.js') ?>"></script>
    <script src="assets/overview.js?v=<?= filemtime(__DIR__.'/assets/overview.js') ?>"></script>
</body>
</html>
