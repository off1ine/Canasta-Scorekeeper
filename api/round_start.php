<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();


$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($input['session_id'] ?? 0);
if ($sessionId <= 0) json_out(['error' => 'Missing session_id'], 400);

// session settings (target score)
$sess = $pdo->prepare("SELECT id, max_score_per_round FROM sessions WHERE id=?");
$sess->execute([$sessionId]);
$session = $sess->fetch();
if (!$session) json_out(['error' => 'Session not found'], 404);
$target = (int)$session['max_score_per_round'];

$pdo->beginTransaction();
try {
  // Find active round
  $roundStmt = $pdo->prepare("
    SELECT * FROM rounds
    WHERE session_id=? AND ended_at IS NULL
    ORDER BY round_number DESC
    LIMIT 1
  ");
  $roundStmt->execute([$sessionId]);
  $activeRound = $roundStmt->fetch();

  if ($activeRound) {
    $roundId = (int)$activeRound['id'];

    // Compute winner by highest total in that round
    $totalsStmt = $pdo->prepare("
      SELECT player_id, round_total
      FROM v_round_totals
      WHERE round_id=?
      ORDER BY round_total DESC
    ");
    $totalsStmt->execute([$roundId]);
    $rows = $totalsStmt->fetchAll();

    $winnerPid = null;
    if ($rows) {
      $top = (int)$rows[0]['round_total'];
      $topPlayers = array_values(array_filter($rows, fn($r) => (int)$r['round_total'] === $top));
      if (count($topPlayers) === 1) $winnerPid = (int)$topPlayers[0]['player_id'];
      // else tie => keep null
    }

    $pdo->prepare("UPDATE rounds SET ended_at=NOW(), winner_player_id=? WHERE id=?")
        ->execute([$winnerPid, $roundId]);
  }

  // Next round number
  $nextNoStmt = $pdo->prepare("SELECT COALESCE(MAX(round_number), 0) + 1 FROM rounds WHERE session_id=?");
  $nextNoStmt->execute([$sessionId]);
  $nextNo = (int)$nextNoStmt->fetchColumn();

  $ins = $pdo->prepare("INSERT INTO rounds (session_id, round_number, target_score) VALUES (?, ?, ?)");
  $ins->execute([$sessionId, $nextNo, $target]);
  $newRoundId = (int)$pdo->lastInsertId();

  $pdo->prepare("UPDATE sessions SET last_activity_at=NOW() WHERE id=?")->execute([$sessionId]);

  $pdo->commit();
  json_out(['ok' => true, 'round_id' => $newRoundId, 'round_number' => $nextNo]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_out(['error' => $e->getMessage()], 500);
}
