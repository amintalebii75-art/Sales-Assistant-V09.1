<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/permissions.php';

const HIPPO_IDLE_TIMEOUT = 60 * 60 * 8;       // 8 hours
const HIPPO_ABSOLUTE_LIFETIME = 60 * 60 * 24 * 30; // 30 days


function hippo_send_private_no_store_headers(): void {
    if (headers_sent()) return;
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function hippo_session_cookie_path(): string {
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '.' || $dir === '/' || $dir === '\\') return '/';
    return '/' . trim($dir, '/') . '/';
}

function hippo_session_start(): void {
    hippo_send_private_no_store_headers();
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('HIPPOSESSID');
    session_set_cookie_params([
        'lifetime' => HIPPO_ABSOLUTE_LIFETIME,
        'path' => hippo_session_cookie_path(),
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function hippo_destroy_session(): void {
    hippo_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function hippo_session_expired(): bool {
    $now = time();
    $started = (int)($_SESSION['auth_started_at'] ?? $now);
    $last = (int)($_SESSION['last_activity_at'] ?? $now);
    return ($now - $last > HIPPO_IDLE_TIMEOUT) || ($now - $started > HIPPO_ABSOLUTE_LIFETIME);
}

function hippo_team_member_exists(PDO $pdo, ?string $teamMemberId): bool {
    $teamMemberId = trim((string)$teamMemberId);
    if ($teamMemberId === '') return false;
    try {
        $row = $pdo->query('SELECT data FROM app_state WHERE id=1')->fetch();
        $state = $row ? json_decode((string)$row['data'], true) : [];
        foreach ((array)($state['team'] ?? []) as $member) {
            if (!is_array($member)) continue;
            if ((string)($member['id'] ?? '') !== $teamMemberId) continue;
            return ($member['active'] ?? true) !== false;
        }
    } catch (Throwable $e) {
        error_log('Team member validation failed: ' . $e->getMessage());
    }
    return false;
}

function hippo_fetch_current_db_user(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare(
        'SELECT id, username, display_name, role, status, team_member_id, last_login_at, locked_until,
                password_changed_at, must_change_password, rbac_review_required, created_at, updated_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function hippo_current_user(): ?array {
    hippo_session_start();
    if (empty($_SESSION['user_id'])) return null;
    if (hippo_session_expired()) {
        hippo_destroy_session();
        return null;
    }

    try {
        $pdo = hippo_db();
        $row = hippo_fetch_current_db_user($pdo, (int)$_SESSION['user_id']);
    } catch (Throwable $e) {
        error_log('Session refresh failed: ' . $e->getMessage());
        return null;
    }
    if (!$row) {
        hippo_destroy_session();
        return null;
    }

    $status = (string)($row['status'] ?? 'inactive');
    $lockedUntil = $row['locked_until'] ?? null;
    if ($status !== 'active' || ($lockedUntil && strtotime((string)$lockedUntil) > time())) {
        hippo_audit($pdo, (int)$row['id'], 'session_rejected', 'user', (string)$row['id'], 'denied', ['status' => $status]);
        hippo_destroy_session();
        return null;
    }

    $dbPasswordChanged = (string)($row['password_changed_at'] ?? '');
    $sessionPasswordChanged = (string)($_SESSION['password_changed_at'] ?? '');
    if ($sessionPasswordChanged !== '' && $dbPasswordChanged !== '' && $dbPasswordChanged !== $sessionPasswordChanged) {
        hippo_audit($pdo, (int)$row['id'], 'session_revoked_password_change', 'user', (string)$row['id']);
        hippo_destroy_session();
        return null;
    }

    $role = hippo_role_alias((string)$row['role']);
    $teamMemberValid = !hippo_role_requires_team_member($role) || hippo_team_member_exists($pdo, $row['team_member_id'] !== null ? (string)$row['team_member_id'] : null);
    $permissions = hippo_load_permissions($pdo, (int)$row['id'], $role, $status);
    $permissionFingerprint = hippo_permission_fingerprint([
        'id' => (int)$row['id'],
        'role' => $role,
        'status' => $status,
        'team_member_id' => $row['team_member_id'] !== null ? (string)$row['team_member_id'] : null,
        'rbac_review_required' => (bool)($row['rbac_review_required'] ?? false),
        'team_member_valid' => $teamMemberValid,
        'permissions' => $permissions,
    ]);
    $_SESSION['username'] = (string)$row['username'];
    $_SESSION['display_name'] = (string)$row['display_name'];
    $_SESSION['role'] = $role;
    $_SESSION['team_member_id'] = $row['team_member_id'] !== null ? (string)$row['team_member_id'] : null;
    $_SESSION['last_activity_at'] = time();

    return [
        'id' => (int)$row['id'],
        'username' => (string)$row['username'],
        'display_name' => (string)$row['display_name'],
        'role' => $role,
        'role_label' => hippo_role_label($role),
        'status' => $status,
        'team_member_id' => $row['team_member_id'] !== null ? (string)$row['team_member_id'] : null,
        'last_login_at' => $row['last_login_at'] ?? ($_SESSION['last_login_at'] ?? null),
        'must_change_password' => (bool)($row['must_change_password'] ?? false),
        'permissions' => $permissions,
        'permission_fingerprint' => $permissionFingerprint,
        'rbac_review_required' => (bool)($row['rbac_review_required'] ?? false),
        'team_member_valid' => $teamMemberValid,
        'scope_version' => HIPPO_SCOPE_VERSION,
        'csrf_token' => hippo_csrf_token(),
    ];
}

function hippo_require_login_page(): array {
    $u = hippo_current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!hippo_operational_account_ready($u) && !in_array($script, ['account_review.php','logout.php'], true)) {
        header('Location: account_review.php');
        exit;
    }
    if (!empty($u['must_change_password']) && !in_array($script, ['change_password.php','account_review.php','logout.php'], true)) {
        header('Location: change_password.php');
        exit;
    }
    return $u;
}

function hippo_require_login_api(): array {
    $u = hippo_current_user();
    if (!$u) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'not_logged_in'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!hippo_operational_account_ready($u)) {
        try { hippo_audit(hippo_db(), (int)$u['id'], 'operational_account_blocked_without_team_member', 'user', (string)$u['id'], 'denied'); } catch (Throwable $e) {}
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'operational_account_review_required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($u['must_change_password'])) {
        http_response_code(428);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'password_change_required'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    return $u;
}

function hippo_login_session(array $user): void {
    hippo_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = (string)$user['username'];
    $_SESSION['display_name'] = (string)$user['display_name'];
    $_SESSION['role'] = hippo_role_alias((string)$user['role']);
    $_SESSION['team_member_id'] = $user['team_member_id'] ?? null;
    $_SESSION['password_changed_at'] = (string)($user['password_changed_at'] ?? '');
    $_SESSION['last_login_at'] = $user['last_login_at'] ?? date('Y-m-d H:i:s');
    $_SESSION['auth_started_at'] = time();
    $_SESSION['last_activity_at'] = time();
    unset($_SESSION['csrf_token']);
    hippo_csrf_token();
}
