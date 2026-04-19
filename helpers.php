<?php
declare(strict_types=1);

/**
 * Determine the round winner.
 * - Canasta: player with the highest total (ties -> null).
 * - Rommé:   player with the lowest total (ties -> null).
 */
function recomputeRoundWinner(PDO $pdo, int $roundId, string $gameType = 'canasta'): ?int {
    $order = $gameType === 'romme' ? 'ASC' : 'DESC';
    $stmt = $pdo->prepare("
        SELECT player_id, round_total
        FROM v_round_totals
        WHERE round_id=?
        ORDER BY round_total {$order}
    ");
    $stmt->execute([$roundId]);
    $rows = $stmt->fetchAll();
    if (!$rows) return null;

    $top = (int)$rows[0]['round_total'];
    $topPlayers = array_values(array_filter($rows, fn($r) => (int)$r['round_total'] === $top));
    return count($topPlayers) === 1 ? (int)$topPlayers[0]['player_id'] : null;
}

/**
 * Get the highest player total in a round.
 */
function roundMaxTotal(PDO $pdo, int $roundId): int {
    $stmt = $pdo->prepare("SELECT MAX(round_total) FROM v_round_totals WHERE round_id=?");
    $stmt->execute([$roundId]);
    return (int)($stmt->fetchColumn() ?? 0);
}

/**
 * Check whether the round has hit its end-of-round trigger.
 * - Canasta: any player total >= target.
 * - Rommé:   any player total >  target (first to exceed the ceiling).
 */
function roundHasReachedTarget(PDO $pdo, int $roundId, int $target, string $gameType = 'canasta'): bool {
    $max = roundMaxTotal($pdo, $roundId);
    return $gameType === 'romme' ? $max > $target : $max >= $target;
}
