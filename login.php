<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

start_app_session();
$pdo = db();

$next = $_GET['next'] ?? '/index.php';
$error = '';

$usersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$isBootstrap = ($usersCount === 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $pin = trim((string)($_POST['pin'] ?? ''));

    if ($username === '' || !validate_pin($pin)) {
        $error = 'Enter a username and a 4-digit PIN.';
    } else {
        if ($isBootstrap) {
            $stmt = $pdo->prepare("INSERT INTO users (username, pin_hash, is_admin, is_active) VALUES (?, ?, 1, 1)");
            $stmt->execute([$username, password_hash($pin, PASSWORD_DEFAULT)]);
            $userId = (int)$pdo->lastInsertId();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = true;

            header('Location: ' . (is_string($next) && $next !== '' && !str_starts_with($next, 'http') ? $next : '/index.php'));
            exit;
        } else {
            $stmt = $pdo->prepare("SELECT id, username, pin_hash, is_admin, is_active FROM users WHERE username=? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !(int)$user['is_active'] || !password_verify($pin, (string)$user['pin_hash'])) {
                $error = 'Invalid username or PIN.';
            } else {
                $pdo->prepare("UPDATE users SET last_login_at=NOW() WHERE id=?")->execute([(int)$user['id']]);

                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = (string)$user['username'];
                $_SESSION['is_admin'] = ((int)$user['is_admin'] === 1);

                header('Location: ' . (is_string($next) && $next !== '' && !str_starts_with($next, 'http') ? $next : '/index.php'));
                exit;
            }
        }
    }
}

$pageTitle = $isBootstrap ? 'Create admin account' : 'Sign in';
?>
<!doctype html>
<html lang="<?= htmlspecialchars(current_locale(), ENT_QUOTES, "UTF-8") ?>">
<head>
    <?php include __DIR__ . '/_head.php'; ?>
</head>
<body class="no-bottom-nav">
    <main class="wrap wrap-narrow wrap-center">
        <div class="auth-mark">
            <div class="wordmark">SCOREKEEPER</div>
        </div>

        <section class="card">
            <h2 class="auth-heading">
                <?= $isBootstrap ? 'Create admin account' : 'Welcome back' ?>
            </h2>
            <p class="auth-subheading">
                <?= $isBootstrap ? 'Set up the first admin for your scorekeeper.' : 'Sign in to continue scoring.' ?>
            </p>

            <?php if ($error): ?>
                <div class="form-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" action="login.php?next=<?= urlencode((string)$next) ?>">
                <div class="form-field">
                    <label for="username">Username</label>
                    <input id="username" name="username" autocomplete="username" required />
                </div>

                <div class="form-field">
                    <label for="pin">4-digit PIN</label>
                    <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="current-password" required />
                </div>

                <div class="form-actions">
                    <button class="btn primary full-width" type="submit">
                        <?= $isBootstrap ? 'Create admin' : 'Sign in' ?>
                    </button>
                </div>
            </form>

            <div class="form-helper">
                PINs are stored hashed. Use HTTPS so logins are protected.
            </div>
        </section>
    </main>
</body>
</html>
