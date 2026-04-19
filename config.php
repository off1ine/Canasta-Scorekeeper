<?php
declare(strict_types=1);

// Optional local config overrides
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

const APP_SESSION_NAME = 'scorekeeper_session';
