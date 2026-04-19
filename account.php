<?php
require_once __DIR__ . '/auth.php';
require_login_page();
$u = current_user();
$initials = strtoupper(mb_substr($u['username'], 0, 2));
$pageTitle = t('Account');
$locale = current_locale();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/_header.php'; ?>

    <main class="wrap wrap-narrow">
        <section class="card">
            <div class="account-identity">
                <div class="avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="account-username">
                    <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php if ($u['is_admin']): ?>
                    <span class="chip chip-indigo"><?= htmlspecialchars(t('Admin'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>

            <p class="section-label" style="margin-top:20px;"><?= htmlspecialchars(t('Change PIN'), ENT_QUOTES, 'UTF-8') ?></p>

            <div class="form-field">
                <label for="oldPin"><?= htmlspecialchars(t('Current PIN'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="oldPin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="current-password" />
            </div>

            <div class="form-field">
                <label for="newPin"><?= htmlspecialchars(t('New PIN (4 digits)'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="newPin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="new-password" />
            </div>

            <div class="form-field">
                <label for="newPin2"><?= htmlspecialchars(t('Repeat new PIN'), ENT_QUOTES, 'UTF-8') ?></label>
                <input id="newPin2" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="new-password" />
            </div>

            <div id="pinStatus" class="form-helper"></div>

            <div class="form-actions">
                <button class="btn primary full-width" id="changePinBtn" type="button"><?= htmlspecialchars(t('Update PIN'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>

            <div class="form-helper">
                <?= htmlspecialchars(t('PINs are stored hashed. Use HTTPS so logins are protected.'), ENT_QUOTES, 'UTF-8') ?>
            </div>
        </section>

        <section class="card">
            <p class="section-label"><?= htmlspecialchars(t('Language'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="form-field">
                <label for="localeSelect"><?= htmlspecialchars(t('Interface language'), ENT_QUOTES, 'UTF-8') ?></label>
                <select id="localeSelect">
                    <option value="en"<?= $locale === 'en' ? ' selected' : '' ?>>English</option>
                    <option value="de"<?= $locale === 'de' ? ' selected' : '' ?>>Deutsch</option>
                </select>
            </div>
            <div id="localeStatus" class="form-helper"></div>
        </section>

        <section class="card">
            <a class="btn danger full-width" href="logout.php">
                <span class="material-symbols-outlined" aria-hidden="true">logout</span>
                <?= htmlspecialchars(t('Log out'), ENT_QUOTES, 'UTF-8') ?>
            </a>
        </section>
    </main>

    <?php include __DIR__ . '/_nav.php'; ?>

    <script src="assets/utils.js?v=<?= filemtime(__DIR__.'/assets/utils.js') ?>"></script>
    <script>
        function isPin(s) { return /^\d{4}$/.test(s); }

        document.getElementById("changePinBtn").addEventListener("click", async () => {
            const status = document.getElementById("pinStatus");
            status.textContent = "";
            status.classList.remove("form-error");

            const oldPin = document.getElementById("oldPin").value.trim();
            const newPin = document.getElementById("newPin").value.trim();
            const newPin2 = document.getElementById("newPin2").value.trim();

            function fail(msg) {
                status.textContent = msg;
                status.classList.add("form-error");
            }

            if (!isPin(oldPin) || !isPin(newPin) || !isPin(newPin2)) return fail(t("PINs must be exactly 4 digits."));
            if (newPin !== newPin2) return fail(t("New PINs do not match."));
            if (oldPin === newPin) return fail(t("New PIN must be different."));

            try {
                await api("api/change_pin.php", {
                    method: "POST",
                    body: JSON.stringify({ old_pin: oldPin, new_pin: newPin })
                });
                status.textContent = t("PIN updated.");
                document.getElementById("oldPin").value = "";
                document.getElementById("newPin").value = "";
                document.getElementById("newPin2").value = "";
            } catch (e) {
                fail(e.message);
            }
        });

        document.getElementById("localeSelect").addEventListener("change", async (e) => {
            const status = document.getElementById("localeStatus");
            status.textContent = "";
            status.classList.remove("form-error");
            try {
                await api("api/locale.php", {
                    method: "POST",
                    body: JSON.stringify({ locale: e.target.value })
                });
                window.location.reload();
            } catch (err) {
                status.textContent = err.message;
                status.classList.add("form-error");
            }
        });
    </script>
</body>
</html>
