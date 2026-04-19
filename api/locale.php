<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

start_app_session();

$input = json_decode((string)file_get_contents('php://input'), true) ?? [];
$locale = (string)($input['locale'] ?? '');

if (set_locale($locale)) {
    json_out(['ok' => true, 'locale' => $locale]);
}
json_out(['error' => t('Unsupported locale.')], 400);
