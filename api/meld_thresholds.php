<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();


$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
  $rows = $pdo->query("SELECT id, score_from, score_to, meld_minimum
                       FROM meld_thresholds
                       ORDER BY score_from ASC")->fetchAll();
  json_out(['thresholds' => $rows]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
  $id = (int)($input['id'] ?? 0);
  $from = $input['score_from'] === null || $input['score_from'] === '' ? null : (int)$input['score_from'];
  $to = $input['score_to'] === null || $input['score_to'] === '' ? null : (int)$input['score_to'];
  $meld = (int)($input['meld_minimum'] ?? 0);

  if ($meld <= 0) json_out(['error' => t('Meld minimum must be > 0.')], 400);
  if ($from !== null && $to !== null && $to < $from) json_out(['error' => t('Score to must be >= score from.')], 400);

  if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE meld_thresholds
                           SET score_from=?, score_to=?, meld_minimum=?
                           WHERE id=?");
    $stmt->execute([$from, $to, $meld, $id]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO meld_thresholds (score_from, score_to, meld_minimum)
                           VALUES (?, ?, ?)");
    $stmt->execute([$from, $to, $meld]);
  }
  json_out(['ok' => true]);
}

if ($method === 'DELETE') {
  $id = (int)($input['id'] ?? 0);
  if ($id <= 0) json_out(['error' => t('Missing id.')], 400);
  $pdo->prepare("DELETE FROM meld_thresholds WHERE id=?")->execute([$id]);
  json_out(['ok' => true]);
}

json_out(['error' => t('Method not allowed.')], 405);
