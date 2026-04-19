<?php
require_once __DIR__ . '/auth.php';
require_login_page();
require_admin_page();
$pageTitle = 'Setup';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_locale(), ENT_QUOTES, "UTF-8") ?>">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <main class="wrap">
        <section class="card">
            <h2>Sessions</h2>

            <div class="form-field">
                <label for="sessionSearch">Find session</label>
                <input id="sessionSearch" type="search" placeholder="Search by name…" />
            </div>

            <label class="checkbox-label" style="margin-top: 12px;">
                <input type="checkbox" id="showArchivedSessions" />
                Show archived
            </label>

            <div class="form-actions">
                <button class="btn full-width" id="newSessionModeBtn" type="button">+ New session</button>
            </div>

            <div id="sessionsStatus" class="muted" style="margin-top: 12px;">Loading…</div>
            <div id="sessionsList" class="admin-list"></div>

            <h3 id="sessionEditorTitle" style="margin-top: 20px;">Create new session</h3>
            <input type="hidden" id="editSessionId" value="" />

            <div class="grid2">
                <div>
                    <label for="editSessName">Session name</label>
                    <input id="editSessName" placeholder="e.g. Christmas 2025" />
                </div>
                <div>
                    <label for="editMaxScore">Max score per round</label>
                    <input id="editMaxScore" type="number" inputmode="numeric" pattern="[0-9]*" step="1" min="1" value="5000" />
                </div>
            </div>

            <div id="createPlayersBlock" class="form-field">
                <label for="playerNames">Players (one per line)</label>
                <textarea id="playerNames" rows="5" placeholder="Alice&#10;Bob&#10;Charlie"></textarea>
            </div>

            <div id="editPropagateBlock" class="form-field" hidden>
                <label for="editPropagate">Apply max score change to rounds</label>
                <select id="editPropagate">
                    <option value="none">Only future rounds (recommended)</option>
                    <option value="active">Also update current active round</option>
                    <option value="all">Update all rounds in this session (rewrites history)</option>
                </select>
            </div>

            <div id="sessionEditStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveSessionBtn" type="button">Create session</button>
                <button class="btn full-width" id="clearSessionEditBtn" type="button">Clear</button>
            </div>
        </section>

        <section class="card">
            <h2>Meld minimum thresholds</h2>
            <p class="muted" style="margin-bottom: 8px;">Score range → required meld minimum for first meld.</p>
            <div id="thresholds" class="admin-list"></div>

            <h3 style="margin-top: 20px;">Add / update</h3>
            <div class="grid3">
                <div>
                    <label for="tFrom">Score from</label>
                    <input id="tFrom" type="number" value="0" />
                </div>
                <div>
                    <label for="tTo">Score to</label>
                    <input id="tTo" type="number" placeholder="blank = ∞" />
                </div>
                <div>
                    <label for="tMeld">Meld minimum</label>
                    <input id="tMeld" type="number" value="50" min="1" />
                </div>
            </div>
            <input type="hidden" id="tId" value="" />
            <div id="tStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveThresholdBtn" type="button">Save threshold</button>
                <button class="btn full-width" id="clearThresholdBtn" type="button">Clear</button>
            </div>
        </section>

        <section class="card">
            <h2>Users</h2>
            <div id="usersStatus" class="muted">Loading…</div>
            <div id="usersList" class="admin-list"></div>

            <h3 style="margin-top: 20px;">Add / update user</h3>
            <input type="hidden" id="userId" value="" />

            <div class="form-field">
                <label for="userName">Username</label>
                <input id="userName" />
            </div>

            <div class="form-field">
                <label for="userPin">PIN (4 digits) — leave empty to keep unchanged</label>
                <input id="userPin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" />
            </div>

            <label class="checkbox-label" style="margin-top: 12px;">
                <input type="checkbox" id="userIsAdmin" />
                Admin
            </label>
            <label class="checkbox-label">
                <input type="checkbox" id="userIsActive" checked />
                Active
            </label>

            <div id="userEditStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveUserBtn" type="button">Save user</button>
                <button class="btn full-width" id="clearUserBtn" type="button">Clear</button>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/_nav.php'; ?>

    <script src="assets/utils.js?v=<?= filemtime(__DIR__.'/assets/utils.js') ?>"></script>
    <script src="assets/setup.js?v=<?= filemtime(__DIR__.'/assets/setup.js') ?>"></script>
</body>
</html>
