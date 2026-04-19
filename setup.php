<?php
require_once __DIR__ . '/auth.php';
require_login_page();
require_admin_page();
$pageTitle = t('Setup');
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
            <h2><?= htmlspecialchars(t('Sessions'), ENT_QUOTES, 'UTF-8') ?></h2>

            <div class="form-field">
                <label for="sessionSearch"><?= htmlspecialchars(t('Find session'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="sessionSearch" type="search" placeholder="<?= htmlspecialchars(t('Search by name…'), ENT_QUOTES, 'UTF-8') ?>" />
            </div>

            <label class="checkbox-label" style="margin-top: 12px;">
                <input type="checkbox" id="showArchivedSessions" />
                <?= htmlspecialchars(t('Show archived'), ENT_QUOTES, 'UTF-8') ?>
            </label>

            <div class="form-actions">
                <button class="btn full-width" id="newSessionModeBtn" type="button"><?= htmlspecialchars(t('+ New session'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>

            <div id="sessionsStatus" class="muted" style="margin-top: 12px;"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="sessionsList" class="admin-list"></div>

            <h3 id="sessionEditorTitle" style="margin-top: 20px;"><?= htmlspecialchars(t('Create new session'), ENT_QUOTES, 'UTF-8') ?></h3>
            <input type="hidden" id="editSessionId" value="" />

            <div class="grid2">
                <div>
                    <label for="editSessName"><?= htmlspecialchars(t('Session name'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="editSessName" placeholder="<?= htmlspecialchars(t('e.g. Christmas 2025'), ENT_QUOTES, 'UTF-8') ?>" />
                </div>
                <div>
                    <label for="editMaxScore"><?= htmlspecialchars(t('Max score per round'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="editMaxScore" type="number" inputmode="numeric" pattern="[0-9]*" step="1" min="1" value="5000" />
                </div>
            </div>

            <div id="gameTypeBlock" class="form-field" style="margin-top: 14px;">
                <label><?= htmlspecialchars(t('Game type'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="pill-group" id="gameTypeGroup" data-selected="canasta">
                    <button type="button" class="pill-option is-active" data-value="canasta"><?= htmlspecialchars(t('Canasta'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" class="pill-option" data-value="romme"><?= htmlspecialchars(t('Rommé'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div id="gameTypeLocked" class="form-helper" hidden><?= htmlspecialchars(t('Game type cannot be changed after the session is created.'), ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div id="editMeldMinBlock" class="form-field" hidden>
                <label for="editMeldMin"><?= htmlspecialchars(t('Meld minimum (Erstmeldung)'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="editMeldMin" type="number" inputmode="numeric" pattern="[0-9]*" step="1" min="1" value="30" />
            </div>

            <div id="createPlayersBlock" class="form-field">
                <label for="playerNames"><?= htmlspecialchars(t('Players (one per line)'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea id="playerNames" rows="5" placeholder="Alice&#10;Bob&#10;Charlie"></textarea>
            </div>

            <div id="editPropagateBlock" class="form-field" hidden>
                <label for="editPropagate"><?= htmlspecialchars(t('Apply max score change to rounds'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="editPropagate">
                    <option value="none"><?= htmlspecialchars(t('Only future rounds (recommended)'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="active"><?= htmlspecialchars(t('Also update current active round'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="all"><?= htmlspecialchars(t('Update all rounds in this session (rewrites history)'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>

            <div id="sessionEditStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveSessionBtn" type="button"><?= htmlspecialchars(t('Create session'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn full-width" id="clearSessionEditBtn" type="button"><?= htmlspecialchars(t('Clear'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </section>

        <section class="card">
            <h2><?= htmlspecialchars(t('Meld minimum thresholds'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="muted" style="margin-bottom: 8px;"><?= htmlspecialchars(t('Score range → required meld minimum for first meld.'), ENT_QUOTES, 'UTF-8') ?></p>
            <div id="thresholds" class="admin-list"></div>

            <h3 style="margin-top: 20px;"><?= htmlspecialchars(t('Add / update'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="grid3">
                <div>
                    <label for="tFrom"><?= htmlspecialchars(t('Score from'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="tFrom" type="number" placeholder="<?= htmlspecialchars(t('blank = -∞'), ENT_QUOTES, 'UTF-8') ?>" />
                </div>
                <div>
                    <label for="tTo"><?= htmlspecialchars(t('Score to'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="tTo" type="number" placeholder="<?= htmlspecialchars(t('blank = ∞'), ENT_QUOTES, 'UTF-8') ?>" />
                </div>
                <div>
                    <label for="tMeld"><?= htmlspecialchars(t('Meld minimum'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input id="tMeld" type="number" value="50" min="1" />
                </div>
            </div>
            <input type="hidden" id="tId" value="" />
            <div id="tStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveThresholdBtn" type="button"><?= htmlspecialchars(t('Save threshold'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn full-width" id="clearThresholdBtn" type="button"><?= htmlspecialchars(t('Clear'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </section>

        <section class="card">
            <h2><?= htmlspecialchars(t('Users'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div id="usersStatus" class="muted"><?= htmlspecialchars(t('Loading…'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="usersList" class="admin-list"></div>

            <h3 style="margin-top: 20px;"><?= htmlspecialchars(t('Add / update user'), ENT_QUOTES, 'UTF-8') ?></h3>
            <input type="hidden" id="userId" value="" />

            <div class="form-field">
                <label for="userName"><?= htmlspecialchars(t('Username'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="userName" />
            </div>

            <div class="form-field">
                <label for="userPin"><?= htmlspecialchars(t('PIN (4 digits) — leave empty to keep unchanged'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="userPin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" />
            </div>

            <label class="checkbox-label" style="margin-top: 12px;">
                <input type="checkbox" id="userIsAdmin" />
                <?= htmlspecialchars(t('Admin'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <label class="checkbox-label">
                <input type="checkbox" id="userIsActive" checked />
                <?= htmlspecialchars(t('Active'), ENT_QUOTES, 'UTF-8') ?>
            </label>

            <div id="userEditStatus" class="form-helper"></div>
            <div class="edit-actions">
                <button class="btn primary full-width" id="saveUserBtn" type="button"><?= htmlspecialchars(t('Save user'), ENT_QUOTES, 'UTF-8') ?></button>
                <button class="btn full-width" id="clearUserBtn" type="button"><?= htmlspecialchars(t('Clear'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/_nav.php'; ?>

    <script src="assets/utils.js?v=<?= filemtime(__DIR__.'/assets/utils.js') ?>"></script>
    <script src="assets/setup.js?v=<?= filemtime(__DIR__.'/assets/setup.js') ?>"></script>
</body>
</html>
