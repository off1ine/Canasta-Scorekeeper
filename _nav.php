<?php
/**
 * Fixed bottom tab bar. Include on authenticated pages after auth.php.
 */
$_navUser = current_user();
$_navIsAdmin = $_navUser && $_navUser['is_admin'];
$_navCurrent = basename($_SERVER['SCRIPT_NAME']);

function _nav_active(string $page, string $current): string {
    return $page === $current ? 'active' : '';
}
?>
<nav class="bottom-nav" aria-label="Primary">
    <a href="index.php" class="<?= _nav_active('index.php', $_navCurrent) ?>">
        <span class="material-symbols-outlined" aria-hidden="true">dashboard</span>
        <span>Overview</span>
    </a>
    <a href="stats.php" class="<?= _nav_active('stats.php', $_navCurrent) ?>">
        <span class="material-symbols-outlined" aria-hidden="true">bar_chart</span>
        <span>Stats</span>
    </a>
    <?php if ($_navIsAdmin): ?>
    <a href="setup.php" class="<?= _nav_active('setup.php', $_navCurrent) ?>">
        <span class="material-symbols-outlined" aria-hidden="true">settings</span>
        <span>Setup</span>
    </a>
    <?php endif; ?>
    <a href="account.php" class="<?= _nav_active('account.php', $_navCurrent) ?>">
        <span class="material-symbols-outlined" aria-hidden="true">person</span>
        <span>Account</span>
    </a>
</nav>
