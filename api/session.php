<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();


$pdo = db();
$sessionId = (int)($_GET['id'] ?? 0);
$roundIdParam = (int)($_GET['round_id'] ?? 0);
if ($sessionId <= 0) json_out(['error' => t('Missing session id.')], 400);

// session
$sess = $pdo->prepare("SELECT id, name, max_score_per_round, created_at, archived_at
                       FROM sessions WHERE id=?");
$sess->execute([$sessionId]);
$session = $sess->fetch();
if (!$session) json_out(['error' => t('Session not found.')], 404);

// players
$playersStmt = $pdo->prepare("
  SELECT p.id, p.name, sp.display_order
  FROM session_players sp
  JOIN players p ON p.id = sp.player_id
  WHERE sp.session_id=?
  ORDER BY sp.display_order ASC, p.name ASC
");
$playersStmt->execute([$sessionId]);
$players = $playersStmt->fetchAll();

// rounds list (for round selector)
$roundsStmt = $pdo->prepare("
  SELECT r.id, r.round_number, r.started_at, r.ended_at, r.target_score,
         r.winner_player_id, p.name AS winner_name
  FROM rounds r
  LEFT JOIN players p ON p.id = r.winner_player_id
  WHERE r.session_id=?
  ORDER BY r.round_number ASC
");
$roundsStmt->execute([$sessionId]);
$rounds = $roundsStmt->fetchAll();
if (!$rounds) json_out(['error' => t('No rounds found.')], 500);

// pick round
$round = null;
if ($roundIdParam > 0) {
  foreach ($rounds as $r) {
    if ((int)$r['id'] === $roundIdParam) { $round = $r; break; }
  }
  if (!$round) json_out(['error' => t('Round not found in session.')], 404);
} else {
  // default: active round if any, else last round
  foreach ($rounds as $r) {
    if ($r['ended_at'] === null) { $round = $r; break; }
  }
  if (!$round) $round = $rounds[count($rounds)-1];
}
$roundId = (int)$round['id'];

// games in round
$gamesStmt = $pdo->prepare("
  SELECT g.id, g.game_number, g.played_at, g.winner_player_id,
         pw.name AS winner_name,
         g.dealer_player_id,
         pd.name AS dealer_name
  FROM games g
  LEFT JOIN players pw ON pw.id = g.winner_player_id
  LEFT JOIN players pd ON pd.id = g.dealer_player_id
  WHERE g.round_id=?
  ORDER BY g.game_number ASC
");

$gamesStmt->execute([$roundId]);
$games = $gamesStmt->fetchAll();

// scores per game
$scoreStmt = $pdo->prepare("
  SELECT gs.game_id, gs.player_id, gs.score, gs.is_winner
  FROM game_scores gs
  JOIN games g ON g.id = gs.game_id
  WHERE g.round_id=?
");
$scoreStmt->execute([$roundId]);
$scores = $scoreStmt->fetchAll();

// totals per player (round)
$totalsStmt = $pdo->prepare("
  SELECT player_id, round_total
  FROM v_round_totals
  WHERE round_id=?
");
$totalsStmt->execute([$roundId]);
$totalsRows = $totalsStmt->fetchAll();
$totals = [];
foreach ($totalsRows as $tr) $totals[(int)$tr['player_id']] = (int)$tr['round_total'];

// last dealer across the whole session (for next-round rotation)
$lastDealerStmt = $pdo->prepare("
  SELECT g.dealer_player_id
  FROM games g
  JOIN rounds r ON r.id = g.round_id
  WHERE r.session_id = ?
  ORDER BY r.round_number DESC, g.game_number DESC
  LIMIT 1
");
$lastDealerStmt->execute([$sessionId]);
$lastDealer = $lastDealerStmt->fetchColumn();
$lastDealerPlayerId = $lastDealer !== false && $lastDealer !== null ? (int)$lastDealer : null;

// meld thresholds
$meld = $pdo->query("SELECT id, score_from, score_to, meld_minimum
                     FROM meld_thresholds
                     ORDER BY score_from ASC")->fetchAll();

function meldMinimumForScore(array $thresholds, int $score): ?int {
  foreach ($thresholds as $t) {
    $from = $t['score_from'] === null ? null : (int)$t['score_from'];
    $to   = $t['score_to']   === null ? null : (int)$t['score_to'];
    if (($from === null || $score >= $from) && ($to === null || $score <= $to)) {
      return (int)$t['meld_minimum'];
    }
  }
  return null;
}

$meldMin = [];
foreach ($players as $p) {
  $pid = (int)$p['id'];
  $score = $totals[$pid] ?? 0;
  $meldMin[$pid] = meldMinimumForScore($meld, $score);
}

json_out([
  'session' => $session,
  'players' => $players,
  'rounds' => $rounds,
  'round' => $round,
  'games' => $games,
  'scores' => $scores,
  'totals' => $totals,
  'meld_minimums' => $meldMin,
  'meld_thresholds' => $meld,
  'last_dealer_player_id' => $lastDealerPlayerId
]);
