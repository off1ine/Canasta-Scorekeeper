<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();


$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => t('Method not allowed.')], 405);

$sessionId = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? (int)$_GET['session_id'] : null;

if ($sessionId !== null && $sessionId <= 0) {
    json_out(['error' => t('Invalid session id.')], 400);
}

/**
 * Run a query with an optional session filter.
 * $sql must contain {SESSION_JOIN} and {SESSION_WHERE} placeholders.
 */
function sessionQuery(PDO $pdo, string $sql, ?int $sessionId): array {
    if ($sessionId === null) {
        $sql = str_replace(['{SESSION_JOIN}', '{SESSION_WHERE}', '{SESSION_WHERE_ONLY}'], '', $sql);
        return $pdo->query($sql)->fetchAll();
    } else {
        $sql = str_replace('{SESSION_JOIN}', 'JOIN rounds r ON r.id = g.round_id', $sql);
        $sql = str_replace('{SESSION_WHERE}', 'AND r.session_id = ?', $sql);
        $sql = str_replace('{SESSION_WHERE_ONLY}', 'WHERE r.session_id = ?', $sql);
        $paramCount = substr_count($sql, '?');
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_fill(0, $paramCount, $sessionId));
        return $stmt->fetchAll();
    }
}

// ---- Player stats ----
if ($sessionId === null) {
    $sql = "
    SELECT
      p.id AS player_id,
      p.name AS player_name,

      (SELECT COALESCE(SUM(gs.score), 0)
       FROM game_scores gs
       WHERE gs.player_id = p.id) AS total_points,

      (SELECT COALESCE(AVG(gs.score), 0)
       FROM game_scores gs
       WHERE gs.player_id = p.id) AS avg_points_per_game,

      (SELECT COUNT(DISTINCT gs.game_id)
       FROM game_scores gs
       WHERE gs.player_id = p.id) AS games_played,

      (SELECT COUNT(*)
       FROM games g
       WHERE g.winner_player_id = p.id) AS won_games,

      (SELECT COUNT(*)
       FROM rounds r
       WHERE r.winner_player_id = p.id) AS won_rounds

    FROM players p
    HAVING games_played > 0
    ORDER BY total_points DESC, won_rounds DESC, won_games DESC, p.name ASC
  ";

    $rows = $pdo->query($sql)->fetchAll();
} else {
    $chk = $pdo->prepare("SELECT 1 FROM sessions WHERE id=?");
    $chk->execute([$sessionId]);
    if (!$chk->fetchColumn()) json_out(['error' => t('Session not found.')], 404);

    $sql = "
    SELECT
      p.id AS player_id,
      p.name AS player_name,

      (SELECT COALESCE(SUM(gs.score), 0)
       FROM game_scores gs
       JOIN games g ON g.id = gs.game_id
       JOIN rounds r ON r.id = g.round_id
       WHERE gs.player_id = p.id AND r.session_id = ?) AS total_points,

      (SELECT COALESCE(AVG(gs.score), 0)
       FROM game_scores gs
       JOIN games g ON g.id = gs.game_id
       JOIN rounds r ON r.id = g.round_id
       WHERE gs.player_id = p.id AND r.session_id = ?) AS avg_points_per_game,

      (SELECT COUNT(DISTINCT gs.game_id)
       FROM game_scores gs
       JOIN games g ON g.id = gs.game_id
       JOIN rounds r ON r.id = g.round_id
       WHERE gs.player_id = p.id AND r.session_id = ?) AS games_played,

      (SELECT COUNT(*)
       FROM games g
       JOIN rounds r ON r.id = g.round_id
       WHERE g.winner_player_id = p.id AND r.session_id = ?) AS won_games,

      (SELECT COUNT(*)
       FROM rounds r
       WHERE r.winner_player_id = p.id AND r.session_id = ?) AS won_rounds

    FROM session_players sp
    JOIN players p ON p.id = sp.player_id
    WHERE sp.session_id = ?
    ORDER BY total_points DESC, won_rounds DESC, won_games DESC, p.name ASC
  ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$sessionId, $sessionId, $sessionId, $sessionId, $sessionId, $sessionId]);
    $rows = $stmt->fetchAll();
}

// Normalize types / formatting
foreach ($rows as &$r) {
    $r['total_points'] = (int)$r['total_points'];
    $r['games_played'] = (int)$r['games_played'];
    $r['won_games'] = (int)$r['won_games'];
    $r['won_rounds'] = (int)$r['won_rounds'];
    $r['avg_points_per_game'] = round((float)$r['avg_points_per_game'], 2);

    $gp = $r['games_played'];
    $wg = $r['won_games'];
    $r['win_rate_games'] = $gp > 0 ? round(($wg / $gp) * 100, 2) : 0.0;
}
unset($r);

// ---- High/low scores (single game) ----
$limit = 5;

$scoreSql = "
    SELECT gs.score, p.name AS player_name, s.name AS session_name,
           r.round_number, g.game_number, g.played_at
    FROM game_scores gs
    JOIN players p ON p.id = gs.player_id
    JOIN games g ON g.id = gs.game_id
    JOIN rounds r ON r.id = g.round_id
    JOIN sessions s ON s.id = r.session_id
    {SESSION_WHERE_ONLY}
    ORDER BY gs.score %s, g.played_at DESC
    LIMIT {$limit}
";

$highScores = sessionQuery($pdo, sprintf($scoreSql, 'DESC'), $sessionId);
$lowScores = sessionQuery($pdo, sprintf($scoreSql, 'ASC'), $sessionId);

foreach ([&$highScores, &$lowScores] as &$list) {
    foreach ($list as &$row) {
        $row['score'] = (int)$row['score'];
        $row['round_number'] = (int)$row['round_number'];
        $row['game_number'] = (int)$row['game_number'];
    }
    unset($row);
}
unset($list);

// ---- Dealer impact stats ----
$diSql = "
    SELECT p.id AS player_id, p.name AS player_name,
           COUNT(*) AS games_dealt,
           SUM(CASE WHEN g.winner_player_id IS NOT NULL THEN 1 ELSE 0 END) AS games_with_winner,
           SUM(CASE WHEN g.winner_player_id = g.dealer_player_id THEN 1 ELSE 0 END) AS dealer_won_self
    FROM players p
    JOIN games g ON g.dealer_player_id = p.id
    {SESSION_JOIN}
    {SESSION_WHERE_ONLY}
    GROUP BY p.id, p.name
    ORDER BY p.name ASC
";
$diRows = sessionQuery($pdo, $diSql, $sessionId);

$dwSql = "
    SELECT g.dealer_player_id, g.winner_player_id, pw.name AS winner_name, COUNT(*) AS wins
    FROM games g
    JOIN players pw ON pw.id = g.winner_player_id
    {SESSION_JOIN}
    WHERE g.dealer_player_id IS NOT NULL AND g.winner_player_id IS NOT NULL {SESSION_WHERE}
    GROUP BY g.dealer_player_id, g.winner_player_id, pw.name
    ORDER BY g.dealer_player_id, wins DESC
";
$dwRows = sessionQuery($pdo, $dwSql, $sessionId);

// Build dealer_winners map
$dealerWinners = [];
foreach ($dwRows as $dw) {
  $did = (int)$dw['dealer_player_id'];
  if (!isset($dealerWinners[$did])) $dealerWinners[$did] = [];
  $dealerWinners[$did][] = [
    'winner_player_id' => (int)$dw['winner_player_id'],
    'winner_name' => $dw['winner_name'],
    'wins' => (int)$dw['wins']
  ];
}

// Build overall win rates lookup
$overallWinRate = [];
foreach ($rows as $r) {
  $overallWinRate[(int)$r['player_id']] = $r['win_rate_games'];
}

$dealerImpact = [];
foreach ($diRows as $di) {
  $pid = (int)$di['player_id'];
  $dealt = (int)$di['games_dealt'];
  $withWinner = (int)$di['games_with_winner'];
  $selfWins = (int)$di['dealer_won_self'];
  $selfWinRate = $withWinner > 0 ? round(($selfWins / $withWinner) * 100, 2) : 0.0;
  $overall = $overallWinRate[$pid] ?? 0.0;
  $delta = round($selfWinRate - $overall, 2);

  $dealerImpact[] = [
    'player_id' => $pid,
    'player_name' => $di['player_name'],
    'games_dealt' => $dealt,
    'wins_as_dealer' => $selfWins,
    'win_rate_as_dealer' => $selfWinRate,
    'overall_win_rate' => $overall,
    'delta' => $delta,
    'top_winners' => $dealerWinners[$pid] ?? []
  ];
}

json_out([
  'players' => $rows,
  'high_scores' => $highScores,
  'low_scores' => $lowScores,
  'dealer_impact' => $dealerImpact
]);
