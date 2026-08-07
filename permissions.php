<?php
declare(strict_types=1);

/**
 * V05.2 central authorization and data-boundary policy.
 * HTTP endpoints must route RBAC, field filtering and scoped writes through this file.
 */

const HIPPO_SCOPE_VERSION = 'v06';

// Shared hosts do not always enable mbstring. Keep security truncation available
// without turning a missing optional extension into an authorization failure.
if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        if (function_exists('iconv_substr')) {
            $result = iconv_substr($value, $start, $length ?? strlen($value), $encoding ?: 'UTF-8');
            if ($result !== false) return $result;
        }
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int {
        if (function_exists('iconv_strlen')) {
            $result = iconv_strlen($value, $encoding ?: 'UTF-8');
            if ($result !== false) return $result;
        }
        return strlen($value);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string {
        return strtolower($value);
    }
}

final class HippoAuthorizationException extends RuntimeException {
    public function __construct(
        public readonly string $errorCode,
        public readonly int $httpStatus = 403,
        string $message = '',
        public readonly array $metadata = []
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}

function hippo_role_alias(string $role): string {
    return match ($role) {
        'editor' => 'manager',
        'viewer', 'viewer_manager' => 'manager_viewer',
        default => $role,
    };
}

function hippo_valid_roles(): array {
    return ['manager', 'marketer', 'center_call', 'manager_viewer'];
}

function hippo_role_label(string $role): string {
    return match (hippo_role_alias($role)) {
        'manager' => 'مدیر',
        'marketer' => 'بازاریاب / کارشناس فروش',
        'center_call' => 'مرکز تماس',
        'manager_viewer' => 'ناظر مدیریتی',
        default => 'نامشخص',
    };
}

function hippo_permission_keys(): array {
    return [
        'dashboard.view_personal', 'dashboard.view_team',
        'customers.view_own', 'customers.view_all', 'customers.create',
        'customers.edit_own', 'customers.edit_all', 'customers.delete',
        'customers.assign', 'customers.share',
        'interactions.create', 'interactions.edit_own',
        'followups.manage_own', 'followups.manage_all',
        'tasks.view_own', 'tasks.view_all', 'tasks.create_personal', 'tasks.assign',
        'reports.view_personal', 'reports.view_team',
        'orders.view_own', 'orders.view_all',
        'excel_import.use', 'ai.use',
        'users.manage', 'permissions.manage',
        'backups.view', 'backups.restore',
        'settings.manage', 'audit.view',
        'state.view_full', 'state.save_full',
        'plans.view_own', 'plans.view_team', 'plans.view_team_summary',
        'plans.manage', 'plans.publish', 'plans.assign', 'plans.update_own',
        'plans.close', 'plans.copy_month',
    ];
}

function hippo_role_permission_defaults(): array {
    $all = array_fill_keys(hippo_permission_keys(), true);
    return [
        'manager' => $all,
        'marketer' => array_fill_keys([
            'dashboard.view_personal', 'customers.view_own', 'customers.create',
            'customers.edit_own', 'interactions.create', 'interactions.edit_own',
            'followups.manage_own', 'tasks.view_own', 'tasks.create_personal',
            'reports.view_personal', 'orders.view_own', 'ai.use',
            'plans.view_own', 'plans.update_own',
        ], true),
        'center_call' => array_fill_keys([
            'dashboard.view_personal', 'customers.view_own', 'interactions.create',
            'followups.manage_own', 'tasks.view_own', 'reports.view_personal',
            'plans.view_own', 'plans.update_own',
        ], true),
        'manager_viewer' => array_fill_keys([
            'dashboard.view_team', 'reports.view_team', 'plans.view_team_summary',
        ], true),
    ];
}

function hippo_default_permissions_for_role(string $role): array {
    $role = hippo_role_alias($role);
    $defaults = hippo_role_permission_defaults()[$role] ?? [];
    $result = [];
    foreach (hippo_permission_keys() as $key) $result[$key] = !empty($defaults[$key]);
    return $result;
}

function hippo_load_permissions(PDO $pdo, int $userId, string $role, string $status): array {
    $permissions = hippo_default_permissions_for_role($role);
    if ($status !== 'active') return array_fill_keys(array_keys($permissions), false);
    try {
        $stmt = $pdo->prepare('SELECT permission_key, allowed FROM role_permissions WHERE role = ?');
        $stmt->execute([hippo_role_alias($role)]);
        $rows = $stmt->fetchAll();
        if ($rows) {
            $permissions = array_fill_keys(hippo_permission_keys(), false);
            foreach ($rows as $row) {
                $key = (string)$row['permission_key'];
                if (array_key_exists($key, $permissions)) $permissions[$key] = (bool)$row['allowed'];
            }
        }
        $stmt = $pdo->prepare('SELECT permission_key, allowed FROM user_permission_overrides WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) {
            $key = (string)$row['permission_key'];
            if (array_key_exists($key, $permissions)) $permissions[$key] = (bool)$row['allowed'];
        }
    } catch (Throwable $e) {
        error_log('RBAC permission load failed: ' . $e->getMessage());
    }
    return $permissions;
}

function hippo_can(array $user, string $permission): bool {
    if (($user['status'] ?? 'inactive') !== 'active') return false;
    return !empty(($user['permissions'] ?? [])[$permission]);
}

function hippo_is_manager(array $user): bool {
    return hippo_role_alias((string)($user['role'] ?? '')) === 'manager';
}

function hippo_can_view_full_state(array $user): bool {
    return hippo_is_manager($user) && hippo_can($user, 'state.view_full');
}

/** Full-State write invariant: role + view + save are all mandatory. */
function hippo_can_save_full_state(array $user): bool {
    return hippo_is_manager($user)
        && hippo_can($user, 'state.view_full')
        && hippo_can($user, 'state.save_full');
}

function hippo_require_permission(array $user, string $permission): void {
    if (!hippo_can($user, $permission)) {
        throw new HippoAuthorizationException('forbidden', 403, 'Permission denied: ' . $permission, ['permission' => $permission]);
    }
}

/** Central guard for organization-sensitive permissions; overrides never replace manager role. */
function hippo_require_manager_permission(array $user, string $permission): void {
    if (!hippo_is_manager($user)) {
        throw new HippoAuthorizationException('manager_role_required', 403, '', ['permission' => $permission]);
    }
    hippo_require_permission($user, $permission);
}

function hippo_role_requires_team_member(string $role): bool {
    return in_array(hippo_role_alias($role), ['marketer', 'center_call'], true);
}

function hippo_operational_account_ready(array $user): bool {
    if (!hippo_role_requires_team_member((string)($user['role'] ?? ''))) return true;
    return trim((string)($user['team_member_id'] ?? '')) !== ''
        && empty($user['rbac_review_required'])
        && (!array_key_exists('team_member_valid', $user) || !empty($user['team_member_valid']));
}

function hippo_require_operational_account(array $user): void {
    if (!hippo_operational_account_ready($user)) {
        throw new HippoAuthorizationException('operational_account_review_required', 403);
    }
}

function hippo_permission_fingerprint(array $user): string {
    $permissions = (array)($user['permissions'] ?? []);
    ksort($permissions);
    $payload = [
        'v' => HIPPO_SCOPE_VERSION,
        'user_id' => (int)($user['id'] ?? 0),
        'role' => hippo_role_alias((string)($user['role'] ?? '')),
        'status' => (string)($user['status'] ?? 'inactive'),
        'team_member_id' => (string)($user['team_member_id'] ?? ''),
        'rbac_review_required' => (int)!empty($user['rbac_review_required']),
        'team_member_valid' => (int)(!array_key_exists('team_member_valid', $user) || !empty($user['team_member_valid'])),
        'permissions' => $permissions,
    ];
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
}

function hippo_csrf_token(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function hippo_csrf_from_request(?array $body = null): string {
    $header = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($header !== '') return $header;
    return is_array($body) ? (string)($body['csrf_token'] ?? '') : '';
}

function hippo_verify_csrf(?array $body = null): bool {
    $actual = hippo_csrf_from_request($body);
    $expected = hippo_csrf_token();
    return $actual !== '' && hash_equals($expected, $actual);
}

function hippo_require_csrf(?array $body = null): void {
    if (!hippo_verify_csrf($body)) throw new HippoAuthorizationException('invalid_csrf', 403);
}

function hippo_client_ip(): string {
    return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function hippo_audit(
    PDO $pdo,
    ?int $userId,
    string $action,
    string $entityType = 'system',
    ?string $entityId = null,
    string $result = 'ok',
    array $metadata = []
): void {
    try {
        $safe = $metadata;
        foreach (['password', 'password_hash', 'api_key', 'token', 'csrf_token', 'prompt', 'data', 'state'] as $key) unset($safe[$key]);
        // Keep metadata bounded and avoid accidentally logging full objects.
        foreach ($safe as $key => $value) {
            if (is_array($value)) $safe[$key] = array_slice(array_map('strval', array_values($value)), 0, 30);
            elseif (is_string($value)) $safe[$key] = mb_substr($value, 0, 500);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO audit_logs
             (user_id, action, entity_type, entity_id, result, metadata_json, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            mb_substr($action, 0, 100),
            mb_substr($entityType, 0, 50),
            $entityId !== null ? mb_substr($entityId, 0, 120) : null,
            mb_substr($result, 0, 30),
            json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            hippo_client_ip(),
            mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
        ]);
    } catch (Throwable $e) {
        error_log('Audit write failed: ' . $e->getMessage());
    }
}

function hippo_normalize_phone_server(mixed $value): string {
    $s = strtr((string)$value, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $s = preg_replace('/[^0-9+]/u', '', $s) ?? '';
    if (str_starts_with($s, '0098')) $s = '0' . substr($s, 4);
    elseif (str_starts_with($s, '+98')) $s = '0' . substr($s, 3);
    elseif (str_starts_with($s, '98') && strlen($s) >= 12) $s = '0' . substr($s, 2);
    return preg_replace('/\D+/', '', $s) ?? '';
}

function hippo_access_rank(string $level): int {
    return match ($level) {'edit' => 3, 'call' => 2, 'view' => 1, default => 0};
}

function hippo_access_from_rank(int $rank): string {
    return match (true) {$rank >= 3 => 'edit', $rank === 2 => 'call', $rank === 1 => 'view', default => ''};
}

function hippo_customer_access_rows(PDO $pdo, int $userId): array {
    $rows = [];
    try {
        $stmt = $pdo->prepare('SELECT customer_id, access_level FROM customer_access WHERE user_id = ?');
        $stmt->execute([$userId]);
        foreach ($stmt->fetchAll() as $row) $rows[(string)$row['customer_id']] = (string)$row['access_level'];
    } catch (Throwable $e) {
        error_log('Customer access load failed: ' . $e->getMessage());
    }
    return $rows;
}

/** Explicit customer_access is a ceiling when it coexists with broad permissions. */
function hippo_customer_level(array $user, array $customer, array $accessRows): string {
    $id = (string)($customer['id'] ?? '');
    $explicit = (string)($accessRows[$id] ?? '');
    if (!in_array($explicit, ['view', 'call', 'edit'], true)) $explicit = '';

    $base = '';
    if (hippo_can($user, 'customers.view_all')) {
        $base = hippo_can($user, 'customers.edit_all') ? 'edit' : 'view';
    } else {
        $memberId = (string)($user['team_member_id'] ?? '');
        $isOwner = $memberId !== '' && (string)($customer['assignee'] ?? '') === $memberId;
        if ($isOwner && hippo_can($user, 'customers.view_own')) {
            $base = hippo_can($user, 'customers.edit_own') ? 'edit' : 'view';
        }
    }

    if ($explicit !== '' && $base !== '') {
        return hippo_access_from_rank(min(hippo_access_rank($explicit), hippo_access_rank($base)));
    }
    return $explicit !== '' ? $explicit : $base;
}

function hippo_customer_is_visible(array $user, array $customer, array $accessRows): bool {
    return hippo_access_rank(hippo_customer_level($user, $customer, $accessRows)) >= 1;
}

/**
 * Customer Access is a hard ceiling. A View grant never becomes Call implicitly,
 * even for the center-call role. The manager must explicitly grant access_level=call
 * before an interaction can be created for that customer.
 */
function hippo_interaction_level_for_user(array $user, string $customerLevel): string {
    return $customerLevel;
}

function hippo_minimal_team(array $team): array {
    $out = [];
    foreach ($team as $member) {
        if (!is_array($member) || !isset($member['id'])) continue;
        $out[] = ['id'=>(string)$member['id'], 'name'=>(string)($member['name'] ?? ''), 'role'=>(string)($member['role'] ?? ''), 'access'=>(string)($member['access'] ?? '')];
    }
    return $out;
}

function hippo_team_name_map(array $team): array {
    $map = [];
    foreach ($team as $member) {
        if (is_array($member) && isset($member['id'])) $map[(string)$member['id']] = (string)($member['name'] ?? '');
    }
    return $map;
}

function hippo_pick_fields(array $row, array $allowlist): array {
    $out = [];
    foreach ($allowlist as $key) if (array_key_exists($key, $row)) $out[$key] = $row[$key];
    return $out;
}

function hippo_customer_output_allowlist(string $level): array {
    return match ($level) {
        'edit' => [
            'id','name','company','contact','phone','phone2','city','province','industry','product',
            'technicalNeed','source','stage','nextFollowUp','status','estimatedVolume','productGroup','consumptionType','packaging','currency','createdAt','updatedAt'
        ],
        'call' => [
            'id','name','company','contact','phone','phone2','city','province','industry','product',
            'stage','nextFollowUp','status'
        ],
        'view' => ['id','name','company','city','province','industry','product','stage','nextFollowUp','status'],
        default => [],
    };
}

function hippo_filter_customer_fields_for_level(array $customer, string $level, array $user, array $teamNameMap): array {
    $copy = hippo_pick_fields($customer, hippo_customer_output_allowlist($level));
    $assigneeId = (string)($customer['assignee'] ?? '');
    $copy['assignee'] = (string)($teamNameMap[$assigneeId] ?? ''); // display name only
    $copy['_accessLevel'] = $level;
    $copy['_ownerSelf'] = $assigneeId !== '' && $assigneeId === (string)($user['team_member_id'] ?? '');
    return $copy;
}

function hippo_interaction_output_allowlist(string $level): array {
    return match ($level) {
        'edit' => ['id','customerId','date','channel','resultIds','note','nextFollowUp','duration','status','contactFor','route','currency','week','volume','price','value','analysis'],
        'call' => ['id','customerId','date','channel','resultIds','note','nextFollowUp','duration','status','contactFor','route','currency'],
        'view' => ['id','customerId','date','channel','resultIds','status','contactFor','route','currency'],
        default => [],
    };
}

function hippo_filter_interaction_fields_for_level(array $interaction, string $level, array $user, array $teamNameMap): array {
    $copy = hippo_pick_fields($interaction, hippo_interaction_output_allowlist($level));
    if (isset($copy['resultIds']) && is_array($copy['resultIds']) && in_array($level, ['view','call'], true)) {
        $allowed = array_fill_keys(hippo_call_result_ids(), true);
        $copy['resultIds'] = array_values(array_filter(array_map('strval', $copy['resultIds']), static fn($id) => isset($allowed[$id])));
    }
    if (array_key_exists('analysis', $copy)) $copy['analysis'] = hippo_sanitize_analysis($copy['analysis']);
    $memberId = (string)($interaction['memberId'] ?? '');
    $copy['memberId'] = (string)($teamNameMap[$memberId] ?? ''); // display name only
    $copy['_accessLevel'] = $level;
    $copy['_ownerSelf'] = $memberId !== '' && $memberId === (string)($user['team_member_id'] ?? '');
    return $copy;
}

function hippo_call_result_ids(): array {
    return [
        'price_high','competitor_lower','payment','quality','sample_requested','has_inventory',
        'decision_maker','bad_timing','transport','min_order','not_fit','quote_requested','follow_up','stop'
    ];
}

function hippo_filter_reply_library(array $library, array $user): array {
    if (hippo_can_view_full_state($user)) return array_values(array_filter($library, 'is_array'));
    $role = hippo_role_alias((string)($user['role'] ?? ''));
    $allowedIds = $role === 'center_call' ? array_fill_keys(hippo_call_result_ids(), true) : null;
    $out = [];
    foreach ($library as $item) {
        if (!is_array($item) || !isset($item['id'])) continue;
        $teamVisible = !empty($item['teamVisible']) || !empty($item['callCenterAllowed']);
        if ($allowedIds !== null && empty($allowedIds[(string)$item['id']]) && !$teamVisible) continue;
        $fields = $role === 'center_call'
            ? ['id','label','category','active','teamVisible','callCenterAllowed','handoffToAssignee']
            : ['id','label','category','response','action','stage','active','teamVisible','callCenterAllowed','handoffToAssignee'];
        $out[] = hippo_pick_fields($item, $fields);
    }
    return $out;
}

function hippo_active_team_result_ids(array $library, bool $callOnly): array {
    $out = [];
    foreach ($library as $item) {
        if (!is_array($item) || empty($item['active'])) continue;
        $id = trim((string)($item['id'] ?? ''));
        if ($id === '') continue;
        if ($callOnly && empty($item['teamVisible']) && empty($item['callCenterAllowed'])) continue;
        $out[$id] = true;
    }
    return array_keys($out);
}


function hippo_filter_form_config(array $config): array {
    $out = [];
    foreach (['customer','interaction'] as $entity) {
        $out[$entity] = [];
        foreach ((array)($config[$entity] ?? []) as $key => $field) {
            if (!is_array($field)) continue;
            $out[$entity][(string)$key] = hippo_pick_fields($field, ['label','enabled','required','locked','masterKey','roles']);
        }
    }
    return $out;
}

function hippo_form_field_catalog(): array {
    return [
        'customer' => [
            'name','phone','contact','industry','source','productGroup','consumptionType','packaging','currency','city','address',
            'assignee','stage','estimatedVolume','nextFollowUp','paymentPreference','competitor','technicalNeed','note','score'
        ],
        'interaction' => ['customer','channel','results','contactFor','route','currency','nextFollowUp','week','volume','price','member','note'],
    ];
}

function hippo_locked_form_fields(): array {
    return ['customer.name'=>true,'interaction.customer'=>true,'interaction.results'=>true];
}

function hippo_normalize_form_config_payload(array $config): array {
    $catalog = hippo_form_field_catalog();
    $locked = hippo_locked_form_fields();
    $out = [];
    foreach ($catalog as $entity => $keys) {
        $out[$entity] = [];
        foreach ($keys as $key) {
            $field = is_array($config[$entity][$key] ?? null) ? $config[$entity][$key] : [];
            $path = $entity . '.' . $key;
            $isLocked = isset($locked[$path]);
            $roles = [];
            foreach (['manager','marketer','center_call'] as $role) $roles[$role] = !array_key_exists($role, (array)($field['roles'] ?? [])) || !empty($field['roles'][$role]);
            if ($isLocked) $roles['manager'] = true;
            $item = [
                'label' => mb_substr(trim((string)($field['label'] ?? $key)), 0, 100),
                'enabled' => $isLocked ? true : !array_key_exists('enabled',$field) || !empty($field['enabled']),
                'required' => $isLocked ? true : !empty($field['required']),
                'roles' => $roles,
            ];
            if ($isLocked) $item['locked'] = true;
            $masterKey = trim((string)($field['masterKey'] ?? ''));
            if ($masterKey !== '' && in_array($masterKey, ['source','industry','productGroup','consumptionType','packaging','currency','contactFor','route'], true)) $item['masterKey'] = $masterKey;
            $out[$entity][$key] = $item;
        }
    }
    return $out;
}

function hippo_normalize_master_data_payload(array $data): array {
    $allowedKeys = ['source','industry','productGroup','consumptionType','packaging','currency','contactFor','route'];
    $out = [];
    foreach ($allowedKeys as $key) {
        $group = is_array($data[$key] ?? null) ? $data[$key] : [];
        $mode = (string)($group['addMode'] ?? 'manager_only');
        if (!in_array($mode, ['manager_only','approval','direct'], true)) $mode = 'manager_only';
        $seenIds = [];
        $seenLabels = [];
        $options = [];
        foreach (array_slice((array)($group['options'] ?? []), 0, 400) as $index => $option) {
            if (!is_array($option)) continue;
            $label = mb_substr(trim((string)($option['label'] ?? '')), 0, 100);
            if (mb_strlen($label) < 2) continue;
            $labelKey = mb_strtolower($label);
            if (isset($seenLabels[$labelKey])) continue;
            $rawId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($option['id'] ?? ''));
            $id = $rawId !== '' ? mb_substr($rawId, 0, 120) : 'base_' . $key . '_' . ($index + 1);
            if (isset($seenIds[$id])) $id .= '_' . ($index + 1);
            $status = (string)($option['status'] ?? (!empty($option['active']) ? 'active' : 'inactive'));
            if (!in_array($status, ['active','inactive','pending','rejected'], true)) $status = 'inactive';
            $item = [
                'id'=>$id,
                'label'=>$label,
                'active'=>$status === 'active',
                'status'=>$status,
                'system'=>!empty($option['system']),
            ];
            foreach (['createdBy','createdAt','reviewedBy','reviewedAt'] as $meta) {
                if (!empty($option[$meta])) $item[$meta] = mb_substr((string)$option[$meta], 0, 160);
            }
            $options[] = $item;
            $seenIds[$id] = true;
            $seenLabels[$labelKey] = true;
        }
        $out[$key] = [
            'label'=>mb_substr(trim((string)($group['label'] ?? $key)), 0, 100),
            'addMode'=>$mode,
            'options'=>$options,
        ];
    }
    return $out;
}

function hippo_filter_master_data(array $data, array $user): array {
    if (hippo_can_view_full_state($user)) return $data;
    $out = [];
    foreach ($data as $key => $group) {
        if (!is_array($group)) continue;
        $options = [];
        foreach ((array)($group['options'] ?? []) as $option) {
            if (!is_array($option) || empty($option['active']) || (string)($option['status'] ?? 'active') !== 'active') continue;
            $options[] = hippo_pick_fields($option, ['id','label','active','status','system']);
        }
        $out[(string)$key] = [
            'label' => (string)($group['label'] ?? $key),
            'addMode' => (string)($group['addMode'] ?? 'manager_only'),
            'options' => $options,
        ];
    }
    return $out;
}

function hippo_filter_state_with_access(array $full, array $user, array $accessRows): array {
    $role = hippo_role_alias((string)($user['role'] ?? ''));
    if ($role === 'manager_viewer') {
        $customers = is_array($full['customers'] ?? null) ? $full['customers'] : [];
        $interactions = is_array($full['interactions'] ?? null) ? $full['interactions'] : [];
        return [
            'version' => (int)($full['version'] ?? 2),
            'project' => ['name' => (string)($full['project']['name'] ?? 'دستیار فروش')],
            'summary' => [
                'customers' => count($customers),
                'interactions' => count($interactions),
                'open_followups' => count(array_filter($customers, static fn($c) => is_array($c) && !empty($c['nextFollowUp']))),
                'unassigned_customers' => count(array_filter($customers, static fn($c) => is_array($c) && empty($c['assignee']))),
            ],
        ];
    }

    if (hippo_can_view_full_state($user)) return $full;

    $team = (array)($full['team'] ?? []);
    $teamNameMap = hippo_team_name_map($team);
    $visible = [];
    $levels = [];
    $customers = [];
    foreach ((array)($full['customers'] ?? []) as $customer) {
        if (!is_array($customer) || !isset($customer['id'])) continue;
        $level = hippo_customer_level($user, $customer, $accessRows);
        if (hippo_access_rank($level) < 1) continue;
        $id = (string)$customer['id'];
        $visible[$id] = true;
        $levels[$id] = $level;
        $customers[] = hippo_filter_customer_fields_for_level($customer, $level, $user, $teamNameMap);
    }

    $interactions = [];
    foreach ((array)($full['interactions'] ?? []) as $interaction) {
        if (!is_array($interaction) || !isset($interaction['id'])) continue;
        $customerId = (string)($interaction['customerId'] ?? '');
        if (empty($visible[$customerId])) continue;
        $interactionLevel = hippo_interaction_level_for_user($user, $levels[$customerId]);
        $interactions[] = hippo_filter_interaction_fields_for_level($interaction, $interactionLevel, $user, $teamNameMap);
    }

    $memberId = (string)($user['team_member_id'] ?? '');
    $weeks = [];
    foreach ((array)($full['weeks'] ?? []) as $week) {
        if (!is_array($week)) continue;
        $allowedWeek = array_intersect_key($week, array_flip(['n','title','subtitle','goal','principle','status']));
        $tasks = (array)($week['tasks'] ?? []);
        if (!hippo_can($user, 'tasks.view_all')) {
            $tasks = array_values(array_filter($tasks, static fn($task) =>
                is_array($task) && $memberId !== '' && (string)($task['assignee'] ?? '') === $memberId
            ));
        }
        $allowedWeek['tasks'] = array_values(array_filter($tasks, 'is_array'));
        $allowedWeek['outputs'] = [];
        $allowedWeek['metrics'] = [];
        $weeks[] = $allowedWeek;
    }

    $weeklyReports = [];
    foreach ((array)($full['weeklyReports'] ?? []) as $report) {
        if (!is_array($report)) continue;
        if (hippo_can($user, 'reports.view_team')) $weeklyReports[] = $report;
        elseif ($memberId !== '' && (string)($report['memberId'] ?? '') === $memberId) $weeklyReports[] = $report;
    }

    $project = array_intersect_key((array)($full['project'] ?? []), array_flip(['name','startDate','activeWeek','analysisWeek','savedAt','lastSaved']));
    if ($memberId !== '') $project['currentMemberId'] = $memberId;
    $settings = array_intersect_key((array)($full['settings'] ?? []), array_flip(['crmView','customerSearch','customerStage','taskStatus']));

    return [
        'version' => (int)($full['version'] ?? 2),
        'project' => $project,
        'team' => hippo_minimal_team($team),
        'customers' => $customers,
        'interactions' => $interactions,
        'weeks' => $weeks,
        'weeklyReports' => $weeklyReports,
        'replyLibrary' => hippo_filter_reply_library((array)($full['replyLibrary'] ?? []), $user),
        'formConfig' => hippo_filter_form_config((array)($full['formConfig'] ?? [])),
        'masterData' => hippo_filter_master_data((array)($full['masterData'] ?? []), $user),
        'settings' => $settings,
    ];
}

function hippo_filter_state(PDO $pdo, array $full, array $user): array {
    return hippo_filter_state_with_access($full, $user, hippo_customer_access_rows($pdo, (int)$user['id']));
}

function hippo_index_by_id(array $rows): array {
    $out = [];
    foreach ($rows as $row) if (is_array($row) && isset($row['id'])) $out[(string)$row['id']] = $row;
    return $out;
}

function hippo_customer_edit_allowlist(array $user, string $level): array {
    if ($level === 'call') return ['contact','phone','phone2','nextFollowUp','status','updatedAt'];
    if ($level !== 'edit') return [];
    $fields = [
        'name','company','industry','city','province','contact','phone','phone2','source','stage',
        'estimatedVolume','technicalNeed','product','productGroup','consumptionType','packaging','currency','nextFollowUp','status','updatedAt'
    ];
    if (hippo_is_manager($user) && hippo_can($user, 'customers.assign')) $fields[] = 'assignee';
    return $fields;
}

function hippo_policy_record_event(string $code, array $metadata = []): void {
    if (!isset($GLOBALS['HIPPO_POLICY_EVENTS']) || !is_array($GLOBALS['HIPPO_POLICY_EVENTS'])) $GLOBALS['HIPPO_POLICY_EVENTS'] = [];
    $GLOBALS['HIPPO_POLICY_EVENTS'][] = ['code'=>$code,'metadata'=>$metadata];
}

function hippo_policy_take_events(): array {
    $events = isset($GLOBALS['HIPPO_POLICY_EVENTS']) && is_array($GLOBALS['HIPPO_POLICY_EVENTS']) ? $GLOBALS['HIPPO_POLICY_EVENTS'] : [];
    $GLOBALS['HIPPO_POLICY_EVENTS'] = [];
    return $events;
}

function hippo_value_is_effectively_empty(mixed $value): bool {
    if ($value === null || $value === '') return true;
    if (is_array($value)) return count(array_filter($value, static fn($v) => !hippo_value_is_effectively_empty($v))) === 0;
    return false;
}

function hippo_apply_customer_patch(array $current, array $incoming, array $user, string $level): array {
    $allowed = array_fill_keys(hippo_customer_edit_allowlist($user, $level), true);
    $ignored = ['id'=>true,'_accessLevel'=>true,'_ownerSelf'=>true,'assigneeName'=>true];
    // Scoped responses carry only the assignee display name. It is presentation data,
    // never an instruction to relink ownership unless the real manager has customers.assign.
    if (!(hippo_is_manager($user) && hippo_can($user, 'customers.assign'))) $ignored['assignee'] = true;
    $rejected = [];
    foreach ($incoming as $field => $value) {
        if (isset($ignored[$field])) continue;
        if (isset($allowed[$field])) {
            $current[$field] = $value;
            continue;
        }
        if (array_key_exists($field, $current) && $current[$field] !== $value && !hippo_value_is_effectively_empty($value)) $rejected[] = (string)$field;
    }
    if ($rejected) hippo_policy_record_event('sensitive_customer_field_access_denied', ['fields'=>$rejected,'access_level'=>$level]);
    return $current;
}

function hippo_sanitize_new_customer(array $candidate, array $user): array {
    $allowed = [
        'id','name','company','industry','city','province','contact','phone','phone2','source','stage',
        'estimatedVolume','technicalNeed','product','productGroup','consumptionType','packaging','currency','nextFollowUp','status','createdAt','updatedAt'
    ];
    $safe = hippo_pick_fields($candidate, $allowed);
    $safe['name'] = mb_substr(trim((string)($safe['name'] ?? '')), 0, 200);
    if ($safe['name'] === '') throw new HippoAuthorizationException('invalid_customer_name', 422);
    return $safe;
}

function hippo_valid_interaction_channel(string $channel): bool {
    return in_array($channel, ['call','whatsapp','meeting','sample','email','other'], true);
}

function hippo_validate_result_ids(mixed $value, array $allowed): array {
    if (!is_array($value)) throw new HippoAuthorizationException('invalid_interaction_results', 422);
    $allowedMap = array_fill_keys($allowed, true);
    $out = [];
    foreach ($value as $id) {
        $id = (string)$id;
        if ($id === '' || empty($allowedMap[$id])) throw new HippoAuthorizationException('interaction_type_rejected', 403, '', ['result_id'=>$id]);
        $out[$id] = true;
    }
    if (!$out) throw new HippoAuthorizationException('invalid_interaction_results', 422);
    return array_keys($out);
}

function hippo_interaction_allowed_fields(array $user, string $level, bool $existing = false): array {
    if ($level === 'call') return ['id','customerId','channel','date','resultIds','note','nextFollowUp','duration','status','contactFor','route','currency'];
    if ($level === 'edit') return [
        'id','customerId','channel','date','resultIds','note','nextFollowUp','duration','status','contactFor','route','currency','week',
        'volume','price','value','analysis'
    ];
    return [];
}

function hippo_sanitize_analysis(mixed $value): array {
    if (!is_array($value)) return [];
    $safe = hippo_pick_fields($value, ['summary','mainObstacle','buyingSignals','missingData','nextAction','confidence','suggestedResponse']);
    foreach (['summary','mainObstacle','nextAction','confidence','suggestedResponse'] as $key) {
        if (isset($safe[$key])) $safe[$key] = mb_substr((string)$safe[$key], 0, 2000);
    }
    foreach (['buyingSignals','missingData'] as $key) {
        if (isset($safe[$key]) && is_array($safe[$key])) $safe[$key] = array_slice(array_map(static fn($v)=>mb_substr((string)$v,0,300), $safe[$key]),0,30);
    }
    return $safe;
}

function hippo_sanitize_interaction_payload(array $incoming, array $user, string $level, array $replyLibrary = []): array {
    if (hippo_access_rank($level) < 2) throw new HippoAuthorizationException('interaction_call_access_required', 403);
    $allowedFields = array_fill_keys(hippo_interaction_allowed_fields($user, $level), true);
    $rejected = [];
    foreach ($incoming as $field => $value) {
        if (in_array($field, ['memberId','_accessLevel','_ownerSelf'], true)) continue;
        if (!isset($allowedFields[$field]) && !hippo_value_is_effectively_empty($value)) $rejected[] = (string)$field;
    }
    if ($rejected) throw new HippoAuthorizationException('interaction_field_rejected', 403, '', ['fields'=>$rejected,'access_level'=>$level]);

    $safe = hippo_pick_fields($incoming, array_keys($allowedFields));
    $safe['id'] = mb_substr((string)($safe['id'] ?? ''), 0, 120);
    $safe['customerId'] = mb_substr((string)($safe['customerId'] ?? ''), 0, 120);
    if ($safe['id'] === '' || $safe['customerId'] === '') throw new HippoAuthorizationException('invalid_interaction', 422);
    $channel = (string)($safe['channel'] ?? 'call');
    if (!hippo_valid_interaction_channel($channel)) throw new HippoAuthorizationException('invalid_interaction_channel', 422);
    $safe['channel'] = $channel;
    $allowedResults = $level === 'call' ? hippo_call_result_ids() : array_merge(hippo_call_result_ids(), ['trial_order','purchase']);
    $allowedResults = array_values(array_unique(array_merge(
        $allowedResults,
        hippo_active_team_result_ids($replyLibrary, $level === 'call')
    )));
    $safe['resultIds'] = hippo_validate_result_ids($safe['resultIds'] ?? [], $allowedResults);
    $safe['date'] = mb_substr((string)($safe['date'] ?? date(DATE_ATOM)), 0, 40);
    $safe['note'] = mb_substr((string)($safe['note'] ?? ''), 0, 2000);
    $safe['nextFollowUp'] = mb_substr((string)($safe['nextFollowUp'] ?? ''), 0, 20);
    $safe['status'] = mb_substr((string)($safe['status'] ?? ''), 0, 40);
    foreach (['contactFor','route','currency'] as $textField) if (isset($safe[$textField])) $safe[$textField] = mb_substr(trim((string)$safe[$textField]), 0, 160);
    if (isset($safe['duration'])) $safe['duration'] = max(0, min(86400, (int)$safe['duration']));
    if (isset($safe['week'])) $safe['week'] = max(1, min(53, (int)$safe['week']));
    foreach (['volume','price','value'] as $numeric) if (isset($safe[$numeric])) $safe[$numeric] = max(0, (float)$safe[$numeric]);
    if (array_key_exists('analysis', $safe)) $safe['analysis'] = hippo_sanitize_analysis($safe['analysis']);
    return $safe;
}

function hippo_patch_existing_interaction(array $current, array $incoming, array $user, string $level, array $replyLibrary = []): array {
    $safe = hippo_sanitize_interaction_payload($incoming, $user, $level, $replyLibrary);
    if ((string)($safe['customerId'] ?? '') !== (string)($current['customerId'] ?? '')) {
        throw new HippoAuthorizationException('interaction_customer_immutable', 403);
    }
    $immutable = ['id','customerId','memberId','fulfillment','orderValue','orderQuantity','payment','purchase','trialOrder','managerDecision'];
    foreach ($immutable as $field) if (array_key_exists($field, $current)) $safe[$field] = $current[$field];
    $safe['id'] = $current['id'];
    $safe['customerId'] = $current['customerId'];
    $safe['memberId'] = $current['memberId'] ?? '';
    // Preserve every server-managed or legacy field not explicitly editable.
    foreach ($current as $field => $value) if (!array_key_exists($field, $safe)) $safe[$field] = $value;
    return $safe;
}

function hippo_validate_full_state_payload(array $incoming): void {
    foreach (['customers','interactions'] as $collection) {
        if (isset($incoming[$collection]) && !is_array($incoming[$collection])) throw new HippoAuthorizationException('invalid_state', 422);
    }
    foreach ((array)($incoming['customers'] ?? []) as $customer) {
        if (!is_array($customer) || trim((string)($customer['id'] ?? '')) === '') throw new HippoAuthorizationException('invalid_customer', 422);
    }
    foreach ((array)($incoming['interactions'] ?? []) as $interaction) {
        if (!is_array($interaction) || trim((string)($interaction['id'] ?? '')) === '' || trim((string)($interaction['customerId'] ?? '')) === '') {
            throw new HippoAuthorizationException('invalid_interaction', 422);
        }
    }
}

function hippo_normalize_full_state_payload(array $current, array $incoming, array $user): array {
    hippo_validate_full_state_payload($incoming);
    $incoming['formConfig'] = hippo_normalize_form_config_payload((array)($incoming['formConfig'] ?? $current['formConfig'] ?? []));
    $incoming['masterData'] = hippo_normalize_master_data_payload((array)($incoming['masterData'] ?? $current['masterData'] ?? []));
    $memberId = (string)($user['team_member_id'] ?? '');
    $replyLibrary = (array)($incoming['replyLibrary'] ?? $current['replyLibrary'] ?? []);
    $currentInteractions = hippo_index_by_id((array)($current['interactions'] ?? []));
    $normalized = [];

    foreach ((array)($incoming['interactions'] ?? []) as $row) {
        $id = (string)($row['id'] ?? '');
        if (isset($currentInteractions[$id])) {
            $existing = $currentInteractions[$id];
            if ((string)($row['customerId'] ?? '') !== (string)($existing['customerId'] ?? '')) {
                throw new HippoAuthorizationException('interaction_customer_immutable', 403);
            }
            // The browser never controls interaction ownership. Existing ownership is
            // retained even for full-state managers; new ownership is taken from session.
            $row['memberId'] = (string)($existing['memberId'] ?? '');
            $normalized[] = $row;
            continue;
        }

        $safe = hippo_sanitize_interaction_payload($row, $user, 'edit', $replyLibrary);
        $safe['id'] = $id;
        $safe['customerId'] = (string)($row['customerId'] ?? '');
        $safe['memberId'] = $memberId;
        $normalized[] = $safe;
    }

    $incoming['interactions'] = $normalized;
    return $incoming;
}

function hippo_scoped_merge_with_access(array $current, array $incoming, array $user, array $accessRows): array {
    hippo_require_operational_account($user);
    if (hippo_can_save_full_state($user)) {
        return hippo_normalize_full_state_payload($current, $incoming, $user);
    }

    $memberId = (string)($user['team_member_id'] ?? '');
    $replyLibrary = (array)($current['replyLibrary'] ?? []);
    $handoffTasks = [];
    $currentCustomers = hippo_index_by_id((array)($current['customers'] ?? []));
    $incomingCustomers = hippo_index_by_id((array)($incoming['customers'] ?? []));
    $phoneOwners = [];
    foreach ($currentCustomers as $id => $customer) {
        $phone = hippo_normalize_phone_server($customer['phone'] ?? '');
        if ($phone !== '') $phoneOwners[$phone] = $id;
    }

    $mergedCustomers = $currentCustomers;
    foreach ($incomingCustomers as $id => $candidate) {
        if (isset($currentCustomers[$id])) {
            $level = hippo_customer_level($user, $currentCustomers[$id], $accessRows);
            if (hippo_access_rank($level) < 1) continue;
            if (hippo_access_rank($level) < 2) {
                $filtered = hippo_filter_customer_fields_for_level($currentCustomers[$id], $level, $user, hippo_team_name_map((array)($current['team'] ?? [])));
                foreach ($candidate as $field=>$value) {
                    if (in_array($field, ['_accessLevel','_ownerSelf'], true)) continue;
                    if (array_key_exists($field,$filtered) && $filtered[$field] !== $value) {
                        throw new HippoAuthorizationException('customer_edit_forbidden',403,'',['field'=>$field]);
                    }
                }
                continue;
            }
            $patched = hippo_apply_customer_patch($currentCustomers[$id], $candidate, $user, $level);
            if (!(hippo_is_manager($user) && hippo_can($user, 'customers.assign'))) $patched['assignee'] = $currentCustomers[$id]['assignee'] ?? '';
            $patched['id'] = $id;
            $mergedCustomers[$id] = $patched;
            continue;
        }

        hippo_require_permission($user, 'customers.create');
        if ($memberId === '') throw new HippoAuthorizationException('team_member_required', 422);
        $candidate = hippo_sanitize_new_customer($candidate, $user);
        $phone = hippo_normalize_phone_server($candidate['phone'] ?? '');
        if ($phone !== '' && isset($phoneOwners[$phone])) {
            $existing = $currentCustomers[$phoneOwners[$phone]];
            if (!hippo_customer_is_visible($user, $existing, $accessRows)) throw new HippoAuthorizationException('customer_exists_forbidden', 403);
            throw new HippoAuthorizationException('duplicate_customer', 409);
        }
        $candidate['id'] = $id;
        $candidate['assignee'] = $memberId;
        $mergedCustomers[$id] = $candidate;
        if ($phone !== '') $phoneOwners[$phone] = $id;
    }
    // Missing customers are retained; scoped users cannot delete by omission.
    $result = $current;
    $result['customers'] = array_values($mergedCustomers);

    $currentInteractions = hippo_index_by_id((array)($current['interactions'] ?? []));
    $incomingInteractions = hippo_index_by_id((array)($incoming['interactions'] ?? []));
    $mergedInteractions = $currentInteractions;
    foreach ($incomingInteractions as $id => $interaction) {
        $customerId = (string)($interaction['customerId'] ?? ($currentInteractions[$id]['customerId'] ?? ''));
        $customer = $mergedCustomers[$customerId] ?? null;
        $level = $customer ? hippo_customer_level($user, $customer, $accessRows) : '';
        $interactionLevel = hippo_interaction_level_for_user($user, $level);
        if (!$customer || hippo_access_rank($level) < 1) {
            if (!isset($currentInteractions[$id])) throw new HippoAuthorizationException('interaction_customer_forbidden', 403);
            continue;
        }
        if (!isset($currentInteractions[$id])) {
            hippo_require_permission($user, 'interactions.create');
            $safe = hippo_sanitize_interaction_payload($interaction, $user, $interactionLevel, $replyLibrary);
            $safe['id'] = $id;
            $safe['customerId'] = $customerId;
            $safe['memberId'] = $memberId;
            $mergedInteractions[$id] = $safe;
            if (hippo_role_alias((string)($user['role'] ?? '')) === 'center_call') {
                $assignee = trim((string)($customer['assignee'] ?? ''));
                if ($assignee !== '' && $assignee !== $memberId) {
                    $labels = [];
                    $byResultId = [];
                    foreach ($replyLibrary as $reply) {
                        if (is_array($reply) && isset($reply['id'])) $byResultId[(string)$reply['id']] = (string)($reply['label'] ?? $reply['id']);
                    }
                    foreach ((array)($safe['resultIds'] ?? []) as $resultId) $labels[] = $byResultId[(string)$resultId] ?? (string)$resultId;
                    $weekNo = max(1, min(53, (int)($current['project']['activeWeek'] ?? 1)));
                    $handoffTasks[] = [
                        'week' => $weekNo,
                        'task' => [
                            'id' => 'handoff_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id),
                            'text' => 'پیگیری نتیجه تماس «' . mb_substr((string)($customer['name'] ?? 'مشتری'), 0, 120) . '»: ' . mb_substr(implode('، ', $labels), 0, 280),
                            'status' => 'not_started',
                            'assignee' => $assignee,
                            'note' => 'ارجاع خودکار از مرکز تماس' . (!empty($safe['nextFollowUp']) ? '؛ پیگیری: ' . (string)$safe['nextFollowUp'] : ''),
                            'custom' => true,
                            'fromManager' => false,
                            'fromCallCenter' => true,
                            'sourceInteractionId' => $id,
                            'priority' => 'normal',
                            'createdAt' => date('c'),
                        ],
                    ];
                }
            }
            continue;
        }
        $existing = $currentInteractions[$id];
        $projectedExisting = hippo_filter_interaction_fields_for_level(
            $existing,
            $interactionLevel,
            $user,
            hippo_team_name_map((array)($current['team'] ?? []))
        );
        // A scoped browser sends the authorized projection, not the hidden server fields.
        // Treat an unchanged projection as a no-op so other users' hidden interactions
        // do not become accidental edit attempts.
        if ($interaction == $existing || $interaction == $projectedExisting) continue;
        if (
            hippo_access_rank($interactionLevel) >= 2
            && hippo_can($user, 'interactions.edit_own')
            && $memberId !== ''
            && (string)($existing['memberId'] ?? '') === $memberId
        ) {
            $mergedInteractions[$id] = hippo_patch_existing_interaction($existing, $interaction, $user, $interactionLevel, $replyLibrary);
        } else {
            throw new HippoAuthorizationException('interaction_edit_forbidden', 403);
        }
    }
    // Missing interactions are retained; non-manager deletion is disabled in V05.2.
    $result['interactions'] = array_values($mergedInteractions);

    $incomingWeeks = (array)($incoming['weeks'] ?? []);
    $resultWeeks = (array)($current['weeks'] ?? []);
    foreach ($resultWeeks as $wi => $week) {
        if (!is_array($week)) continue;
        $currentTasks = hippo_index_by_id((array)($week['tasks'] ?? []));
        $incomingTasks = hippo_index_by_id((array)($incomingWeeks[$wi]['tasks'] ?? []));
        foreach ($incomingTasks as $taskId => $task) {
            if (isset($currentTasks[$taskId])) {
                if ((string)($currentTasks[$taskId]['assignee'] ?? '') !== $memberId) continue;
                $currentTasks[$taskId]['status'] = $task['status'] ?? $currentTasks[$taskId]['status'] ?? 'not_started';
                $currentTasks[$taskId]['note'] = mb_substr((string)($task['note'] ?? $currentTasks[$taskId]['note'] ?? ''),0,2000);
            } elseif (hippo_can($user, 'tasks.create_personal') && $memberId !== '') {
                $task['id'] = $taskId;
                $task['assignee'] = $memberId;
                $task['fromManager'] = false;
                $currentTasks[$taskId] = $task;
            }
        }
        $resultWeeks[$wi]['tasks'] = array_values($currentTasks);
    }
    foreach ($handoffTasks as $handoff) {
        $idx = max(0, (int)$handoff['week'] - 1);
        if (!isset($resultWeeks[$idx]) || !is_array($resultWeeks[$idx])) continue;
        if (!isset($resultWeeks[$idx]['tasks']) || !is_array($resultWeeks[$idx]['tasks'])) $resultWeeks[$idx]['tasks'] = [];
        $exists = false;
        foreach ($resultWeeks[$idx]['tasks'] as $task) {
            if (is_array($task) && (string)($task['sourceInteractionId'] ?? '') === (string)$handoff['task']['sourceInteractionId']) { $exists = true; break; }
        }
        if (!$exists) $resultWeeks[$idx]['tasks'][] = $handoff['task'];
    }
    $result['weeks'] = $resultWeeks;
    return $result;
}

function hippo_scoped_merge(PDO $pdo, array $current, array $incoming, array $user): array {
    return hippo_scoped_merge_with_access($current, $incoming, $user, hippo_customer_access_rows($pdo, (int)$user['id']));
}
