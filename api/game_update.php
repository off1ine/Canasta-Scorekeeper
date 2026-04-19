<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_login_api();


$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => t('Method not allowed.')], 405);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$gameId  = (int)($input['game_id'] ?? 0);
$scoresIn = $input['scores'] ?? null; // array of {player_id, score}
$winnerPid = array_key_exists('winner_player_id', $input) && $input['winner_player_id'] !== null
    ? (int)$input['winner_player_id'] : null;

if ($gameId <= 0 || !is_array($scoresIn) || count($scoresIn) < 2) {
    json_out(['error' => t('Invalid input.')], 400);
}

$dealerPid = array_key_exists('dealer_player_id', $input) && $input['dealer_player_id'] !== null
    ? (int)$input['dealer_player_id'] : null;


// Load game -> round -> session, and which players belong to session
$gameStmt = $pdo->prepare("
  SELECT g.id AS game_id, g.round_id, r.session_id, r.ended_at
  FROM games g
  JOIN rounds r ON r.id = g.round_id
  WHERE g.id = ?
");
$gameStmt->execute([$gameId]);
$g = $gameStmt->fetch();
if (!$g) json_out(['error' => t('Game not found.')], 404);

$roundId = (int)$g['round_id'];
$sessionId = (int)$g['session_id'];
$roundEndedAt = $g['ended_at']; // null or timestamp

// Session players list
$spStmt = $pdo->prepare("SELECT player_id FROM session_players WHERE session_id=?");
$spStmt->execute([$sessionId]);
$sessionPlayerIds = array_map('intval', array_column($spStmt->fetchAll(), 'player_id'));
$sessionPlayerSet = array_fill_keys($sessionPlayerIds, true);

if ($dealerPid !== null && !isset($sessionPlayerSet[$dealerPid])) {
    json_out(['error' => t('Dealer is not part of this session.')], 400);
}


if (count($sessionPlayerIds) < 2) json_out(['error' => t('Session has insufficient players.')], 400);

// Validate winner belongs to session
if ($winnerPid !== null && !isset($sessionPlayerSet[$winnerPid])) {
    json_out(['error' => t('Winner is not part of this session.')], 400);
}

$pdo->beginTransaction();
try {
    // Build map of submitted scores
    $submitted = [];
    foreach ($scoresIn as $row) {
        $pid = (int)($row['player_id'] ?? 0);
        $sc  = (int)($row['score'] ?? 0);
        if ($pid <= 0) continue;

        if (!isset($sessionPlayerSet[$pid])) {
            throw new RuntimeException("Player {$pid} not part of session");
        }
        $submitted[$pid] = $sc; // last one wins if duplicates
    }

    // Enforce: must include ALL session players for consistency (recommended)
    foreach ($sessionPlayerIds as $pid) {
        if (!array_key_exists($pid, $submitted)) {
            throw new RuntimeException("Missing score for player {$pid}");
        }
    }

    // Winner must be among submitted (if provided)
    if ($winnerPid !== null && !array_key_exists($winnerPid, $submitted)) {
        throw new RuntimeException("Winner must have a score entry.");
    }

    // Update game winner
    $pdo->prepare("UPDATE games SET winner_player_id=?, dealer_player_id=? WHERE id=?")
        ->execute([$winnerPid, $dealerPid, $gameId]);


    // Replace game_scores
    $pdo->prepare("DELETE FROM game_scores WHERE game_id=?")->execute([$gameId]);

    $ins = $pdo->prepare("INSERT INTO game_scores (game_id, player_id, score, is_winner) VALUES (?, ?, ?, ?)");
    foreach ($submitted as $pid => $sc) {
        $isWinner = ($winnerPid !== null && $pid === $winnerPid) ? 1 : 0;
        $ins->execute([$gameId, $pid, $sc, $isWinner]);
    }

    // After updating scores, recompute end-state based on target_score
    $roundInfoStmt = $pdo->prepare("SELECT target_score, ended_at FROM rounds WHERE id=?");
    $roundInfoStmt->execute([$roundId]);
    $roundInfo = $roundInfoStmt->fetch();
    $target = (int)$roundInfo['target_score'];

    $maxStmt = $pdo->prepare("
  SELECT MAX(round_total) AS max_total
  FROM v_round_totals
  WHERE round_id=?
");
    $maxStmt->execute([$roundId]);
    $maxTotal = (int)($maxStmt->fetchColumn() ?? 0);

    if ($maxTotal >= $target) {
        // ensure ended + recompute winner
        $newRoundWinner = recomputeRoundWinner($pdo, $roundId);
        $pdo->prepare("UPDATE rounds
                 SET ended_at = COALESCE(ended_at, NOW()),
                     winner_player_id = ?
                 WHERE id=?")
            ->execute([$newRoundWinner, $roundId]);
    } else {
        // un-end round if edits brought it below target
        $pdo->prepare("UPDATE rounds SET ended_at=NULL, winner_player_id=NULL WHERE id=?")
            ->execute([$roundId]);
    }


    $pdo->prepare("UPDATE sessions SET last_activity_at=NOW() WHERE id=?")->execute([$sessionId]);

    $pdo->commit();
    json_out(['ok' => true]);
} catch (Throwable $e) {
    $pdo->rollBack();
    json_out(['error' => $e->getMessage()], 500);
}
