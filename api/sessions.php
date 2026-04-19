<?php

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_login_api();


$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $includeArchived = isset($_GET['include_archived']) && $_GET['include_archived'] === '1';

    $sql = "SELECT id, name, max_score_per_round, game_type, meld_minimum, created_at, archived_at
          FROM sessions";
    if (!$includeArchived) {
        $sql .= " WHERE archived_at IS NULL";
    }
    $sql .= " ORDER BY created_at DESC";

    $rows = $pdo->query($sql)->fetchAll();
    json_out(['sessions' => $rows]);
}


if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = trim((string)($input['name'] ?? ''));
    $max = (int)($input['max_score_per_round'] ?? 5000);
    $players = $input['players'] ?? [];
    $gameType = (string)($input['game_type'] ?? 'canasta');
    if (!in_array($gameType, ['canasta', 'romme'], true)) {
        json_out(['error' => t('Invalid game type.')], 400);
    }
    $meldMin = null;
    if ($gameType === 'romme') {
        $meldMinRaw = $input['meld_minimum'] ?? 30;
        $meldMin = (int)$meldMinRaw;
        if ($meldMin <= 0) json_out(['error' => t('Meld minimum must be > 0.')], 400);
    }

    if ($name === '' || $max <= 0 || !is_array($players) || count($players) < 2) {
        json_out(['error' => t('Invalid session data (need name, max>0, >=2 players).')], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO sessions (name, max_score_per_round, game_type, meld_minimum) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $max, $gameType, $meldMin]);
        $sessionId = (int)$pdo->lastInsertId();

        // Ensure players exist
        $insPlayer = $pdo->prepare("INSERT INTO players (name) VALUES (?)");
        $selPlayer = $pdo->prepare("SELECT id FROM players WHERE name = ?");

        $insSP = $pdo->prepare("INSERT INTO session_players (session_id, player_id, display_order)
                            VALUES (?, ?, ?)");

        $order = 0;
        foreach ($players as $pnameRaw) {
            $pname = trim((string)$pnameRaw);
            if ($pname === '') continue;

            $selPlayer->execute([$pname]);
            $pid = $selPlayer->fetchColumn();
            if (!$pid) {
                $insPlayer->execute([$pname]);
                $pid = (int)$pdo->lastInsertId();
            } else {
                $pid = (int)$pid;
            }
            $insSP->execute([$sessionId, $pid, $order++]);
        }

        // Start round 1 immediately
        $stmt = $pdo->prepare("INSERT INTO rounds (session_id, round_number, target_score) VALUES (?, 1, ?)");
        $stmt->execute([$sessionId, $max]);

        $pdo->commit();
        json_out(['ok' => true, 'session_id' => $sessionId]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_out(['error' => $e->getMessage()], 500);
    }
}

json_out(['error' => t('Method not allowed.')], 405);
