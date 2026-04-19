<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

require_login_api();
require_admin_api();

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($input['session_id'] ?? 0);
$archived = (int)($input['archived'] ?? 1) ? 1 : 0; // 1=archive, 0=restore

if ($sessionId <= 0) json_out(['error' => 'Invalid session_id'], 400);

// Safety: prevent archiving a session that currently has an active round? (optional)
// If you WANT to allow it anyway, remove this check.
// $chk = $pdo->prepare("SELECT 1 FROM rounds WHERE session_id=? AND ended_at IS NULL LIMIT 1");
// $chk->execute([$sessionId]);
// if ($archived === 1 && $chk->fetchColumn()) json_out(['error' => 'End the active round or restore later.'], 400);

if ($archived === 1) {
  $pdo->prepare("UPDATE sessions SET archived_at = COALESCE(archived_at, NOW()) WHERE id=?")
      ->execute([$sessionId]);
} else {
  $pdo->prepare("UPDATE sessions SET archived_at = NULL WHERE id=?")
      ->execute([$sessionId]);
}

json_out(['ok' => true]);
