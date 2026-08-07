<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
$user = hippo_require_login_api();
$pdo = hippo_db();
$action = (string)($_GET['action'] ?? 'bootstrap');
$body = [];

function ua_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ua_body(int $max = 250_000): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > $max) ua_json(['ok'=>false,'error'=>'bad_payload'],400);
    $decoded = json_decode($raw,true);
    if (!is_array($decoded)) ua_json(['ok'=>false,'error'=>'invalid_json'],400);
    return $decoded;
}

function ua_username(string $value): string {
    $value = strtolower(trim($value));
    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $value)) throw new HippoAuthorizationException('invalid_username',422);
    return $value;
}

function ua_password(?string $value, bool $generate = false): array {
    if ($generate) {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $password = '';
        for ($i=0;$i<16;$i++) $password .= $alphabet[random_int(0, strlen($alphabet)-1)];
        return [$password, true];
    }
    $password = (string)$value;
    if (mb_strlen($password) < 10) throw new HippoAuthorizationException('weak_password',422);
    return [$password, false];
}

function ua_full_state(PDO $pdo): array {
    $row = $pdo->query('SELECT data FROM app_state WHERE id=1')->fetch();
    $state = $row ? json_decode((string)$row['data'],true) : [];
    return is_array($state) ? $state : [];
}

function ua_state_row(PDO $pdo, bool $forUpdate = false): array {
    $sql = 'SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1' . ($forUpdate ? ' FOR UPDATE' : '');
    $row = $pdo->query($sql)->fetch();
    if (!$row) {
        return ['data'=>[], 'raw'=>'{}', 'revision'=>0, 'updated_at'=>null, 'updated_by'=>null];
    }
    $decoded = json_decode((string)$row['data'], true);
    return [
        'data'=>is_array($decoded) ? $decoded : [],
        'raw'=>(string)$row['data'],
        'revision'=>(int)$row['revision'],
        'updated_at'=>$row['updated_at'] ?? null,
        'updated_by'=>$row['updated_by'] ?? null,
    ];
}

function ua_expected_revision(array $body): int {
    if (!array_key_exists('expected_revision', $body) || !is_numeric($body['expected_revision'])) {
        throw new HippoAuthorizationException('missing_revision', 400);
    }
    $revision = (int)$body['expected_revision'];
    if ($revision < 0) throw new HippoAuthorizationException('invalid_revision', 400);
    return $revision;
}

function ua_team_member_name(string $value): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '' || mb_strlen($value) < 2 || mb_strlen($value) > 120) {
        throw new HippoAuthorizationException('invalid_team_member_name', 422);
    }
    return $value;
}

function ua_team_member_role(string $value): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if (mb_strlen($value) > 120) throw new HippoAuthorizationException('invalid_team_member_role', 422);
    return $value;
}

function ua_prune_backups(PDO $pdo): void {
    $pdo->exec('DELETE FROM app_state_backups WHERE id NOT IN (SELECT id FROM (SELECT id FROM app_state_backups ORDER BY id DESC LIMIT 20) t)');
}

function ua_team(array $state): array {
    $out=[];
    foreach ((array)($state['team']??[]) as $m) {
        if (!is_array($m) || !isset($m['id']) || ($m['active'] ?? true) === false) continue;
        $out[]=['id'=>(string)$m['id'],'name'=>(string)($m['name']??''),'role'=>(string)($m['role']??''),'active'=>true];
    }
    return $out;
}

function ua_validate_team_member(array $state, ?string $teamMemberId): void {
    if ($teamMemberId === null || $teamMemberId === '') return;
    foreach ((array)($state['team'] ?? []) as $member) {
        if (!is_array($member) || (string)($member['id'] ?? '') !== $teamMemberId) continue;
        if (($member['active'] ?? true) !== false) return;
        break;
    }
    throw new HippoAuthorizationException('team_member_not_found', 422);
}

function ua_assert_team_link(PDO $pdo, ?string $teamMemberId, ?int $ignoreUserId = null, string $status = 'active'): void {
    if ($teamMemberId === null || $teamMemberId === '' || $status !== 'active') return;
    $sql='SELECT id FROM users WHERE team_member_id=? AND status=\'active\'';
    $args=[$teamMemberId];
    if ($ignoreUserId !== null) { $sql.=' AND id<>?'; $args[]=$ignoreUserId; }
    $sql.=' LIMIT 1';
    $stmt=$pdo->prepare($sql);$stmt->execute($args);
    if ($stmt->fetch()) throw new HippoAuthorizationException('team_member_already_linked',409);
}

function ua_apply_operational_review(string $role, string $status, ?string $teamMemberId): array {
    $role = hippo_role_alias($role);
    if (!hippo_role_requires_team_member($role)) return [$status, 0];
    if ($teamMemberId === null || $teamMemberId === '') return ['inactive', 1];
    return [$status, 0];
}

function ua_active_manager_count(PDO $pdo, ?int $exclude = null): int {
    $sql="SELECT COUNT(*) c FROM users WHERE role='manager' AND status='active'";$args=[];
    if ($exclude!==null){$sql.=' AND id<>?';$args[]=$exclude;}
    $stmt=$pdo->prepare($sql);$stmt->execute($args);$row=$stmt->fetch();
    return (int)($row['c']??0);
}

function ua_user_row(PDO $pdo, int $id): array {
    $stmt=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');$stmt->execute([$id]);$row=$stmt->fetch();
    if(!$row) throw new HippoAuthorizationException('user_not_found',404);
    return $row;
}

function ua_require_manager_sensitive(array $actor, string $permission = 'permissions.manage'): void {
    hippo_require_manager_permission($actor,$permission);
}

function ua_assert_role_grantable(array $actor, string $role): void {
    $role = hippo_role_alias($role);
    foreach (hippo_default_permissions_for_role($role) as $key=>$allowed) {
        if ($allowed && !hippo_can($actor,$key)) throw new HippoAuthorizationException('role_permission_exceeds_actor',403);
    }
}

function ua_public_users(PDO $pdo, array $state): array {
    $rows=$pdo->query('SELECT id,username,display_name,role,status,team_member_id,rbac_review_required,last_login_at,failed_attempts,locked_until,password_changed_at,must_change_password,created_by,created_at,updated_at FROM users ORDER BY id')->fetchAll();
    $owned=[];foreach((array)($state['customers']??[]) as $c){$a=(string)($c['assignee']??'');if($a!=='')$owned[$a]=($owned[$a]??0)+1;}
    $shared=[];foreach($pdo->query('SELECT user_id,COUNT(*) c FROM customer_access GROUP BY user_id')->fetchAll() as $r)$shared[(int)$r['user_id']]=(int)$r['c'];
    $out=[];
    foreach($rows as $r){$id=(int)$r['id'];$role=hippo_role_alias((string)$r['role']);$out[]=[
        'id'=>$id,'username'=>(string)$r['username'],'display_name'=>(string)$r['display_name'],'role'=>$role,'role_label'=>hippo_role_label($role),
        'status'=>(string)$r['status'],'team_member_id'=>$r['team_member_id'],'rbac_review_required'=>(bool)$r['rbac_review_required'],
        'last_login_at'=>$r['last_login_at'],'failed_attempts'=>(int)$r['failed_attempts'],'locked_until'=>$r['locked_until'],
        'must_change_password'=>(bool)$r['must_change_password'],'created_by'=>$r['created_by']!==null?(int)$r['created_by']:null,
        'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at'],
        'owned_customers'=>(int)($owned[(string)($r['team_member_id']??'')]??0),'shared_customers'=>(int)($shared[$id]??0),
    ];}
    return $out;
}

function ua_permission_payload(PDO $pdo, array $actor): array {
    ua_require_manager_sensitive($actor,'permissions.manage');
    $overrides=[];
    $stmt=$pdo->query('SELECT user_id,permission_key,allowed FROM user_permission_overrides ORDER BY user_id,permission_key');
    foreach($stmt->fetchAll() as $row){$uid=(int)$row['user_id'];$overrides[$uid][(string)$row['permission_key']]=(bool)$row['allowed'];}
    $grantable=[];foreach(hippo_permission_keys() as $key) if(hippo_can($actor,$key)) $grantable[]=$key;
    return ['roles'=>array_map(static fn($r)=>['id'=>$r,'label'=>hippo_role_label($r),'defaults'=>hippo_default_permissions_for_role($r)],hippo_valid_roles()),'permission_keys'=>$grantable,'overrides'=>$overrides];
}

function ua_customer_access_payload(PDO $pdo, array $state): array {
    $customerMap=[];foreach((array)($state['customers']??[]) as $c)if(is_array($c)&&isset($c['id']))$customerMap[(string)$c['id']]=['id'=>(string)$c['id'],'name'=>(string)($c['name']??$c['company']??'بدون نام'),'assignee'=>(string)($c['assignee']??''),'phone'=>(string)($c['phone']??''),'stage'=>(string)($c['stage']??'new')];
    $rows=$pdo->query('SELECT ca.id,ca.customer_id,ca.user_id,ca.access_level,ca.assigned_by,ca.created_at,ca.updated_at,u.display_name user_name,u.role user_role FROM customer_access ca JOIN users u ON u.id=ca.user_id ORDER BY ca.id DESC')->fetchAll();
    $access=[];foreach($rows as $r){$cid=(string)$r['customer_id'];if(!isset($customerMap[$cid]))continue;$access[]=['id'=>(int)$r['id'],'customer'=>$customerMap[$cid],'user_id'=>(int)$r['user_id'],'user_name'=>(string)$r['user_name'],'user_role'=>hippo_role_alias((string)$r['user_role']),'access_level'=>(string)$r['access_level'],'assigned_by'=>$r['assigned_by']!==null?(int)$r['assigned_by']:null,'created_at'=>$r['created_at'],'updated_at'=>$r['updated_at']];}
    return ['customers'=>array_values($customerMap),'access'=>$access];
}

function ua_audit_payload(PDO $pdo, int $limit): array {
    $stmt=$pdo->prepare('SELECT a.id,a.user_id,a.action,a.entity_type,a.entity_id,a.result,a.metadata_json,a.ip_address,a.created_at,u.display_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT '.$limit);$stmt->execute();
    $rows=[];foreach($stmt->fetchAll() as $r){$m=json_decode((string)($r['metadata_json']??''),true);$rows[]=['id'=>(int)$r['id'],'user_id'=>$r['user_id']!==null?(int)$r['user_id']:null,'display_name'=>$r['display_name'],'action'=>$r['action'],'entity_type'=>$r['entity_type'],'entity_id'=>$r['entity_id'],'result'=>$r['result'],'metadata'=>is_array($m)?$m:[],'ip_address'=>$r['ip_address'],'created_at'=>$r['created_at']];}
    return $rows;
}

try {
    if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='bootstrap') {
        $canPermissions=hippo_is_manager($user)&&hippo_can($user,'permissions.manage');
        $canShare=hippo_is_manager($user)&&hippo_can($user,'customers.share');
        $canAudit=hippo_is_manager($user)&&hippo_can($user,'audit.view');
        ua_json(['ok'=>true,'csrf_token'=>$user['csrf_token'],'current_user'=>['id'=>$user['id'],'display_name'=>$user['display_name'],'role'=>$user['role']],
            'capabilities'=>['users'=>hippo_can($user,'users.manage'),'sensitive_users'=>$canPermissions,'permissions'=>$canPermissions,'customer_access'=>$canShare,'audit'=>$canAudit,'team_management'=>$canPermissions]]);
    }

    if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='users') {
        hippo_require_permission($user,'users.manage');
        $stateRow=ua_state_row($pdo);
        ua_json([
            'ok'=>true,
            'team'=>ua_team($stateRow['data']),
            'team_revision'=>$stateRow['revision'],
            'users'=>ua_public_users($pdo,$stateRow['data']),
        ]);
    }
    if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='permissions') ua_json(['ok'=>true]+ua_permission_payload($pdo,$user));
    if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='customer_access') {ua_require_manager_sensitive($user,'customers.share');ua_json(['ok'=>true]+ua_customer_access_payload($pdo,ua_full_state($pdo)));}
    if ($_SERVER['REQUEST_METHOD']==='GET' && $action==='audit') {ua_require_manager_sensitive($user,'audit.view');$limit=max(1,min(200,(int)($_GET['limit']??100)));ua_json(['ok'=>true,'audit'=>ua_audit_payload($pdo,$limit)]);}

    if ($_SERVER['REQUEST_METHOD']!=='POST') ua_json(['ok'=>false,'error'=>'method_not_allowed'],405);
    $body=ua_body();hippo_require_csrf($body);

    if ($action==='create_team_member') {
        ua_require_manager_sensitive($user,'permissions.manage');
        $name=ua_team_member_name((string)($body['name']??''));
        $roleLabel=ua_team_member_role((string)($body['role_label']??''));
        $expectedRevision=ua_expected_revision($body);
        $pdo->beginTransaction();
        try {
            $stateRow=ua_state_row($pdo,true);
            if ($stateRow['revision']!==$expectedRevision) {
                $pdo->rollBack();
                hippo_audit($pdo,(int)$user['id'],'team_member_create_conflict','state','1','conflict',['expected'=>$expectedRevision,'current'=>$stateRow['revision']]);
                ua_json(['ok'=>false,'error'=>'revision_conflict','current_revision'=>$stateRow['revision']],409);
            }
            $state=$stateRow['data'];
            if (!isset($state['team']) || !is_array($state['team'])) $state['team']=[];
            $normalized=mb_strtolower($name,'UTF-8');
            foreach ($state['team'] as $member) {
                if (!is_array($member)) continue;
                $existing=mb_strtolower(trim((string)($member['name']??'')),'UTF-8');
                if ($existing!=='' && $existing===$normalized) {
                    throw new HippoAuthorizationException('team_member_name_exists',409);
                }
            }
            do {
                $id='tm_'.bin2hex(random_bytes(8));
                $exists=false;
                foreach ($state['team'] as $member) {
                    if (is_array($member) && (string)($member['id']??'')===$id) { $exists=true; break; }
                }
            } while ($exists);
            $member=['id'=>$id,'name'=>$name,'role'=>$roleLabel,'access'=>'marketer','active'=>true];
            $state['team'][]=$member;
            $newRaw=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if ($newRaw===false || strlen($newRaw)>4_000_000) throw new RuntimeException('state_encode_failed');
            $newRevision=$stateRow['revision']+1;
            $stmt=$pdo->prepare('UPDATE app_state SET data=?,updated_by=?,updated_at=CURRENT_TIMESTAMP,revision=? WHERE id=1');
            $stmt->execute([$newRaw,$user['display_name'],$newRevision]);
            $backup=$pdo->prepare('INSERT INTO app_state_backups(data,saved_by,revision,operation,source_backup_id) VALUES(?,?,?,?,NULL)');
            $backup->execute([$newRaw,$user['display_name'],$newRevision,'team_member_create']);
            ua_prune_backups($pdo);
            $pdo->commit();
            hippo_audit($pdo,(int)$user['id'],'team_member_created','team_member',$id,'ok',['name'=>$name,'revision_before'=>$stateRow['revision'],'revision_after'=>$newRevision]);
            ua_json(['ok'=>true,'member'=>$member,'revision'=>$newRevision]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action==='create') {
        hippo_require_permission($user,'users.manage');
        // Account creation, role assignment and Team Member linkage are organization-sensitive.
        ua_require_manager_sensitive($user,'permissions.manage');
        $username=ua_username((string)($body['username']??''));
        $display=trim((string)($body['display_name']??''));if($display===''||mb_strlen($display)>120)throw new HippoAuthorizationException('invalid_display_name',422);
        $role=hippo_role_alias((string)($body['role']??''));if(!in_array($role,hippo_valid_roles(),true))throw new HippoAuthorizationException('invalid_role',422);
        ua_assert_role_grantable($user,$role);
        $status=in_array(($body['status']??'active'),['active','inactive'],true)?(string)$body['status']:'active';
        $team=trim((string)($body['team_member_id']??''));$team=$team!==''?$team:null;
        $state=ua_full_state($pdo);ua_validate_team_member($state,$team);[$status,$review]=ua_apply_operational_review($role,$status,$team);ua_assert_team_link($pdo,$team,null,$status);
        [$password,$generated]=ua_password(isset($body['password'])?(string)$body['password']:null,!empty($body['generate_password']));
        $stmt=$pdo->prepare('INSERT INTO users(username,password_hash,display_name,role,status,team_member_id,rbac_review_required,password_changed_at,must_change_password,created_by) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP,?,?)');
        try{$stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT),$display,$role,$status,$team,$review,$generated?1:(int)!empty($body['must_change_password']),(int)$user['id']]);}
        catch(PDOException $e){if((string)$e->getCode()==='23000')throw new HippoAuthorizationException('username_exists',409);throw $e;}
        $id=(int)$pdo->lastInsertId();
        hippo_audit($pdo,(int)$user['id'],'user_create','user',(string)$id,'ok',['role'=>$role,'status'=>$status,'team_member_id'=>$team,'rbac_review_required'=>$review]);
        if($team!==null)hippo_audit($pdo,(int)$user['id'],'team_member_changed','user',(string)$id,'ok',['before'=>null,'after'=>$team]);
        ua_json(['ok'=>true,'user_id'=>$id,'temporary_password'=>$generated?$password:null,'status'=>$status,'rbac_review_required'=>(bool)$review]);
    }

    if ($action==='update') {
        hippo_require_permission($user,'users.manage');
        $id=(int)($body['user_id']??0);$target=ua_user_row($pdo,$id);
        $display=trim((string)($body['display_name']??$target['display_name']));if($display===''||mb_strlen($display)>120)throw new HippoAuthorizationException('invalid_display_name',422);
        $targetRole=hippo_role_alias((string)$target['role']);
        $role=hippo_role_alias((string)($body['role']??$targetRole));if(!in_array($role,hippo_valid_roles(),true))throw new HippoAuthorizationException('invalid_role',422);
        $status=in_array(($body['status']??$target['status']),['active','inactive','locked'],true)?(string)($body['status']??$target['status']):(string)$target['status'];
        $teamProvided=array_key_exists('team_member_id',$body);
        $team=$teamProvided?trim((string)$body['team_member_id']):(string)($target['team_member_id']??'');$team=$team!==''?$team:null;
        $oldTeam=trim((string)($target['team_member_id']??''));$oldTeam=$oldTeam!==''?$oldTeam:null;
        $sensitiveChange=$role!==$targetRole||$status!==(string)$target['status']||$team!==$oldTeam;
        if($sensitiveChange) ua_require_manager_sensitive($user,'permissions.manage');
        ua_assert_role_grantable($user,$role);
        $state=ua_full_state($pdo);ua_validate_team_member($state,$team);[$status,$review]=ua_apply_operational_review($role,$status,$team);
        $removesManager=($targetRole==='manager'&&(string)$target['status']==='active')&&($role!=='manager'||$status!=='active');
        if($removesManager&&ua_active_manager_count($pdo,$id)<1)throw new HippoAuthorizationException('last_manager_protected',409);
        if($id===(int)$user['id']&&$status!=='active'&&empty($body['confirm_self']))throw new HippoAuthorizationException('self_disable_confirmation_required',409);
        ua_assert_team_link($pdo,$team,$id,$status);
        $stmt=$pdo->prepare('UPDATE users SET display_name=?,role=?,status=?,team_member_id=?,rbac_review_required=?,failed_attempts=IF(?=\'active\',0,failed_attempts),locked_until=IF(?=\'active\',NULL,locked_until) WHERE id=?');
        $stmt->execute([$display,$role,$status,$team,$review,$status,$status,$id]);
        hippo_audit($pdo,(int)$user['id'],'user_update','user',(string)$id,'ok',['role_before'=>$targetRole,'role_after'=>$role,'status_before'=>$target['status'],'status_after'=>$status,'rbac_review_required'=>$review]);
        if($team!==$oldTeam)hippo_audit($pdo,(int)$user['id'],'team_member_changed','user',(string)$id,'ok',['before'=>$oldTeam,'after'=>$team]);
        ua_json(['ok'=>true,'status'=>$status,'rbac_review_required'=>(bool)$review]);
    }

    if ($action==='reset_password') {
        hippo_require_permission($user,'users.manage');$id=(int)($body['user_id']??0);$target=ua_user_row($pdo,$id);
        if(hippo_role_alias((string)$target['role'])==='manager')ua_require_manager_sensitive($user,'permissions.manage');
        [$password,$generated]=ua_password(isset($body['password'])?(string)$body['password']:null,!empty($body['generate_password']));
        $stmt=$pdo->prepare('UPDATE users SET password_hash=?,password_changed_at=CURRENT_TIMESTAMP,must_change_password=?,failed_attempts=0,locked_until=NULL,status=IF(status=\'locked\',\'active\',status) WHERE id=?');
        $stmt->execute([password_hash($password,PASSWORD_DEFAULT),$generated?1:(int)!empty($body['must_change_password']),$id]);
        hippo_audit($pdo,(int)$user['id'],'password_reset','user',(string)$id,'ok');ua_json(['ok'=>true,'temporary_password'=>$generated?$password:null]);
    }

    if ($action==='save_permissions') {
        ua_require_manager_sensitive($user,'permissions.manage');
        $id=(int)($body['user_id']??0);$target=ua_user_row($pdo,$id);$items=$body['overrides']??[];if(!is_array($items))throw new HippoAuthorizationException('invalid_permissions',422);
        foreach($items as $key=>$allowed){if(!in_array($key,hippo_permission_keys(),true))throw new HippoAuthorizationException('invalid_permission_key',422);if($allowed&&!hippo_can($user,$key))throw new HippoAuthorizationException('permission_exceeds_actor',403);}
        $targetIsLastActiveManager=hippo_role_alias((string)$target['role'])==='manager'&&(string)$target['status']==='active'&&ua_active_manager_count($pdo,$id)<1;
        if($targetIsLastActiveManager){foreach(['users.manage','permissions.manage','state.view_full','state.save_full'] as $critical){if(array_key_exists($critical,$items)&&$items[$critical]===false)throw new HippoAuthorizationException('last_manager_permission_protected',409);}}
        $pdo->beginTransaction();try{$stmt=$pdo->prepare('DELETE FROM user_permission_overrides WHERE user_id=?');$stmt->execute([$id]);$ins=$pdo->prepare('INSERT INTO user_permission_overrides(user_id,permission_key,allowed,updated_by) VALUES(?,?,?,?)');foreach($items as $key=>$allowed)$ins->execute([$id,$key,$allowed?1:0,(int)$user['id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        hippo_audit($pdo,(int)$user['id'],'permission_override','user',(string)$id,'ok',['keys'=>array_keys($items)]);ua_json(['ok'=>true]);
    }

    if ($action==='grant_access'||$action==='revoke_access') {
        ua_require_manager_sensitive($user,'customers.share');
        $targetId=(int)($body['user_id']??0);$target=ua_user_row($pdo,$targetId);$targetRole=hippo_role_alias((string)$target['role']);if(!in_array($targetRole,['marketer','center_call'],true))throw new HippoAuthorizationException('invalid_access_target',422);
        $ids=array_values(array_unique(array_filter(array_map('strval',(array)($body['customer_ids']??[])))));if(!$ids||count($ids)>500)throw new HippoAuthorizationException('invalid_customer_list',422);
        $state=ua_full_state($pdo);$valid=[];foreach((array)($state['customers']??[]) as $c)if(is_array($c)&&isset($c['id']))$valid[(string)$c['id']]=true;foreach($ids as $cid)if(empty($valid[$cid]))throw new HippoAuthorizationException('customer_not_found',404);
        if($action==='grant_access'){$level=in_array(($body['access_level']??''),['view','call','edit'],true)?(string)$body['access_level']:'view';$stmt=$pdo->prepare('INSERT INTO customer_access(customer_id,user_id,access_level,assigned_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level),assigned_by=VALUES(assigned_by),updated_at=CURRENT_TIMESTAMP');foreach($ids as $cid)$stmt->execute([$cid,$targetId,$level,(int)$user['id']]);hippo_audit($pdo,(int)$user['id'],'customer_access_grant','user',(string)$targetId,'ok',['count'=>count($ids),'level'=>$level]);}
        else{$marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$pdo->prepare("DELETE FROM customer_access WHERE user_id=? AND customer_id IN ($marks)");$stmt->execute(array_merge([$targetId],$ids));hippo_audit($pdo,(int)$user['id'],'customer_access_revoke','user',(string)$targetId,'ok',['count'=>count($ids)]);}
        ua_json(['ok'=>true]);
    }

    ua_json(['ok'=>false,'error'=>'unknown_action'],404);
} catch (HippoAuthorizationException $e) {
    $teamAttempt=(in_array($action,['create','update'],true)&&array_key_exists('team_member_id',$body))||$action==='create_team_member';
    $auditAction=$teamAttempt?'team_member_link_denied':'users_api_denied';
    hippo_audit($pdo,(int)$user['id'],$auditAction,'api',$action,'denied',['error'=>$e->errorCode]+$e->metadata);
    ua_json(['ok'=>false,'error'=>$e->errorCode],$e->httpStatus);
} catch (Throwable $e) {
    error_log('users_api failed: '.$e->getMessage());
    ua_json(['ok'=>false,'error'=>'server_error'],500);
}
