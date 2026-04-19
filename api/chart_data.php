<?php
declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();

$pdo = db();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') json_out(['error' => t('Method not allowed.')], 405);

$sessionId = isset($_GET['session_id']) && $_GET['session_id'] !== '' ? (int)$_GET['session_id'] : null;
if ($sessionId !== null && $sessionId <= 0) json_out(['error' => t('Invalid session id.')], 400);

// ---- Games per round ----
if ($sessionId === null) {
    $gprSql = "
      SELECT r.id AS round_id, r.round_number, s.name AS session_name,
             COUNT(g.id) AS game_count
      FROM rounds r
      JOIN sessions s ON s.id = r.session_id
      LEFT JOIN games g ON g.round_id = r.id
      GROUP BY r.id, r.round_number, s.name
      ORDER BY r.id ASC
    ";
    $gprRows = $pdo->query($gprSql)->fetchAll();
} else {
    $gprSql = "
      SELECT r.id AS round_id, r.round_number, s.name AS session_name,
             COUNT(g.id) AS game_count
      FROM rounds r
      JOIN sessions s ON s.id = r.session_id
      LEFT JOIN games g ON g.round_id = r.id
      WHERE r.session_id = ?
      GROUP BY r.id, r.round_number, s.name
      ORDER BY r.round_number ASC
    ";
    $stmt = $pdo->prepare($gprSql);
    $stmt->execute([$sessionId]);
    $gprRows = $stmt->fetchAll();
}

$gamesPerRound = [
    'labels' => [],
    'data'   => []
];
foreach ($gprRows as $row) {
    $label = $sessionId
        ? "R{$row['round_number']}"
        : "{$row['session_name']} R{$row['round_number']}";
    $gamesPerRound['labels'][] = $label;
    $gamesPerRound['data'][] = (int)$row['game_count'];
}

// ---- Score per game (per player) ----
if ($sessionId === null) {
    $spgSql = "
      SELECT g.id AS game_id, g.game_number, r.round_number, s.name AS session_name,
             gs.player_id, p.name AS player_name, gs.score
      FROM game_scores gs
      JOIN games g ON g.id = gs.game_id
      JOIN rounds r ON r.id = g.round_id
      JOIN sessions s ON s.id = r.session_id
      JOIN players p ON p.id = gs.player_id
      ORDER BY g.played_at ASC, g.id ASC, p.name ASC
    ";
    $spgRows = $pdo->query($spgSql)->fetchAll();
} else {
    $spgSql = "
      SELECT g.id AS game_id, g.game_number, r.round_number, s.name AS session_name,
             gs.player_id, p.name AS player_name, gs.score
      FROM game_scores gs
      JOIN games g ON g.id = gs.game_id
      JOIN rounds r ON r.id = g.round_id
      JOIN sessions s ON s.id = r.session_id
      JOIN players p ON p.id = gs.player_id
      WHERE r.session_id = ?
      ORDER BY r.round_number ASC, g.game_number ASC, p.name ASC
    ";
    $stmt = $pdo->prepare($spgSql);
    $stmt->execute([$sessionId]);
    $spgRows = $stmt->fetchAll();
}

// Build labels (one per game) and per-player series
$gameLabels = [];
$gameIdOrder = [];
$playerScores = []; // player_name => [game_index => score]
$playerNames = [];

foreach ($spgRows as $row) {
    $gid = (int)$row['game_id'];
    if (!isset($gameIdOrder[$gid])) {
        $idx = count($gameIdOrder);
        $gameIdOrder[$gid] = $idx;
        $label = $sessionId
            ? "R{$row['round_number']}G{$row['game_number']}"
            : "{$row['session_name']} R{$row['round_number']}G{$row['game_number']}";
        $gameLabels[] = $label;
    }

    $pName = $row['player_name'];
    if (!isset($playerScores[$pName])) {
        $playerScores[$pName] = [];
        $playerNames[] = $pName;
    }
    $playerScores[$pName][$gameIdOrder[$gid]] = (int)$row['score'];
}

$totalGames = count($gameLabels);
$series = [];
foreach ($playerNames as $name) {
    $data = [];
    for ($i = 0; $i < $totalGames; $i++) {
        $data[] = $playerScores[$name][$i] ?? null;
    }
    $series[] = ['player_name' => $name, 'data' => $data];
}

// ---- Score per round (per player) ----
if ($sessionId === null) {
    $sprSql = "
      SELECT r.id AS round_id, r.round_number, s.name AS session_name,
             vt.player_id, p.name AS player_name, vt.round_total AS score
      FROM v_round_totals vt
      JOIN rounds r ON r.id = vt.round_id
      JOIN sessions s ON s.id = r.session_id
      JOIN players p ON p.id = vt.player_id
      ORDER BY r.id ASC, p.name ASC
    ";
    $sprRows = $pdo->query($sprSql)->fetchAll();
} else {
    $sprSql = "
      SELECT r.id AS round_id, r.round_number, s.name AS session_name,
             vt.player_id, p.name AS player_name, vt.round_total AS score
      FROM v_round_totals vt
      JOIN rounds r ON r.id = vt.round_id
      JOIN sessions s ON s.id = r.session_id
      JOIN players p ON p.id = vt.player_id
      WHERE r.session_id = ?
      ORDER BY r.round_number ASC, p.name ASC
    ";
    $stmt = $pdo->prepare($sprSql);
    $stmt->execute([$sessionId]);
    $sprRows = $stmt->fetchAll();
}

$roundLabels = [];
$roundIdOrder = [];
$roundPlayerScores = [];
$roundPlayerNames = [];

foreach ($sprRows as $row) {
    $rid = (int)$row['round_id'];
    if (!isset($roundIdOrder[$rid])) {
        $idx = count($roundIdOrder);
        $roundIdOrder[$rid] = $idx;
        $label = $sessionId
            ? "R{$row['round_number']}"
            : "{$row['session_name']} R{$row['round_number']}";
        $roundLabels[] = $label;
    }

    $pName = $row['player_name'];
    if (!isset($roundPlayerScores[$pName])) {
        $roundPlayerScores[$pName] = [];
        $roundPlayerNames[] = $pName;
    }
    $roundPlayerScores[$pName][$roundIdOrder[$rid]] = (int)$row['score'];
}

$totalRounds = count($roundLabels);
$roundSeries = [];
foreach ($roundPlayerNames as $name) {
    $data = [];
    for ($i = 0; $i < $totalRounds; $i++) {
        $data[] = $roundPlayerScores[$name][$i] ?? null;
    }
    $roundSeries[] = ['player_name' => $name, 'data' => $data];
}

json_out([
    'games_per_round' => $gamesPerRound,
    'score_per_game'  => [
        'labels' => $gameLabels,
        'series' => $series
    ],
    'score_per_round' => [
        'labels' => $roundLabels,
        'series' => $roundSeries
    ]
]);
