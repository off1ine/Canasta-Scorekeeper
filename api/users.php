<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_login_api();
require_admin_api();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

function pin_hash_or_null($pin): ?string {
  $pin = trim((string)$pin);
  if ($pin === '') return null;
  if (!validate_pin($pin)) return null;
  return password_hash($pin, PASSWORD_DEFAULT);
}

if ($method === 'GET') {
  $rows = $pdo->query("SELECT id, username, is_admin, is_active, created_at, last_login_at
                       FROM users ORDER BY username ASC")->fetchAll();
  json_out(['users' => $rows]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
  $id = (int)($input['id'] ?? 0);
  $username = trim((string)($input['username'] ?? ''));
  $pin = (string)($input['pin'] ?? '');
  $is_admin = (int)($input['is_admin'] ?? 0) ? 1 : 0;
  $is_active = (int)($input['is_active'] ?? 1) ? 1 : 0;

  if ($username === '') json_out(['error' => 'Username required'], 400);

  if ($id > 0) {
    // update
    $fields = ["username=?","is_admin=?","is_active=?"];
    $params = [$username, $is_admin, $is_active];

    $hashed = pin_hash_or_null($pin);
    if ($pin !== '' && $hashed === null) json_out(['error' => 'PIN must be exactly 4 digits.'], 400);
    if ($hashed !== null) { $fields[] = "pin_hash=?"; $params[] = $hashed; }

    $params[] = $id;

    $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id=?";
    $pdo->prepare($sql)->execute($params);
    json_out(['ok' => true]);
  } else {
    // create
    if (!validate_pin($pin)) json_out(['error' => 'PIN must be exactly 4 digits.'], 400);

    $stmt = $pdo->prepare("INSERT INTO users (username, pin_hash, is_admin, is_active) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, password_hash($pin, PASSWORD_DEFAULT), $is_admin, $is_active]);
    json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
  }
}

if ($method === 'DELETE') {
  $id = (int)($input['id'] ?? 0);
  if ($id <= 0) json_out(['error' => 'Missing id'], 400);

  // Prevent deactivating yourself
  $u = current_user();
  if ($u && (int)$u['id'] === $id) json_out(['error' => 'You cannot deactivate your own account.'], 400);

  $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
  json_out(['ok' => true]);
}

json_out(['error' => 'Method not allowed'], 405);
