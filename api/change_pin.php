<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_login_api();
$pdo = db();
$u = current_user();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$old = trim((string)($input['old_pin'] ?? ''));
$new = trim((string)($input['new_pin'] ?? ''));

if (!validate_pin($old) || !validate_pin($new)) {
  json_out(['error' => 'PIN must be exactly 4 digits.'], 400);
}
if ($old === $new) {
  json_out(['error' => 'New PIN must be different.'], 400);
}

$stmt = $pdo->prepare("SELECT pin_hash FROM users WHERE id=? AND is_active=1");
$stmt->execute([(int)$u['id']]);
$row = $stmt->fetch();
if (!$row) json_out(['error' => 'User not found or inactive'], 404);

if (!password_verify($old, (string)$row['pin_hash'])) {
  json_out(['error' => 'Current PIN is incorrect.'], 400);
}

$pdo->prepare("UPDATE users SET pin_hash=? WHERE id=?")
    ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]);

json_out(['ok' => true]);
