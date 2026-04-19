<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_login_api();

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$sessionId = (int)($input['session_id'] ?? 0);
$scoresIn  = $input['scores'] ?? null;
$winnerPid = array_key_exists('winner_player_id', $input) && $input['winner_player_id'] !== null
  ? (int)$input['winner_player_id'] : null;
$dealerPid = array_key_exists('dealer_player_id', $input) && $input['dealer_player_id'] !== null
  ? (int)$input['dealer_player_id'] : null;

if ($sessionId <= 0 || !is_array($scoresIn) || count($scoresIn) < 2) {
  json_out(['error' => 'Invalid input'], 400);
}

// active round only
$roundStmt = $pdo->prepare("
  SELECT * FROM rounds
  WHERE session_id=? AND ended_at IS NULL
  ORDER BY round_number DESC
  LIMIT 1
");
$roundStmt->execute([$sessionId]);
$round = $roundStmt->fetch();
if (!$round) json_out(['error' => 'No active round (round already ended or not started).'], 400);

$roundId = (int)$round['id'];
$target  = (int)$round['target_score'];

// helper: validate player belongs to session
$belongs = $pdo->prepare("SELECT 1 FROM session_players WHERE session_id=? AND player_id=?");

if ($winnerPid !== null) {
  $belongs->execute([$sessionId, $winnerPid]);
  if (!$belongs->fetchColumn()) json_out(['error' => 'Winner is not part of this session'], 400);
}

// Server-side dealer suggestion (Approach B reset each round)
function suggestDealer(PDO $pdo, int $sessionId, int $roundId): ?int {
  // players in order
  $ps = $pdo->prepare("
    SELECT player_id
    FROM session_players
    WHERE session_id=?
    ORDER BY display_order ASC, player_id ASC
  ");
  $ps->execute([$sessionId]);
  $players = array_map('intval', array_column($ps->fetchAll(), 'player_id'));
  if (!$players) return null;

  // last dealer in this round
  $ld = $pdo->prepare("
    SELECT dealer_player_id
    FROM games
    WHERE round_id=? AND dealer_player_id IS NOT NULL
    ORDER BY game_number DESC, id DESC
    LIMIT 1
  ");
  $ld->execute([$roundId]);
  $lastDealer = $ld->fetchColumn();
  if ($lastDealer === false || $lastDealer === null) return $players[0];

  $lastDealer = (int)$lastDealer;
  $idx = array_search($lastDealer, $players, true);
  if ($idx === false) return $players[0];
  return $players[($idx + 1) % count($players)];
}

if ($dealerPid === null) {
  $dealerPid = suggestDealer($pdo, $sessionId, $roundId);
}
if ($dealerPid === null) json_out(['error' => 'Unable to determine dealer'], 400);

// validate dealer belongs to session
$belongs->execute([$sessionId, $dealerPid]);
if (!$belongs->fetchColumn()) json_out(['error' => 'Dealer is not part of this session'], 400);

// next game number
$gn = $pdo->prepare("SELECT COALESCE(MAX(game_number), 0) + 1 FROM games WHERE round_id=?");
$gn->execute([$roundId]);
$gameNumber = (int)$gn->fetchColumn();

$pdo->beginTransaction();
try {
  $insGame = $pdo->prepare("
    INSERT INTO games (round_id, game_number, winner_player_id, dealer_player_id)
    VALUES (?, ?, ?, ?)
  ");
  $insGame->execute([$roundId, $gameNumber, $winnerPid, $dealerPid]);
  $gameId = (int)$pdo->lastInsertId();

  $insScore = $pdo->prepare("
    INSERT INTO game_scores (game_id, player_id, score, is_winner)
    VALUES (?, ?, ?, 0)
  ");

  $seen = [];
  foreach ($scoresIn as $row) {
    $pid = (int)($row['player_id'] ?? 0);
    $sc  = (int)($row['score'] ?? 0);
    if ($pid <= 0) continue;
    $seen[$pid] = true;

    $belongs->execute([$sessionId, $pid]);
    if (!$belongs->fetchColumn()) throw new RuntimeException("Player {$pid} not part of session");

    $insScore->execute([$gameId, $pid, $sc]);
  }

  if ($winnerPid !== null) {
    if (!isset($seen[$winnerPid])) throw new RuntimeException("Winner must also have a score entry.");
    $pdo->prepare("UPDATE game_scores SET is_winner=1 WHERE game_id=? AND player_id=?")
        ->execute([$gameId, $winnerPid]);
  }

  // Auto-end round if target reached (no auto new round)
  $endedNow = false;
  if (roundHasReachedTarget($pdo, $roundId, $target)) {
    $newWinner = recomputeRoundWinner($pdo, $roundId);
    $pdo->prepare("UPDATE rounds SET ended_at=COALESCE(ended_at, NOW()), winner_player_id=? WHERE id=?")
        ->execute([$newWinner, $roundId]);
    $endedNow = true;
  }

  $pdo->prepare("UPDATE sessions SET last_activity_at=NOW() WHERE id=?")->execute([$sessionId]);

  $pdo->commit();
  json_out(['ok' => true, 'game_id' => $gameId, 'round_ended' => $endedNow]);
} catch (Throwable $e) {
  $pdo->rollBack();
  json_out(['error' => $e->getMessage()], 500);
}
