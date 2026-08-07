<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/planning_policy.php';

const HIPPO_PLANNING_BODY_LIMIT = 1_000_000;
const HIPPO_PLAN_STATUSES = ['draft', 'published', 'closed', 'archived'];
const HIPPO_TASK_PRIORITIES = ['low', 'normal', 'high', 'urgent'];
const HIPPO_TASK_STATUSES = ['active', 'cancelled', 'archived'];
const HIPPO_ASSIGNMENT_STATUSES = ['pending', 'in_progress', 'blocked', 'needs_decision', 'completed', 'cancelled'];

function planning_json(array $payload, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function planning_ok(array $data = [], string $message = ''): never {
    planning_json(['ok' => true, 'data' => $data, 'message' => $message]);
}

function planning_fail(string $code, string $message, int $status = 400, array $extra = []): never {
    planning_json(array_merge(['ok' => false, 'error' => $code, 'message' => $message], $extra), $status);
}

function planning_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    if (strlen($raw) > HIPPO_PLANNING_BODY_LIMIT) {
        throw new HippoAuthorizationException('payload_too_large', 413);
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) throw new HippoAuthorizationException('invalid_json', 400);
    return $body;
}

function planning_text(mixed $value, int $max, bool $required = false): string {
    $value = trim((string)$value);
    if ($required && $value === '') throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'text']);
    if (mb_strlen($value) > $max) throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'text_length']);
    return $value;
}

function planning_id(mixed $value, string $field = 'id'): int {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) throw new HippoAuthorizationException('validation_error', 422, '', ['field' => $field]);
    return (int)$id;
}

function planning_expected_revision(array $body): int {
    if (!array_key_exists('expected_revision', $body)) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'expected_revision']);
    }
    $revision = filter_var($body['expected_revision'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($revision === false) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'expected_revision']);
    }
    return (int)$revision;
}

function planning_month_key(mixed $value): string {
    $value = trim((string)$value);
    if (!preg_match('/^(?:(?:13|14|15)\d{2}|20\d{2})-(0[1-9]|1[0-2])$/', $value)) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'month_key']);
    }
    return $value;
}

function planning_date(mixed $value, string $field, bool $required = false): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        if ($required) throw new HippoAuthorizationException('validation_error', 422, '', ['field' => $field]);
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => $field]);
    }
    return $value;
}

function planning_progress(mixed $value): int {
    if (is_string($value) && !preg_match('/^\d{1,3}$/', trim($value))) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'progress_percent']);
    }
    if (!is_int($value) && !is_string($value) && !is_float($value)) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'progress_percent']);
    }
    $progress = (int)$value;
    if ($progress < 0 || $progress > 100) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => 'progress_percent']);
    }
    return $progress;
}

function planning_enum(mixed $value, array $allowed, string $field): string {
    $value = (string)$value;
    if (!in_array($value, $allowed, true)) {
        throw new HippoAuthorizationException('validation_error', 422, '', ['field' => $field]);
    }
    return $value;
}

function planning_can_enter(array $user): bool {
    return hippo_can($user, 'plans.view_own')
        || hippo_can($user, 'plans.view_team')
        || hippo_can($user, 'plans.view_team_summary');
}

function planning_require_manager(array $user, string $permission): void {
    hippo_require_manager_permission($user, $permission);
}

function planning_plan(PDO $pdo, int $planId, bool $forUpdate = false): array {
    $sql = 'SELECT * FROM monthly_plans WHERE id = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$planId]);
    $row = $stmt->fetch();
    if (!$row) throw new HippoAuthorizationException('not_found', 404);
    return $row;
}

function planning_week(PDO $pdo, int $weekId, bool $forUpdate = false): array {
    $sql = 'SELECT w.*, p.status AS plan_status FROM monthly_plan_weeks w JOIN monthly_plans p ON p.id=w.plan_id WHERE w.id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$weekId]);
    $row = $stmt->fetch();
    if (!$row) throw new HippoAuthorizationException('not_found', 404);
    return $row;
}

function planning_task(PDO $pdo, int $taskId, bool $forUpdate = false): array {
    $sql = 'SELECT t.*, p.status AS plan_status, w.week_number FROM monthly_plan_tasks t JOIN monthly_plans p ON p.id=t.plan_id JOIN monthly_plan_weeks w ON w.id=t.week_id WHERE t.id=? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId]);
    $row = $stmt->fetch();
    if (!$row) throw new HippoAuthorizationException('not_found', 404);
    return $row;
}

function planning_assert_revision(array $row, int $expected): void {
    $current = (int)($row['revision'] ?? 0);
    if ($expected !== $current) {
        throw new HippoAuthorizationException('conflict', 409, '', ['current_revision' => $current]);
    }
}

function planning_assert_plan_writable(array $plan): void {
    if (in_array((string)$plan['status'], ['closed', 'archived'], true)) {
        throw new HippoAuthorizationException('plan_read_only', 409);
    }
}

function planning_normalize_plan(array $row): array {
    return [
        'id' => (int)$row['id'],
        'month_key' => (string)$row['month_key'],
        'title' => (string)$row['title'],
        'description' => (string)($row['description'] ?? ''),
        'status' => (string)$row['status'],
        'created_by' => (int)$row['created_by'],
        'published_at' => $row['published_at'] ?? null,
        'closed_at' => $row['closed_at'] ?? null,
        'archived_at' => $row['archived_at'] ?? null,
        'revision' => (int)$row['revision'],
        'created_at' => (string)$row['created_at'],
        'updated_at' => (string)$row['updated_at'],
    ];
}

function planning_normalize_week(array $row): array {
    return [
        'id' => (int)$row['id'], 'plan_id' => (int)$row['plan_id'],
        'week_number' => (int)$row['week_number'], 'title' => (string)$row['title'],
        'goal_text' => (string)($row['goal_text'] ?? ''),
        'start_date' => $row['start_date'] ?? null, 'end_date' => $row['end_date'] ?? null,
        'revision' => (int)$row['revision'], 'updated_at' => (string)$row['updated_at'],
    ];
}

function planning_normalize_task(array $row): array {
    return [
        'id' => (int)$row['id'], 'plan_id' => (int)$row['plan_id'], 'week_id' => (int)$row['week_id'],
        'title' => (string)$row['title'], 'description' => (string)($row['description'] ?? ''),
        'priority' => (string)$row['priority'], 'due_date' => $row['due_date'] ?? null,
        'status' => (string)$row['status'], 'revision' => (int)$row['revision'],
        'created_at' => (string)$row['created_at'], 'updated_at' => (string)$row['updated_at'],
        'source' => 'monthly_plan',
    ];
}

function planning_normalize_assignment(array $row, bool $includePrivate = true): array {
    $out = [
        'id' => (int)$row['id'], 'task_id' => (int)$row['task_id'], 'user_id' => (int)$row['user_id'],
        'team_member_id' => (string)$row['team_member_id'], 'status' => (string)$row['status'],
        'progress_percent' => (int)$row['progress_percent'], 'started_at' => $row['started_at'] ?? null,
        'completed_at' => $row['completed_at'] ?? null, 'revision' => (int)$row['revision'],
        'updated_at' => (string)$row['updated_at'],
    ];
    if (isset($row['display_name'])) $out['display_name'] = (string)$row['display_name'];
    if (isset($row['role'])) $out['role'] = hippo_role_alias((string)$row['role']);
    if ($includePrivate) {
        $out['user_note'] = (string)($row['user_note'] ?? '');
        $out['blocked_reason'] = (string)($row['blocked_reason'] ?? '');
    }
    return $out;
}

function planning_eligible_users(PDO $pdo): array {
    $stmt = $pdo->query(
        "SELECT id,display_name,role,status,team_member_id,rbac_review_required,locked_until
         FROM users
         WHERE status='active' AND role IN ('marketer','center_call')
           AND team_member_id IS NOT NULL AND team_member_id<>''
           AND rbac_review_required=0
           AND (locked_until IS NULL OR locked_until<=NOW())
         ORDER BY display_name,id"
    );
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $teamMemberId = (string)($row['team_member_id'] ?? '');
        if (!hippo_team_member_exists($pdo, $teamMemberId)) continue;
        $permissions = hippo_load_permissions($pdo, (int)$row['id'], (string)$row['role'], (string)$row['status']);
        if (empty($permissions['plans.view_own'])) continue;
        $out[] = [
            'id' => (int)$row['id'], 'display_name' => (string)$row['display_name'],
            'role' => hippo_role_alias((string)$row['role']), 'team_member_id' => (string)$row['team_member_id'],
        ];
    }
    return $out;
}

function planning_eligible_user(PDO $pdo, int $userId): array {
    foreach (planning_eligible_users($pdo) as $user) if ((int)$user['id'] === $userId) return $user;
    throw new HippoAuthorizationException('assignment_recipient_invalid', 422);
}

function planning_plan_full(PDO $pdo, int $planId): array {
    $plan = planning_normalize_plan(planning_plan($pdo, $planId));
    $weekStmt = $pdo->prepare('SELECT * FROM monthly_plan_weeks WHERE plan_id=? ORDER BY week_number');
    $weekStmt->execute([$planId]);
    $weeks = [];
    foreach ($weekStmt->fetchAll() as $row) {
        $week = planning_normalize_week($row);
        $week['tasks'] = [];
        $weeks[(int)$row['id']] = $week;
    }
    $taskStmt = $pdo->prepare('SELECT * FROM monthly_plan_tasks WHERE plan_id=? ORDER BY week_id,id');
    $taskStmt->execute([$planId]);
    $tasks = [];
    foreach ($taskStmt->fetchAll() as $row) {
        $task = planning_normalize_task($row);
        $task['assignments'] = [];
        $tasks[(int)$row['id']] = $task;
    }
    if ($tasks) {
        $assignmentStmt = $pdo->prepare(
            'SELECT a.*,u.display_name,u.role FROM monthly_task_assignments a JOIN users u ON u.id=a.user_id
             JOIN monthly_plan_tasks t ON t.id=a.task_id WHERE t.plan_id=? ORDER BY u.display_name,a.id'
        );
        $assignmentStmt->execute([$planId]);
        foreach ($assignmentStmt->fetchAll() as $row) {
            $taskId = (int)$row['task_id'];
            if (isset($tasks[$taskId])) $tasks[$taskId]['assignments'][] = planning_normalize_assignment($row, true);
        }
    }
    foreach ($tasks as $task) if (isset($weeks[(int)$task['week_id']])) $weeks[(int)$task['week_id']]['tasks'][] = $task;
    $plan['weeks'] = array_values($weeks);
    return $plan;
}

function planning_personal_plan(PDO $pdo, int $planId, int $userId): array {
    $rawPlan = planning_plan($pdo, $planId);
    if (!in_array((string)$rawPlan['status'], ['published', 'closed'], true)) throw new HippoAuthorizationException('not_found', 404);
    $stmt = $pdo->prepare(
        'SELECT a.*,t.plan_id,t.week_id,t.title AS task_title,t.description AS task_description,t.priority,t.due_date,t.status AS task_status,
                t.revision AS task_revision,w.week_number,w.title AS week_title,w.goal_text,w.start_date,w.end_date,w.revision AS week_revision
         FROM monthly_task_assignments a
         JOIN monthly_plan_tasks t ON t.id=a.task_id
         JOIN monthly_plan_weeks w ON w.id=t.week_id
         WHERE t.plan_id=? AND a.user_id=? AND t.status<>\'archived\'
         ORDER BY w.week_number,t.due_date,t.id'
    );
    $stmt->execute([$planId, $userId]);
    $rows = $stmt->fetchAll();
    if (!$rows) throw new HippoAuthorizationException('not_found', 404);
    $plan = planning_normalize_plan($rawPlan);
    $weeks = [];
    foreach ($rows as $row) {
        $weekId = (int)$row['week_id'];
        if (!isset($weeks[$weekId])) {
            $weeks[$weekId] = [
                'id' => $weekId, 'week_number' => (int)$row['week_number'], 'title' => (string)$row['week_title'],
                'goal_text' => (string)($row['goal_text'] ?? ''), 'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null, 'revision' => (int)$row['week_revision'], 'tasks' => [],
            ];
        }
        $weeks[$weekId]['tasks'][] = [
            'id' => (int)$row['task_id'], 'title' => (string)$row['task_title'],
            'description' => (string)($row['task_description'] ?? ''), 'priority' => (string)$row['priority'],
            'due_date' => $row['due_date'] ?? null, 'status' => (string)$row['task_status'],
            'revision' => (int)$row['task_revision'], 'source' => 'monthly_plan',
            'assignment' => planning_normalize_assignment($row, true),
        ];
    }
    $plan['weeks'] = array_values($weeks);
    return $plan;
}

function planning_team_summary(PDO $pdo, int $planId): array {
    $plan = planning_plan($pdo, $planId);
    $stmt = $pdo->prepare(
        "SELECT u.id AS user_id,u.display_name,u.role,COUNT(a.id) AS assignment_count,
                SUM(a.status='completed') AS completed_count,SUM(a.status='in_progress') AS in_progress_count,
                SUM(a.status='blocked') AS blocked_count,SUM(a.status='needs_decision') AS needs_decision_count,
                ROUND(AVG(a.progress_percent),0) AS progress_percent,MAX(a.updated_at) AS last_updated
         FROM monthly_task_assignments a
         JOIN monthly_plan_tasks t ON t.id=a.task_id
         JOIN users u ON u.id=a.user_id
         WHERE t.plan_id=? AND a.status<>'cancelled'
         GROUP BY u.id,u.display_name,u.role ORDER BY u.display_name"
    );
    $stmt->execute([$planId]);
    $members = [];
    foreach ($stmt->fetchAll() as $row) {
        $members[] = [
            'user_id' => (int)$row['user_id'], 'display_name' => (string)$row['display_name'],
            'role' => hippo_role_alias((string)$row['role']), 'assignment_count' => (int)$row['assignment_count'],
            'completed_count' => (int)$row['completed_count'], 'in_progress_count' => (int)$row['in_progress_count'],
            'blocked_count' => (int)$row['blocked_count'], 'needs_decision_count' => (int)$row['needs_decision_count'],
            'progress_percent' => (int)($row['progress_percent'] ?? 0), 'last_updated' => $row['last_updated'] ?? null,
        ];
    }
    $totals = ['assignment_count'=>0,'completed_count'=>0,'in_progress_count'=>0,'blocked_count'=>0,'needs_decision_count'=>0,'progress_percent'=>0];
    foreach ($members as $m) foreach (array_keys($totals) as $key) if ($key !== 'progress_percent') $totals[$key] += (int)$m[$key];
    $progressStmt = $pdo->prepare(
        "SELECT a.status,a.progress_percent FROM monthly_task_assignments a
         JOIN monthly_plan_tasks t ON t.id=a.task_id
         WHERE t.plan_id=?"
    );
    $progressStmt->execute([$planId]);
    $totals['progress_percent'] = planning_weighted_progress($progressStmt->fetchAll());
    return ['plan' => planning_normalize_plan($plan), 'totals' => $totals, 'members' => $members];
}

function planning_personal_plan_list(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT p.* FROM monthly_plans p JOIN monthly_plan_tasks t ON t.plan_id=p.id
         JOIN monthly_task_assignments a ON a.task_id=t.id
         WHERE a.user_id=? AND p.status IN ('published','closed') ORDER BY p.id DESC"
    );
    $stmt->execute([$userId]);
    return array_map('planning_normalize_plan', $stmt->fetchAll());
}

function planning_plan_list(PDO $pdo, array $user): array {
    if (hippo_can($user, 'plans.view_team')) {
        $stmt = $pdo->query('SELECT * FROM monthly_plans ORDER BY id DESC');
    } elseif (hippo_can($user, 'plans.view_team_summary')) {
        $stmt = $pdo->query("SELECT * FROM monthly_plans WHERE status IN ('published','closed') ORDER BY id DESC");
    } else {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT p.* FROM monthly_plans p JOIN monthly_plan_tasks t ON t.plan_id=p.id
             JOIN monthly_task_assignments a ON a.task_id=t.id
             WHERE a.user_id=? AND p.status IN ('published','closed') ORDER BY p.id DESC"
        );
        $stmt->execute([(int)$user['id']]);
    }
    return array_map('planning_normalize_plan', $stmt->fetchAll());
}

function planning_bootstrap(PDO $pdo, array $user, array $body): array {
    if (!planning_can_enter($user)) throw new HippoAuthorizationException('forbidden', 403);
    $plans = planning_plan_list($pdo, $user);
    $requested = isset($body['plan_id']) && (int)$body['plan_id'] > 0 ? (int)$body['plan_id'] : (int)($plans[0]['id'] ?? 0);
    $mode = hippo_can($user, 'plans.view_team') ? 'manager' : (hippo_can($user, 'plans.view_team_summary') ? 'viewer' : 'personal');
    $result = [
        'mode' => $mode, 'user' => [
            'id'=>(int)$user['id'],'display_name'=>(string)$user['display_name'],'role'=>(string)$user['role'],
            'role_label'=>(string)$user['role_label'],'permissions'=>(array)$user['permissions'],
            'permission_fingerprint'=>(string)$user['permission_fingerprint'],
        ],
        'plans' => $plans, 'plan' => null,
    ];
    if ($mode === 'manager') {
        $result['eligible_users'] = planning_eligible_users($pdo);
        if ($requested > 0) $result['plan'] = planning_plan_full($pdo, $requested);
    } elseif ($mode === 'viewer') {
        if ($requested > 0) $result['plan'] = planning_team_summary($pdo, $requested);
    } else {
        if ($requested > 0) $result['plan'] = planning_personal_plan($pdo, $requested, (int)$user['id']);
    }
    return $result;
}

function planning_record_assignment_history(PDO $pdo, array $assignment, int $changedBy, string $reason): int {
    $stmt = $pdo->prepare(
        'INSERT INTO monthly_assignment_history(
            assignment_id,old_status,old_progress_percent,old_user_note,old_blocked_reason,
            old_started_at,old_completed_at,changed_by,change_reason
         ) VALUES(?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        (int)$assignment['id'], (string)$assignment['status'], (int)$assignment['progress_percent'],
        $assignment['user_note'] ?? null, $assignment['blocked_reason'] ?? null,
        $assignment['started_at'] ?? null, $assignment['completed_at'] ?? null,
        $changedBy, $reason,
    ]);
    return (int)$pdo->lastInsertId();
}

function planning_assign_users(PDO $pdo, array $actor, int $taskId, array $userIds, string $auditAction): array {
    $task = planning_task($pdo, $taskId, true);
    if ((string)$task['status'] !== 'active' || in_array((string)$task['plan_status'], ['closed','archived'], true)) {
        throw new HippoAuthorizationException('task_not_assignable', 409);
    }
    $unique = array_values(array_unique(array_map(fn($v) => planning_id($v, 'user_id'), $userIds)));
    if (!$unique) throw new HippoAuthorizationException('validation_error', 422, '', ['field'=>'user_ids']);
    $created = [];
    $find = $pdo->prepare('SELECT id,status,revision FROM monthly_task_assignments WHERE task_id=? AND user_id=? LIMIT 1 FOR UPDATE');
    $insert = $pdo->prepare('INSERT INTO monthly_task_assignments(task_id,user_id,team_member_id,updated_by) VALUES(?,?,?,?)');
    foreach ($unique as $userId) {
        $recipient = planning_eligible_user($pdo, $userId);
        $find->execute([$taskId, $userId]);
        $existing = $find->fetch();
        if ($existing && (string)$existing['status'] === 'cancelled') {
            throw new HippoAuthorizationException('assignment_cancelled_requires_reactivation', 409);
        }
        if ($existing) continue;
        $insert->execute([$taskId, $userId, (string)$recipient['team_member_id'], (int)$actor['id']]);
        $created[] = (int)$pdo->lastInsertId();
    }
    hippo_audit($pdo, (int)$actor['id'], $auditAction, 'monthly_task', (string)$taskId, 'ok', ['recipient_count'=>count($created)]);
    return $created;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') planning_fail('method_not_allowed', 'فقط درخواست POST مجاز است.', 405);
    $user = hippo_require_login_api();
    $body = planning_body();
    hippo_require_csrf($body);
    $action = planning_text($_GET['action'] ?? ($body['action'] ?? ''), 80, true);
    $pdo = hippo_db();

    switch ($action) {
        case 'bootstrap':
        case 'list_months':
            planning_ok(planning_bootstrap($pdo, $user, $body));

        case 'get_plan':
            $planId = planning_id($body['plan_id'] ?? 0, 'plan_id');
            if (hippo_can($user, 'plans.view_team')) planning_ok(['plan'=>planning_plan_full($pdo,$planId)]);
            if (hippo_can($user, 'plans.view_team_summary')) {
                $summaryPlan = planning_plan($pdo, $planId);
                if (!in_array((string)$summaryPlan['status'], ['published','closed'], true)) throw new HippoAuthorizationException('not_found', 404);
                planning_ok(['plan'=>planning_team_summary($pdo,$planId)]);
            }
            hippo_require_permission($user, 'plans.view_own');
            planning_ok(['plan'=>planning_personal_plan($pdo,$planId,(int)$user['id'])]);

        case 'create_plan':
            planning_require_manager($user, 'plans.manage');
            $monthKey = planning_month_key($body['month_key'] ?? '');
            $title = planning_text($body['title'] ?? '', 180, true);
            $description = planning_text($body['description'] ?? '', 5000);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('INSERT INTO monthly_plans(month_key,title,description,created_by) VALUES(?,?,?,?)');
                $stmt->execute([$monthKey,$title,$description,(int)$user['id']]);
                $planId = (int)$pdo->lastInsertId();
                $weekStmt = $pdo->prepare('INSERT INTO monthly_plan_weeks(plan_id,week_number,title,goal_text) VALUES(?,?,?,?)');
                for ($n=1; $n<=4; $n++) $weekStmt->execute([$planId,$n,'هفته '.$n,'']);
                hippo_audit($pdo,(int)$user['id'],'plan.create','monthly_plan',(string)$planId,'ok',['month_key'=>$monthKey]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($e instanceof PDOException && in_array((string)$e->getCode(), ['23000','23505'], true)) throw new HippoAuthorizationException('month_exists',409);
                throw $e;
            }
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'برنامه ماهانه ایجاد شد.');

        case 'update_plan':
            planning_require_manager($user, 'plans.manage');
            $planId = planning_id($body['plan_id'] ?? 0, 'plan_id');
            $pdo->beginTransaction();
            try {
                $plan = planning_plan($pdo,$planId,true); planning_assert_plan_writable($plan); planning_assert_revision($plan,planning_expected_revision($body));
                $title = planning_text($body['title'] ?? $plan['title'],180,true);
                $description = planning_text($body['description'] ?? ($plan['description']??''),5000);
                $stmt=$pdo->prepare('UPDATE monthly_plans SET title=?,description=?,revision=revision+1 WHERE id=? AND revision=?');
                $stmt->execute([$title,$description,$planId,(int)$plan['revision']]);
                if ($stmt->rowCount()!==1) throw new HippoAuthorizationException('conflict',409);
                hippo_audit($pdo,(int)$user['id'],'plan.update','monthly_plan',(string)$planId);
                $pdo->commit();
            } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'برنامه به‌روزرسانی شد.');

        case 'publish_plan':
            planning_require_manager($user, 'plans.publish');
            $planId=planning_id($body['plan_id']??0,'plan_id');$pdo->beginTransaction();
            try{$plan=planning_plan($pdo,$planId,true);planning_assert_revision($plan,planning_expected_revision($body));if((string)$plan['status']!=='draft')throw new HippoAuthorizationException('invalid_plan_state',409);
                $stmt=$pdo->prepare("UPDATE monthly_plans SET status='published',published_at=NOW(),revision=revision+1 WHERE id=? AND revision=?");$stmt->execute([$planId,(int)$plan['revision']]);if($stmt->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);
                hippo_audit($pdo,(int)$user['id'],'plan.publish','monthly_plan',(string)$planId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'برنامه منتشر شد.');

        case 'close_plan':
            planning_require_manager($user, 'plans.close');
            $planId=planning_id($body['plan_id']??0,'plan_id');$pdo->beginTransaction();
            try{$plan=planning_plan($pdo,$planId,true);planning_assert_revision($plan,planning_expected_revision($body));if((string)$plan['status']!=='published')throw new HippoAuthorizationException('invalid_plan_state',409);
                $stmt=$pdo->prepare("UPDATE monthly_plans SET status='closed',closed_at=NOW(),revision=revision+1 WHERE id=? AND revision=?");$stmt->execute([$planId,(int)$plan['revision']]);if($stmt->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);
                hippo_audit($pdo,(int)$user['id'],'plan.close','monthly_plan',(string)$planId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'برنامه بسته شد.');

        case 'archive_plan':
            planning_require_manager($user, 'plans.close');
            $planId=planning_id($body['plan_id']??0,'plan_id');$pdo->beginTransaction();
            try{$plan=planning_plan($pdo,$planId,true);planning_assert_revision($plan,planning_expected_revision($body));if((string)$plan['status']==='archived')throw new HippoAuthorizationException('invalid_plan_state',409);
                $stmt=$pdo->prepare("UPDATE monthly_plans SET status='archived',archived_at=NOW(),revision=revision+1 WHERE id=? AND revision=?");$stmt->execute([$planId,(int)$plan['revision']]);if($stmt->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);
                hippo_audit($pdo,(int)$user['id'],'plan.archive','monthly_plan',(string)$planId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'برنامه آرشیو شد.');

        case 'copy_plan':
            planning_require_manager($user,'plans.copy_month');
            $sourceId=planning_id($body['source_plan_id']??0,'source_plan_id');$targetMonth=planning_month_key($body['target_month_key']??'');$targetTitle=planning_text($body['title']??'',180,true);
            $pdo->beginTransaction();
            try{$source=planning_plan_full($pdo,$sourceId);$stmt=$pdo->prepare('INSERT INTO monthly_plans(month_key,title,description,created_by) VALUES(?,?,?,?)');$stmt->execute([$targetMonth,$targetTitle,(string)$source['description'],(int)$user['id']]);$newPlanId=(int)$pdo->lastInsertId();
                $weekIns=$pdo->prepare('INSERT INTO monthly_plan_weeks(plan_id,week_number,title,goal_text,start_date,end_date) VALUES(?,?,?,?,NULL,NULL)');
                $taskIns=$pdo->prepare('INSERT INTO monthly_plan_tasks(plan_id,week_id,title,description,priority,due_date,created_by,status) VALUES(?,?,?,?,?,NULL,?,?)');
                foreach($source['weeks'] as $week){$weekIns->execute([$newPlanId,(int)$week['week_number'],(string)$week['title'],(string)$week['goal_text']]);$newWeekId=(int)$pdo->lastInsertId();foreach($week['tasks'] as $task){if((string)$task['status']!=='active')continue;$taskIns->execute([$newPlanId,$newWeekId,(string)$task['title'],(string)$task['description'],(string)$task['priority'],(int)$user['id'],'active']);}}
                hippo_audit($pdo,(int)$user['id'],'plan.copy','monthly_plan',(string)$newPlanId,'ok',['source_plan_id'=>$sourceId,'target_month_key'=>$targetMonth,'assignments_copied'=>false]);$pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof PDOException&&in_array((string)$e->getCode(),['23000','23505'],true))throw new HippoAuthorizationException('month_exists',409);throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$newPlanId)],'برنامه بدون Assignmentهای قبلی کپی شد.');

        case 'create_week':
            planning_require_manager($user,'plans.manage');
            $planId=planning_id($body['plan_id']??0,'plan_id');$number=(int)($body['week_number']??0);if($number<1||$number>4)throw new HippoAuthorizationException('validation_error',422,'',['field'=>'week_number']);
            $title=planning_text($body['title']??('هفته '.$number),180,true);$goal=planning_text($body['goal_text']??'',5000);$start=planning_date($body['start_date']??'','start_date');$end=planning_date($body['end_date']??'','end_date');
            $pdo->beginTransaction();try{$plan=planning_plan($pdo,$planId,true);planning_assert_plan_writable($plan);$stmt=$pdo->prepare('INSERT INTO monthly_plan_weeks(plan_id,week_number,title,goal_text,start_date,end_date) VALUES(?,?,?,?,?,?)');$stmt->execute([$planId,$number,$title,$goal,$start,$end]);$id=(int)$pdo->lastInsertId();hippo_audit($pdo,(int)$user['id'],'plan.week_create','monthly_plan_week',(string)$id);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'هفته ایجاد شد.');

        case 'update_week':
            planning_require_manager($user,'plans.manage');
            $weekId=planning_id($body['week_id']??0,'week_id');$pdo->beginTransaction();
            try{$week=planning_week($pdo,$weekId,true);if(in_array((string)$week['plan_status'],['closed','archived'],true))throw new HippoAuthorizationException('plan_read_only',409);planning_assert_revision($week,planning_expected_revision($body));
                $title=planning_text($body['title']??$week['title'],180,true);$goal=planning_text($body['goal_text']??($week['goal_text']??''),5000);$start=planning_date($body['start_date']??($week['start_date']??''),'start_date');$end=planning_date($body['end_date']??($week['end_date']??''),'end_date');if($start&&$end&&$end<$start)throw new HippoAuthorizationException('validation_error',422,'',['field'=>'date_range']);
                $stmt=$pdo->prepare('UPDATE monthly_plan_weeks SET title=?,goal_text=?,start_date=?,end_date=?,revision=revision+1 WHERE id=? AND revision=?');$stmt->execute([$title,$goal,$start,$end,$weekId,(int)$week['revision']]);if($stmt->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);hippo_audit($pdo,(int)$user['id'],'plan.week_update','monthly_plan_week',(string)$weekId);$planId=(int)$week['plan_id'];$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'هفته به‌روزرسانی شد.');

        case 'create_task':
            planning_require_manager($user,'plans.manage');
            $weekId=planning_id($body['week_id']??0,'week_id');$title=planning_text($body['title']??'',240,true);$description=planning_text($body['description']??'',8000);$priority=planning_enum($body['priority']??'normal',HIPPO_TASK_PRIORITIES,'priority');$due=planning_date($body['due_date']??'','due_date');
            $pdo->beginTransaction();try{$week=planning_week($pdo,$weekId,true);if(in_array((string)$week['plan_status'],['closed','archived'],true))throw new HippoAuthorizationException('plan_read_only',409);$stmt=$pdo->prepare('INSERT INTO monthly_plan_tasks(plan_id,week_id,title,description,priority,due_date,created_by) VALUES(?,?,?,?,?,?,?)');$stmt->execute([(int)$week['plan_id'],$weekId,$title,$description,$priority,$due,(int)$user['id']]);$taskId=(int)$pdo->lastInsertId();hippo_audit($pdo,(int)$user['id'],'plan.task_create','monthly_task',(string)$taskId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,(int)$week['plan_id']),'task_id'=>$taskId],'وظیفه ایجاد شد.');

        case 'update_task':
            planning_require_manager($user,'plans.manage');
            $taskId=planning_id($body['task_id']??0,'task_id');$pdo->beginTransaction();
            try{$task=planning_task($pdo,$taskId,true);if(in_array((string)$task['plan_status'],['closed','archived'],true))throw new HippoAuthorizationException('plan_read_only',409);planning_assert_revision($task,planning_expected_revision($body));
                $title=planning_text($body['title']??$task['title'],240,true);$description=planning_text($body['description']??($task['description']??''),8000);$priority=planning_enum($body['priority']??$task['priority'],HIPPO_TASK_PRIORITIES,'priority');$due=planning_date($body['due_date']??($task['due_date']??''),'due_date');
                $stmt=$pdo->prepare('UPDATE monthly_plan_tasks SET title=?,description=?,priority=?,due_date=?,revision=revision+1 WHERE id=? AND revision=?');$stmt->execute([$title,$description,$priority,$due,$taskId,(int)$task['revision']]);if($stmt->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);hippo_audit($pdo,(int)$user['id'],'plan.task_update','monthly_task',(string)$taskId);$planId=(int)$task['plan_id'];$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'وظیفه به‌روزرسانی شد.');

        case 'cancel_task':
            planning_require_manager($user,'plans.manage');
            $taskId=planning_id($body['task_id']??0,'task_id');$pdo->beginTransaction();try{$task=planning_task($pdo,$taskId,true);planning_assert_revision($task,planning_expected_revision($body));if(in_array((string)$task['plan_status'],['closed','archived'],true))throw new HippoAuthorizationException('plan_read_only',409);
                $stmt=$pdo->prepare("UPDATE monthly_plan_tasks SET status='cancelled',revision=revision+1 WHERE id=? AND revision=?");$stmt->execute([$taskId,(int)$task['revision']]);if($stmt->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);$pdo->prepare("UPDATE monthly_task_assignments SET status='cancelled',updated_by=?,revision=revision+1 WHERE task_id=? AND status<>'cancelled'")->execute([(int)$user['id'],$taskId]);hippo_audit($pdo,(int)$user['id'],'plan.task_cancel','monthly_task',(string)$taskId);$planId=(int)$task['plan_id'];$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'وظیفه لغو شد.');

        case 'assign_task':
            planning_require_manager($user,'plans.assign');$taskId=planning_id($body['task_id']??0,'task_id');$recipientId=planning_id($body['user_id']??0,'user_id');$pdo->beginTransaction();try{$ids=planning_assign_users($pdo,$user,$taskId,[$recipientId],'plan.assignment_create');$task=planning_task($pdo,$taskId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}planning_ok(['assignment_ids'=>$ids,'plan'=>planning_plan_full($pdo,(int)$task['plan_id'])],'Assignment ایجاد شد.');

        case 'assign_task_bulk':
            planning_require_manager($user,'plans.assign');$taskId=planning_id($body['task_id']??0,'task_id');$userIds=is_array($body['user_ids']??null)?$body['user_ids']:[];$pdo->beginTransaction();try{$ids=planning_assign_users($pdo,$user,$taskId,$userIds,'plan.assignment_bulk_create');$task=planning_task($pdo,$taskId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}planning_ok(['assignment_ids'=>$ids,'plan'=>planning_plan_full($pdo,(int)$task['plan_id'])],'Assignmentهای گروهی ایجاد شدند.');

        case 'assign_task_everyone':
            planning_require_manager($user,'plans.assign');$taskId=planning_id($body['task_id']??0,'task_id');$eligible=planning_eligible_users($pdo);if(!empty($body['preview']))planning_ok(['recipients'=>$eligible,'count'=>count($eligible)],'فهرست دریافت‌کنندگان آماده شد.');
            $pdo->beginTransaction();try{$ids=planning_assign_users($pdo,$user,$taskId,array_column($eligible,'id'),'plan.assignment_everyone');$task=planning_task($pdo,$taskId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}planning_ok(['assignment_ids'=>$ids,'recipient_count'=>count($ids),'plan'=>planning_plan_full($pdo,(int)$task['plan_id'])],'وظیفه عمومی ایجاد شد.');

        case 'update_assignment':
            planning_require_manager($user,'plans.assign');$assignmentId=planning_id($body['assignment_id']??0,'assignment_id');$pdo->beginTransaction();
            try{$stmt=$pdo->prepare('SELECT a.*,t.plan_id,t.status AS task_status,p.status AS plan_status FROM monthly_task_assignments a JOIN monthly_plan_tasks t ON t.id=a.task_id JOIN monthly_plans p ON p.id=t.plan_id WHERE a.id=? LIMIT 1 FOR UPDATE');$stmt->execute([$assignmentId]);$a=$stmt->fetch();if(!$a)throw new HippoAuthorizationException('not_found',404);if(in_array((string)$a['plan_status'],['closed','archived'],true)||(string)$a['task_status']!=='active')throw new HippoAuthorizationException('plan_read_only',409);planning_assert_revision($a,planning_expected_revision($body));if((string)$a['status']==='cancelled')throw new HippoAuthorizationException('assignment_cancelled_requires_reactivation',409);
                $status=planning_enum($body['status']??$a['status'],HIPPO_ASSIGNMENT_STATUSES,'status');if($status==='cancelled')throw new HippoAuthorizationException('forbidden',403);$progress=planning_progress($body['progress_percent']??$a['progress_percent']);$reason=planning_text($body['blocked_reason']??($a['blocked_reason']??''),1000);
                $started=$a['started_at'];$completed=$a['completed_at'];if(in_array($status,['in_progress','blocked','needs_decision','completed'],true)&&empty($started))$started=date('Y-m-d H:i:s');if($status==='completed'){$progress=100;$completed=date('Y-m-d H:i:s');}elseif((string)$a['status']==='completed'){$completed=null;}if(!in_array($status,['blocked','needs_decision'],true))$reason='';
                $up=$pdo->prepare('UPDATE monthly_task_assignments SET status=?,progress_percent=?,blocked_reason=?,started_at=?,completed_at=?,updated_by=?,revision=revision+1 WHERE id=? AND revision=?');$up->execute([$status,$progress,$reason,$started,$completed,(int)$user['id'],$assignmentId,(int)$a['revision']]);if($up->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);
                hippo_audit($pdo,(int)$user['id'],'plan.assignment_update','monthly_assignment',(string)$assignmentId,'ok',['status'=>$status,'progress_percent'=>$progress,'manager_update'=>true]);$planId=(int)$a['plan_id'];$pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'Assignment به‌روزرسانی شد.');

        case 'revoke_assignment':
            planning_require_manager($user,'plans.assign');$assignmentId=planning_id($body['assignment_id']??0,'assignment_id');$pdo->beginTransaction();try{$stmt=$pdo->prepare('SELECT a.*,t.plan_id,p.status AS plan_status FROM monthly_task_assignments a JOIN monthly_plan_tasks t ON t.id=a.task_id JOIN monthly_plans p ON p.id=t.plan_id WHERE a.id=? LIMIT 1 FOR UPDATE');$stmt->execute([$assignmentId]);$a=$stmt->fetch();if(!$a)throw new HippoAuthorizationException('not_found',404);if(in_array((string)$a['plan_status'],['closed','archived'],true))throw new HippoAuthorizationException('plan_read_only',409);planning_assert_revision($a,planning_expected_revision($body));$up=$pdo->prepare("UPDATE monthly_task_assignments SET status='cancelled',updated_by=?,revision=revision+1 WHERE id=? AND revision=?");$up->execute([(int)$user['id'],$assignmentId,(int)$a['revision']]);if($up->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);hippo_audit($pdo,(int)$user['id'],'plan.assignment_revoke','monthly_assignment',(string)$assignmentId);$planId=(int)$a['plan_id'];$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'Assignment لغو شد.');


        case 'reactivate_assignment':
            planning_require_manager($user,'plans.assign');
            $assignmentId=planning_id($body['assignment_id']??0,'assignment_id');
            $changeReason=planning_text($body['change_reason']??'',500,true);
            $pdo->beginTransaction();
            try{
                $stmt=$pdo->prepare('SELECT a.*,t.plan_id,t.status AS task_status,p.status AS plan_status FROM monthly_task_assignments a JOIN monthly_plan_tasks t ON t.id=a.task_id JOIN monthly_plans p ON p.id=t.plan_id WHERE a.id=? LIMIT 1 FOR UPDATE');
                $stmt->execute([$assignmentId]);
                $a=$stmt->fetch();
                if(!$a)throw new HippoAuthorizationException('not_found',404);
                if(in_array((string)$a['plan_status'],['closed','archived'],true)||(string)$a['task_status']!=='active')throw new HippoAuthorizationException('plan_read_only',409);
                planning_assert_revision($a,planning_expected_revision($body));
                if((string)$a['status']!=='cancelled')throw new HippoAuthorizationException('assignment_not_cancelled',409);
                $recipient=planning_eligible_user($pdo,(int)$a['user_id']);
                $historyId=planning_record_assignment_history($pdo,$a,(int)$user['id'],$changeReason);
                $up=$pdo->prepare("UPDATE monthly_task_assignments SET team_member_id=?,status='pending',progress_percent=0,user_note=NULL,blocked_reason=NULL,started_at=NULL,completed_at=NULL,updated_by=?,revision=revision+1 WHERE id=? AND revision=? AND status='cancelled'");
                $up->execute([(string)$recipient['team_member_id'],(int)$user['id'],$assignmentId,(int)$a['revision']]);
                if($up->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);
                hippo_audit($pdo,(int)$user['id'],'plan.assignment_reactivate','monthly_assignment',(string)$assignmentId,'ok',['history_id'=>$historyId,'new_status'=>'pending']);
                $planId=(int)$a['plan_id'];
                $pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_plan_full($pdo,$planId)],'Assignment به‌صورت صریح فعال شد و Snapshot قبلی حفظ گردید.');

        case 'update_my_assignment':
            hippo_require_permission($user,'plans.update_own');$assignmentId=planning_id($body['assignment_id']??0,'assignment_id');$pdo->beginTransaction();
            try{$stmt=$pdo->prepare('SELECT a.*,t.plan_id,t.status AS task_status,p.status AS plan_status FROM monthly_task_assignments a JOIN monthly_plan_tasks t ON t.id=a.task_id JOIN monthly_plans p ON p.id=t.plan_id WHERE a.id=? AND a.user_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$assignmentId,(int)$user['id']]);$a=$stmt->fetch();if(!$a)throw new HippoAuthorizationException('forbidden',403);if((string)$a['status']==='cancelled')throw new HippoAuthorizationException('assignment_cancelled_read_only',409);if((string)$a['plan_status']!=='published'||(string)$a['task_status']!=='active')throw new HippoAuthorizationException('plan_read_only',409);planning_assert_revision($a,planning_expected_revision($body));
                $status=planning_enum($body['status']??$a['status'],HIPPO_ASSIGNMENT_STATUSES,'status');if($status==='cancelled'||!planning_operational_assignment_transition_allowed((string)$a['status'],$status))throw new HippoAuthorizationException('forbidden',403);$progress=planning_progress($body['progress_percent']??$a['progress_percent']);$note=planning_text($body['user_note']??($a['user_note']??''),2000);$reason=planning_text($body['blocked_reason']??($a['blocked_reason']??''),1000);
                $started=$a['started_at'];$completed=$a['completed_at'];if(in_array($status,['in_progress','blocked','needs_decision','completed'],true)&&empty($started))$started=date('Y-m-d H:i:s');if($status==='completed'){$progress=100;$completed=date('Y-m-d H:i:s');}elseif((string)$a['status']==='completed'){$completed=null;}if(!in_array($status,['blocked','needs_decision'],true))$reason='';
                $up=$pdo->prepare('UPDATE monthly_task_assignments SET status=?,progress_percent=?,user_note=?,blocked_reason=?,started_at=?,completed_at=?,updated_by=?,revision=revision+1 WHERE id=? AND user_id=? AND revision=?');$up->execute([$status,$progress,$note,$reason,$started,$completed,(int)$user['id'],$assignmentId,(int)$user['id'],(int)$a['revision']]);if($up->rowCount()!==1)throw new HippoAuthorizationException('conflict',409);
                $auditAction=match($status){'completed'=>'plan.assignment_complete','blocked'=>'plan.assignment_blocked','needs_decision'=>'plan.assignment_needs_decision',default=>'plan.assignment_update'};hippo_audit($pdo,(int)$user['id'],$auditAction,'monthly_assignment',(string)$assignmentId,'ok',['status'=>$status,'progress_percent'=>$progress]);$planId=(int)$a['plan_id'];$pdo->commit();
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
            planning_ok(['plan'=>planning_personal_plan($pdo,$planId,(int)$user['id'])],'پیشرفت وظیفه ثبت شد.');

        case 'team_summary':
            hippo_require_permission($user,'plans.view_team_summary');
            $summaryPlanId = planning_id($body['plan_id']??0,'plan_id');
            $summaryPlanRow = planning_plan($pdo,$summaryPlanId);
            if (!hippo_can($user,'plans.view_team') && !in_array((string)$summaryPlanRow['status'],['published','closed'],true)) throw new HippoAuthorizationException('not_found',404);
            planning_ok(planning_team_summary($pdo,$summaryPlanId));

        case 'personal_summary':
            hippo_require_permission($user,'plans.view_own');
            $plans=planning_personal_plan_list($pdo,(int)$user['id']);
            $planId=isset($body['plan_id'])&&(int)$body['plan_id']>0?(int)$body['plan_id']:(int)($plans[0]['id']??0);
            $plan=$planId?planning_personal_plan($pdo,$planId,(int)$user['id']):null;
            planning_ok(['plans'=>$plans,'plan'=>$plan]);

        case 'blocked_tasks':
        case 'needs_decision_tasks':
            $targetStatus=$action==='blocked_tasks'?'blocked':'needs_decision';
            if(hippo_can($user,'plans.view_team')){$stmt=$pdo->prepare('SELECT a.id,a.task_id,a.user_id,a.team_member_id,a.status,a.progress_percent,a.blocked_reason,a.revision,a.updated_at,u.display_name,u.role,t.title AS task_title,t.plan_id,w.week_number,p.title AS plan_title,p.month_key FROM monthly_task_assignments a JOIN users u ON u.id=a.user_id JOIN monthly_plan_tasks t ON t.id=a.task_id JOIN monthly_plan_weeks w ON w.id=t.week_id JOIN monthly_plans p ON p.id=t.plan_id WHERE a.status=? ORDER BY a.updated_at DESC');$stmt->execute([$targetStatus]);$items=$stmt->fetchAll();}
            else{hippo_require_permission($user,'plans.view_own');$stmt=$pdo->prepare('SELECT a.id,a.task_id,a.user_id,a.team_member_id,a.status,a.progress_percent,a.blocked_reason,a.revision,a.updated_at,t.title AS task_title,t.plan_id,w.week_number,p.title AS plan_title,p.month_key FROM monthly_task_assignments a JOIN monthly_plan_tasks t ON t.id=a.task_id JOIN monthly_plan_weeks w ON w.id=t.week_id JOIN monthly_plans p ON p.id=t.plan_id WHERE a.status=? AND a.user_id=? ORDER BY a.updated_at DESC');$stmt->execute([$targetStatus,(int)$user['id']]);$items=$stmt->fetchAll();}planning_ok(['items'=>$items]);

        default:
            planning_fail('unknown_action','عملیات درخواستی شناخته نشد.',404);
    }
} catch (HippoAuthorizationException $e) {
    $messages = [
        'forbidden'=>'اجازه انجام این عملیات را ندارید.','manager_role_required'=>'این عملیات فقط برای مدیر مجاز است.',
        'invalid_csrf'=>'درخواست امنیتی معتبر نیست. صفحه را تازه‌سازی کنید.','not_found'=>'رکورد موردنظر پیدا نشد.',
        'validation_error'=>'اطلاعات ارسالی معتبر نیست.','conflict'=>'اطلاعات توسط نشست دیگری تغییر کرده است. صفحه را تازه‌سازی کنید.',
        'plan_read_only'=>'این برنامه بسته یا فقط‌خواندنی است.','invalid_plan_state'=>'وضعیت فعلی برنامه اجازه این عملیات را نمی‌دهد.',
        'month_exists'=>'برای این ماه قبلاً برنامه ساخته شده است.','assignment_recipient_invalid'=>'کاربر انتخاب‌شده واجد شرایط دریافت وظیفه نیست.',
        'task_not_assignable'=>'این وظیفه قابل تخصیص نیست.','assignment_cancelled_read_only'=>'Assignment لغوشده فقط خواندنی است و کاربر عملیاتی نمی‌تواند آن را فعال کند.',
        'assignment_cancelled_requires_reactivation'=>'Assignment لغوشده فقط از عملیات صریح فعال‌سازی مجدد مدیر قابل بازگشت است.',
        'assignment_not_cancelled'=>'فقط Assignment لغوشده را می‌توان فعال‌سازی مجدد کرد.','payload_too_large'=>'حجم درخواست بیش از حد مجاز است.',
        'invalid_json'=>'ساختار JSON درخواست معتبر نیست.',
    ];
    $extra = [];
    if ($e->httpStatus === 409 && isset($e->metadata['current_revision'])) $extra['current_revision'] = (int)$e->metadata['current_revision'];
    planning_fail($e->errorCode, $messages[$e->errorCode] ?? 'درخواست قابل انجام نیست.', $e->httpStatus, $extra);
} catch (PDOException $e) {
    error_log('Planning database error: ' . $e->getMessage());
    planning_fail('database_error','عملیات پایگاه داده انجام نشد.',500);
} catch (Throwable $e) {
    error_log('Planning API error: ' . $e->getMessage());
    planning_fail('internal_error','خطای فنی رخ داد. دوباره تلاش کنید.',500);
}
