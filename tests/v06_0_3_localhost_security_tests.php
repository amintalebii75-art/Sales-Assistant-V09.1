<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$results = [];
$check = static function (string $name, bool $ok, string $detail = '') use (&$results): void {
    $results[] = ['name'=>$name, 'status'=>$ok?'PASS':'FAIL', 'detail'=>$detail];
};
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);

$forbidden = ['add.php','add_team.php','ajax_add_team.php','diag.php','final.php','fix.php','force_db.php','go.php','config.php'];
$present = array_values(array_filter($forbidden, static fn(string $f): bool => file_exists($root.'/'.$f)));
$check('unsafe staging helpers removed', $present === [], $present ? implode(', ', $present) : 'none present');

$usersPage = $read('users.php');
$usersApi = $read('users_api.php');
$usersJs = $read('assets/js/users-rbac.js');
$htaccess = $read('.htaccess');

$check('users page has no pre-auth state mutation', !str_contains($usersPage, 'fast_add_ajax') && !str_contains($usersPage, 'UPDATE app_state'), 'no direct write path');
$check('custom roles are not created from UI', !str_contains($usersPage, 'custom_roles') && !str_contains($usersJs, 'custom_roles'), 'fixed server roles preserved');
$check('team creation uses official API action', str_contains($usersApi, "action==='create_team_member'") && str_contains($usersJs, "api('create_team_member'"), 'API and UI wired');
$check('team creation requires real manager permission', str_contains($usersApi, "ua_require_manager_sensitive(\$user,'permissions.manage')"), 'manager + permissions.manage');
$check('team creation uses CSRF-protected POST flow', str_contains($usersApi, '$body=ua_body();hippo_require_csrf($body);'), 'global POST CSRF guard');
$check('team creation uses optimistic locking', str_contains($usersApi, 'ua_expected_revision($body)') && str_contains($usersApi, 'FOR UPDATE') && str_contains($usersApi, 'revision_conflict'), 'expected revision + row lock');
$check('team creation records backup', str_contains($usersApi, "'team_member_create'"), 'backup operation registered');
$check('team creation records audit', str_contains($usersApi, "'team_member_created'"), 'audit event registered');
$auth = $read('auth.php');
$planningApi = $read('planning_api.php');
$check('inactive team member is not valid for login scope', str_contains($auth, "(\$member['active'] ?? true) !== false"), 'inactive member rejected');
$check('session cookie is isolated and hardened', str_contains($auth, "session_name('HIPPOSESSID')") && str_contains($auth, "session.use_strict_mode") && str_contains($auth, 'hippo_session_cookie_path()'), 'custom name + strict mode + app path');
$login = $read('login.php');
$check('login POST is CSRF protected', str_contains($login, 'hippo_verify_csrf($_POST)') && str_contains($login, 'name="csrf_token"'), 'login form token + server verification');
$createAdminPath = $root . '/create_admin.php';
if (file_exists($createAdminPath)) {
    $createAdmin = $read('create_admin.php');
    $check('initial admin creation is CSRF protected', str_contains($createAdmin, 'hippo_verify_csrf($_POST)') && str_contains($createAdmin, 'name="csrf_token"'), 'setup token + CSRF required');
} else {
    $check('initial admin creation is CSRF protected', true, 'create_admin.php intentionally omitted from release');
}
$check('planning excludes invalid team members', str_contains($planningApi, 'hippo_team_member_exists($pdo, $teamMemberId)'), 'eligible recipients require active team record');
$check('planning accepts initial revision zero', str_contains($planningApi, "'min_range' => 0") && !str_contains($planningApi, 'return planning_id($body[\'expected_revision\'] ?? 0'), 'first update can use revision 0');
$check('nonexistent team manager link removed', !str_contains($usersJs, 'team_manager.php'), 'no dead link');
$check('customer access rendering typo removed', !str_contains($usersJs, 'customers.comap'), 'customers.map used');
$check('localhost config sample exists', file_exists($root.'/config.localhost.sample.php'), 'sample present; real config absent');
$check('test and evidence web access blocked', str_contains($htaccess, 'tests|TEST-EVIDENCE') && file_exists($root.'/tests/.htaccess') && file_exists($root.'/TEST-EVIDENCE/.htaccess'), 'root rule + directory deny files present');
$check('sensitive file extensions blocked', str_contains($htaccess, 'config\\.php') && str_contains($htaccess, '\\.(sql|md|json|txt|log|ini|env)'), 'FilesMatch deny rule present');

$fail = count(array_filter($results, static fn(array $r): bool => $r['status']==='FAIL'));
echo json_encode([
    'suite'=>'V06.0.3 Localhost Security Regression',
    'generated_at'=>date(DATE_ATOM),
    'pass'=>count($results)-$fail,
    'fail'=>$fail,
    'results'=>$results,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($fail === 0 ? 0 : 1);
