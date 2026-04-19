<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => 'Method not allowed'], 405);

$sessionId = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? (int)$_GET['session_id'] : null;
$since = isset($_GET['since']) && $_GET['since'] !== '' ? $_GET['since'] : null;

// Validate date format
if ($since !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $since)) {
    json_out(['error' => 'Invalid date format, use YYYY-MM-DD'], 400);
}

// Get all wins with dates, optionally filtered by session and date
$params = [];
$where = ['g.winner_player_id IS NOT NULL'];

if ($sessionId !== null) {
    $where[] = 'r.session_id = ?';
    $params[] = $sessionId;
}

if ($since !== null) {
    $where[] = 'g.played_at >= ?';
    $params[] = $since;
}

$whereSql = implode(' AND ', $where);

$sql = "
    SELECT
        g.winner_player_id AS player_id,
        p.name AS player_name,
        DATE(g.played_at) AS game_date,
        COUNT(*) AS wins
    FROM games g
    JOIN rounds r ON r.id = g.round_id
    JOIN players p ON p.id = g.winner_player_id
    WHERE {$whereSql}
    GROUP BY g.winner_player_id, p.name, DATE(g.played_at)
    ORDER BY game_date ASC, p.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Build per-player win series
$players = [];
$dateSet = [];

foreach ($rows as $row) {
    $pid = (int)$row['player_id'];
    $date = $row['game_date'];
    $wins = (int)$row['wins'];

    if (!isset($players[$pid])) {
        $players[$pid] = ['player_id' => $pid, 'player_name' => $row['player_name'], 'wins_by_date' => []];
    }
    $players[$pid]['wins_by_date'][$date] = $wins;
    $dateSet[$date] = true;
}

// Sort dates and build cumulative series
$dates = array_keys($dateSet);
sort($dates);

$series = [];
foreach ($players as $pid => $p) {
    $cumulative = [];
    $total = 0;
    foreach ($dates as $d) {
        $total += $p['wins_by_date'][$d] ?? 0;
        $cumulative[] = $total;
    }
    $series[] = [
        'player_id' => $p['player_id'],
        'player_name' => $p['player_name'],
        'data' => $cumulative
    ];
}

json_out([
    'dates' => $dates,
    'series' => $series,
    'since' => $since
]);
