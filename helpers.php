<?php
declare(strict_types=1);

/**
 * Determine the round winner (highest total, or null on tie/no data).
 */
function recomputeRoundWinner(PDO $pdo, int $roundId): ?int {
    $stmt = $pdo->prepare("
        SELECT player_id, round_total
        FROM v_round_totals
        WHERE round_id=?
        ORDER BY round_total DESC
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
 * Check whether any player has reached the target score in a round.
 */
function roundHasReachedTarget(PDO $pdo, int $roundId, int $target): bool {
    return roundMaxTotal($pdo, $roundId) >= $target;
}
