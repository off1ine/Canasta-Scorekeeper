<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

require_login_api();
require_admin_api();

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => t('Method not allowed.')], 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($input['session_id'] ?? 0);
$name = trim((string)($input['name'] ?? ''));
$newMax = (int)($input['max_score_per_round'] ?? 0);
$meldMinInput = array_key_exists('meld_minimum', $input) ? $input['meld_minimum'] : null;

// propagation mode: "none" | "active" | "all"
$prop = (string)($input['propagate'] ?? 'none');

if ($sessionId <= 0) json_out(['error' => t('Invalid session id.')], 400);
if ($name === '') json_out(['error' => t('Session name required.')], 400);
if ($newMax <= 0) json_out(['error' => t('Max score must be > 0.')], 400);
if (!in_array($prop, ['none','active','all'], true)) json_out(['error' => t('Invalid propagate mode.')], 400);

// Fetch current session to enforce game_type immutability and scope meld_minimum edits.
$cur = $pdo->prepare("SELECT game_type FROM sessions WHERE id=?");
$cur->execute([$sessionId]);
$curGameType = $cur->fetchColumn();
if ($curGameType === false) json_out(['error' => t('Session not found.')], 404);

$meldMin = null;
$updateMeld = false;
if ($curGameType === 'romme' && $meldMinInput !== null && $meldMinInput !== '') {
  $meldMin = (int)$meldMinInput;
  if ($meldMin <= 0) json_out(['error' => t('Meld minimum must be > 0.')], 400);
  $updateMeld = true;
}

$pdo->beginTransaction();
try {
  // update session fields (game_type is immutable after creation)
  if ($updateMeld) {
    $pdo->prepare("UPDATE sessions SET name=?, max_score_per_round=?, meld_minimum=? WHERE id=?")
        ->execute([$name, $newMax, $meldMin, $sessionId]);
  } else {
    $pdo->prepare("UPDATE sessions SET name=?, max_score_per_round=? WHERE id=?")
        ->execute([$name, $newMax, $sessionId]);
  }

  if ($prop === 'active') {
    // update only current active round (if any)
    $r = $pdo->prepare("
      SELECT id
      FROM rounds
      WHERE session_id=? AND ended_at IS NULL
      ORDER BY round_number DESC
      LIMIT 1
    ");
    $r->execute([$sessionId]);
    $roundId = $r->fetchColumn();
    if ($roundId) {
      $roundId = (int)$roundId;
      $pdo->prepare("UPDATE rounds SET target_score=? WHERE id=?")->execute([$newMax, $roundId]);

      // if new target already reached -> end it now
      if (roundMaxTotal($pdo, $roundId) >= $newMax) {
        $winnerPid = recomputeRoundWinner($pdo, $roundId);
        $pdo->prepare("UPDATE rounds SET ended_at=COALESCE(ended_at, NOW()), winner_player_id=? WHERE id=?")
            ->execute([$winnerPid, $roundId]);
      }
    }
  }

  if ($prop === 'all') {
    // update ALL rounds target_score (historical rewrite - explicit)
    $pdo->prepare("UPDATE rounds SET target_score=? WHERE session_id=?")->execute([$newMax, $sessionId]);

    // Re-evaluate ended state for all rounds (ended/unended) to stay consistent
    $rounds = $pdo->prepare("SELECT id, ended_at FROM rounds WHERE session_id=?");
    $rounds->execute([$sessionId]);
    foreach ($rounds->fetchAll() as $rr) {
      $rid = (int)$rr['id'];
      $maxTotal = roundMaxTotal($pdo, $rid);

      if ($maxTotal >= $newMax) {
        $winnerPid = recomputeRoundWinner($pdo, $rid);
        $pdo->prepare("UPDATE rounds SET ended_at=COALESCE(ended_at, NOW()), winner_player_id=? WHERE id=?")
            ->execute([$winnerPid, $rid]);
      } else {
        // If you don't want to "un-end" historical rounds, remove this block.
        $pdo->prepare("UPDATE rounds SET ended_at=NULL, winner_player_id=NULL WHERE id=?")
            ->execute([$rid]);
      }
    }
  }

  $pdo->commit();
  json_out(['ok' => true]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_out(['error' => $e->getMessage()], 500);
}
