<?php
declare(strict_types=1);

/**
 * V06.0.1 planning security policy helpers.
 * These functions are side-effect free so transition and weighted-progress policy
 * can be regression-tested without bootstrapping an HTTP request.
 */
function planning_operational_assignment_transition_allowed(string $currentStatus, string $nextStatus): bool {
    if ($currentStatus === 'cancelled') return false;
    return in_array($nextStatus, ['pending','in_progress','blocked','needs_decision','completed'], true);
}

/** @param array<int,array{status?:mixed,progress_percent?:mixed}> $assignments */
function planning_weighted_progress(array $assignments): int {
    $sum = 0;
    $count = 0;
    foreach ($assignments as $assignment) {
        if ((string)($assignment['status'] ?? '') === 'cancelled') continue;
        $progress = (int)($assignment['progress_percent'] ?? 0);
        $progress = max(0, min(100, $progress));
        $sum += $progress;
        $count++;
    }
    return $count > 0 ? (int)round($sum / $count) : 0;
}
