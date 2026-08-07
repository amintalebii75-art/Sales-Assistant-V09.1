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
$action = (string)($_GET['action'] ?? '');

function hippo_json(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function hippo_read_json_body(int $maxBytes = 4_000_000): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > $maxBytes) hippo_json(['ok'=>false,'error'=>'bad_payload'],400);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) hippo_json(['ok'=>false,'error'=>'invalid_json'],400);
    return $decoded;
}

function hippo_expected_revision(array $body): int {
    if (!array_key_exists('expected_revision',$body) || !is_numeric($body['expected_revision'])) hippo_json(['ok'=>false,'error'=>'missing_revision'],400);
    $revision=(int)$body['expected_revision'];
    if($revision<0) hippo_json(['ok'=>false,'error'=>'invalid_revision'],400);
    return $revision;
}

function hippo_prune_backups(PDO $pdo): void {
    $pdo->exec('DELETE FROM app_state_backups WHERE id NOT IN (SELECT id FROM (SELECT id FROM app_state_backups ORDER BY id DESC LIMIT 20) t)');
}

function hippo_insert_backup(PDO $pdo,string $data,string $savedBy,int $revision,string $operation,?int $sourceBackupId=null): void {
    $stmt=$pdo->prepare('INSERT INTO app_state_backups (data,saved_by,revision,operation,source_backup_id) VALUES (?,?,?,?,?)');
    $stmt->execute([$data,$savedBy,$revision,$operation,$sourceBackupId]);
}

function hippo_state_metadata(PDO $pdo): array {
    $row=$pdo->query('SELECT revision,updated_at,updated_by FROM app_state WHERE id=1')->fetch();
    return ['revision'=>$row?(int)$row['revision']:0,'updated_at'=>$row['updated_at']??null,'updated_by'=>$row['updated_by']??null];
}

function hippo_state_scope(array $user): string {
    return hippo_can_view_full_state($user) ? 'organization' : 'scoped';
}

function hippo_issue_state_context(array $user,string $scope,int $revision): string {
    if(session_status()!==PHP_SESSION_ACTIVE) session_start();
    $token=bin2hex(random_bytes(32));
    $_SESSION['hippo_state_context_v052']=[
        'token'=>$token,
        'user_id'=>(int)$user['id'],
        'scope'=>$scope,
        'revision'=>$revision,
        'permission_fingerprint'=>(string)($user['permission_fingerprint']??hippo_permission_fingerprint($user)),
        'issued_at'=>time(),
    ];
    return $token;
}

function hippo_validate_state_context(array $body,array $user,int $currentRevision): array {
    if(session_status()!==PHP_SESSION_ACTIVE) session_start();
    $ctx=$_SESSION['hippo_state_context_v052']??null;
    $token=(string)($body['state_context_token']??'');
    $currentFingerprint=(string)($user['permission_fingerprint']??hippo_permission_fingerprint($user));
    $valid=is_array($ctx)
        && $token!==''
        && is_string($ctx['token']??null)
        && hash_equals((string)$ctx['token'],$token)
        && (int)($ctx['user_id']??0)===(int)$user['id']
        && (int)($ctx['revision']??-1)===$currentRevision
        && hash_equals((string)($ctx['permission_fingerprint']??''),$currentFingerprint)
        && time()-(int)($ctx['issued_at']??0)<=12*60*60;
    if(!$valid){
        unset($_SESSION['hippo_state_context_v052']);
        if(hippo_can_save_full_state($user)) throw new HippoAuthorizationException('full_state_scope_mismatch',403,'',['reason'=>'context_or_fingerprint_mismatch']);
        throw new HippoAuthorizationException('state_context_stale',409);
    }
    $scope=(string)($ctx['scope']??'');
    if(hippo_can_save_full_state($user) && $scope!=='organization') {
        unset($_SESSION['hippo_state_context_v052']);
        throw new HippoAuthorizationException('full_state_scope_mismatch',403,'',['scope'=>$scope]);
    }
    return $ctx;
}

function hippo_user_public_payload(array $user): array {
    return [
        'id'=>(int)$user['id'],'username'=>(string)$user['username'],'display_name'=>(string)$user['display_name'],
        'role'=>(string)$user['role'],'role_label'=>(string)$user['role_label'],'team_member_id'=>$user['team_member_id'],
        'rbac_review_required'=>(bool)($user['rbac_review_required']??false),'team_member_valid'=>(bool)($user['team_member_valid']??true),'permissions'=>$user['permissions'],
        'permission_fingerprint'=>(string)($user['permission_fingerprint']??hippo_permission_fingerprint($user)),
        'scope_version'=>(string)($user['scope_version']??HIPPO_SCOPE_VERSION),'csrf_token'=>$user['csrf_token'],
    ];
}

function hippo_save_denied_audit_action(HippoAuthorizationException $e): string {
    return match($e->errorCode){
        'full_state_scope_mismatch'=>'full_state_scope_mismatch',
        'customer_field_rejected','customer_edit_forbidden'=>'sensitive_customer_field_access_denied',
        'interaction_field_rejected'=>'interaction_field_rejected',
        'interaction_type_rejected'=>'interaction_type_rejected',
        default=>'full_state_save_denied',
    };
}

function hippo_user_can_manage_reply_options(array $user, array $state): bool {
    if (hippo_is_manager($user)) return true;
    $memberId = trim((string)($user['team_member_id'] ?? ''));
    if ($memberId === '') return false;
    foreach ((array)($state['team'] ?? []) as $member) {
        if (!is_array($member) || (string)($member['id'] ?? '') !== $memberId) continue;
        return (string)($member['access'] ?? '') === 'sales_manager';
    }
    return false;
}


function hippo_base_data_keys(): array {
    return ['source','industry','productGroup','consumptionType','packaging','currency','contactFor','route'];
}


function hippo_default_base_group(string $key): array {
    $defs = [
        'source'=>['نحوه آشنایی','approval',[['source_phone','تماس تلفنی'],['source_referral','معرفی یا رابط'],['source_visit','بازدید حضوری'],['source_research','تحقیق و توسعه'],['source_site','سایت یا شبکه اجتماعی'],['source_exhibition','نمایشگاه']]],
        'industry'=>['زمینه فعالیت','approval',[['industry_packaging','بسته‌بندی'],['industry_polymer','پلیمر و گرانول'],['industry_film','نایلون و فیلم'],['industry_injection','قطعات تزریقی'],['industry_recycling','بازیافت'],['industry_trade','بازرگانی']]],
        'productGroup'=>['گروه محصولات','approval',[['product_stretch','فیلم استرچ'],['product_shrink','فیلم شرینک'],['product_granule','گرانول'],['product_masterbatch','مستربچ'],['product_other','سایر']]],
        'consumptionType'=>['نوع مصرف','approval',[['consumption_continuous','تولید مستمر'],['consumption_project','سفارش پروژه‌ای'],['consumption_trial','مصرف آزمایشی'],['consumption_seasonal','مصرف فصلی']]],
        'packaging'=>['بسته‌بندی','approval',[['pack_25kg','کیسه ۲۵ کیلویی'],['pack_jumbo','جامبوبگ'],['pack_roll','رول'],['pack_pallet','پالت'],['pack_custom','سفارشی']]],
        'currency'=>['نوع ارز','manager_only',[['currency_irr','ریال'],['currency_toman','تومان'],['currency_usd','دلار'],['currency_eur','یورو']]],
        'contactFor'=>['ارتباط برای','approval',[['contact_intro','معرفی اولیه'],['contact_price','پیگیری قیمت'],['contact_sample','ارسال یا پیگیری نمونه'],['contact_quote','پیش‌فاکتور'],['contact_order','پیگیری سفارش'],['contact_after_sales','خدمات پس از فروش']]],
        'route'=>['مسیر ارتباط','approval',[['route_phone','تماس تلفنی'],['route_whatsapp','واتساپ'],['route_meeting','جلسه حضوری'],['route_referral','معرفی واسطه'],['route_exhibition','نمایشگاه']]],
    ];
    $def = $defs[$key] ?? [$key,'manager_only',[]];
    return ['label'=>$def[0],'addMode'=>$def[1],'options'=>array_map(static fn($x)=>['id'=>$x[0],'label'=>$x[1],'active'=>true,'status'=>'active','system'=>true],$def[2])];
}

function hippo_merge_base_group_defaults(string $key, array $current): array {
    $base = hippo_default_base_group($key);
    $byId = [];
    foreach ((array)($current['options'] ?? []) as $option) if (is_array($option) && isset($option['id'])) $byId[(string)$option['id']] = $option;
    $options = [];
    foreach ($base['options'] as $option) {
        $id=(string)$option['id'];$options[] = isset($byId[$id]) ? array_merge($option,$byId[$id]) : $option;unset($byId[$id]);
    }
    foreach ($byId as $option) $options[]=$option;
    return ['label'=>(string)($current['label']??$base['label']),'addMode'=>(string)($current['addMode']??$base['addMode']),'options'=>$options];
}

function hippo_base_option_label_exists(array $options, string $label): bool {
    $needle = mb_strtolower(trim($label));
    foreach ($options as $item) {
        if (!is_array($item)) continue;
        if (mb_strtolower(trim((string)($item['label'] ?? ''))) === $needle && (string)($item['status'] ?? '') !== 'rejected') return true;
    }
    return false;
}

function hippo_user_can_manage_base_data(array $user, array $state): bool {
    return hippo_user_can_manage_reply_options($user, $state);
}

function hippo_reply_option_label_exists(array $library, string $label): bool {
    $needle = mb_strtolower(trim($label));
    foreach ($library as $item) {
        if (!is_array($item)) continue;
        if (mb_strtolower(trim((string)($item['label'] ?? ''))) === $needle) return true;
    }
    return false;
}

try {
    if($action==='reply_option_create' && $_SERVER['REQUEST_METHOD']==='POST'){
        $body=hippo_read_json_body(50_000);hippo_require_csrf($body);$expectedRevision=hippo_expected_revision($body);
        $label=trim((string)($body['label']??''));$category=trim((string)($body['category']??'سفارشی'));
        if(mb_strlen($label)<2||mb_strlen($label)>100)hippo_json(['ok'=>false,'error'=>'invalid_reply_option_label'],422);
        if($category==='')$category='سفارشی';$category=mb_substr($category,0,60);
        $pdo->beginTransaction();
        try{
            $row=$pdo->query('SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1 FOR UPDATE')->fetch();
            if(!$row){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'state_not_found'],404);}
            $currentRevision=(int)$row['revision'];
            if($expectedRevision!==$currentRevision){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'revision_conflict','current_revision'=>$currentRevision],409);}
            $state=json_decode((string)$row['data'],true);if(!is_array($state))$state=[];
            if(!hippo_user_can_manage_reply_options($user,$state))throw new HippoAuthorizationException('reply_option_manage_forbidden',403);
            $library=(array)($state['replyLibrary']??[]);
            if(hippo_reply_option_label_exists($library,$label)){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'reply_option_exists'],409);}
            $option=[
                'id'=>'team_'.bin2hex(random_bytes(8)),
                'label'=>$label,
                'category'=>$category,
                'response'=>'',
                'action'=>'ارجاع نتیجه به بازاریاب مسئول مشتری',
                'stage'=>'negotiation',
                'active'=>true,
                'edited'=>true,
                'teamVisible'=>true,
                'callCenterAllowed'=>true,
                'handoffToAssignee'=>true,
                'createdBy'=>(string)$user['display_name'],
                'createdAt'=>date('c'),
            ];
            $library[]=$option;$state['replyLibrary']=$library;
            $newRaw=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($newRaw===false)throw new RuntimeException('json_encode_failed');
            $replyRevision=$currentRevision+1;
            $stmt=$pdo->prepare('UPDATE app_state SET data=?,updated_by=?,updated_at=CURRENT_TIMESTAMP,revision=? WHERE id=1');
            $stmt->execute([$newRaw,$user['display_name'],$replyRevision]);
            hippo_insert_backup($pdo,$newRaw,$user['display_name'],$replyRevision,'reply_option_create');hippo_prune_backups($pdo);$pdo->commit();
            hippo_audit($pdo,(int)$user['id'],'reply_option_create','reply_option',(string)$option['id'],'ok',['label'=>$label]);
            $replyStateToken=hippo_issue_state_context($user,hippo_state_scope($user),$replyRevision);
        }catch(HippoAuthorizationException $e){if($pdo->inTransaction())$pdo->rollBack();hippo_audit($pdo,(int)$user['id'],'reply_option_create_denied','reply_option',null,'denied',['error'=>$e->errorCode]);hippo_json(['ok'=>false,'error'=>$e->errorCode],$e->httpStatus);}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Reply option create failed: '.$e->getMessage());hippo_json(['ok'=>false,'error'=>'reply_option_save_failed'],500);}
        hippo_json(['ok'=>true,'option'=>$option,'revision'=>$replyRevision,'state_context_token'=>$replyStateToken]);
    }


    if($action==='base_option_create' && $_SERVER['REQUEST_METHOD']==='POST'){
        $body=hippo_read_json_body(60_000);hippo_require_csrf($body);$expectedRevision=hippo_expected_revision($body);
        $fieldKey=trim((string)($body['field_key']??''));$label=trim((string)($body['label']??''));
        if(!in_array($fieldKey,hippo_base_data_keys(),true))hippo_json(['ok'=>false,'error'=>'invalid_base_field'],422);
        if(mb_strlen($label)<2||mb_strlen($label)>100)hippo_json(['ok'=>false,'error'=>'invalid_base_option_label'],422);
        $pdo->beginTransaction();
        try{
            $row=$pdo->query('SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1 FOR UPDATE')->fetch();
            if(!$row){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'state_not_found'],404);}
            $currentRevision=(int)$row['revision'];
            if($expectedRevision!==$currentRevision){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'revision_conflict','current_revision'=>$currentRevision],409);}
            $state=json_decode((string)$row['data'],true);if(!is_array($state))$state=[];
            $group=hippo_merge_base_group_defaults($fieldKey,(array)($state['masterData'][$fieldKey]??[]));$options=(array)($group['options']??[]);
            if(hippo_base_option_label_exists($options,$label)){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'base_option_exists'],409);}
            $manager=hippo_user_can_manage_base_data($user,$state);$mode=(string)($group['addMode']??'manager_only');
            if(!$manager&&!in_array($mode,['direct','approval'],true))throw new HippoAuthorizationException('base_option_add_forbidden',403);
            $status=($manager||$mode==='direct')?'active':'pending';
            $option=[
                'id'=>'base_'.preg_replace('/[^a-zA-Z0-9_-]/','',$fieldKey).'_'.bin2hex(random_bytes(7)),
                'label'=>$label,'active'=>$status==='active','status'=>$status,'system'=>false,
                'createdBy'=>(string)$user['display_name'],'createdAt'=>date('c'),
            ];
            if(!isset($state['masterData'])||!is_array($state['masterData']))$state['masterData']=[];
            $state['masterData'][$fieldKey]=$group;
            $state['masterData'][$fieldKey]['options'][]=$option;
            $newRaw=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($newRaw===false)throw new RuntimeException('json_encode_failed');
            $baseRevision=$currentRevision+1;
            $stmt=$pdo->prepare('UPDATE app_state SET data=?,updated_by=?,updated_at=CURRENT_TIMESTAMP,revision=? WHERE id=1');$stmt->execute([$newRaw,$user['display_name'],$baseRevision]);
            hippo_insert_backup($pdo,$newRaw,$user['display_name'],$baseRevision,'base_option_create');hippo_prune_backups($pdo);$pdo->commit();
            $token=hippo_issue_state_context($user,hippo_state_scope($user),$baseRevision);
            hippo_audit($pdo,(int)$user['id'],'base_option_create','base_option',(string)$option['id'],'ok',['field_key'=>$fieldKey,'label'=>$label,'status'=>$status]);
        }catch(HippoAuthorizationException $e){if($pdo->inTransaction())$pdo->rollBack();hippo_audit($pdo,(int)$user['id'],'base_option_create_denied','base_option',null,'denied',['error'=>$e->errorCode,'field_key'=>$fieldKey]);hippo_json(['ok'=>false,'error'=>$e->errorCode],$e->httpStatus);}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Base option create failed: '.$e->getMessage());hippo_json(['ok'=>false,'error'=>'base_option_save_failed'],500);}
        hippo_json(['ok'=>true,'option'=>$option,'field_key'=>$fieldKey,'revision'=>$baseRevision,'state_context_token'=>$token]);
    }

    if($action==='state' && $_SERVER['REQUEST_METHOD']==='GET'){
        hippo_require_operational_account($user);
        if(hippo_role_alias((string)$user['role'])==='manager_viewer'){
            hippo_audit($pdo,(int)$user['id'],'state_full_denied','state','1','denied');
            hippo_json(['ok'=>false,'error'=>'summary_only'],403);
        }
        if(!hippo_can($user,'customers.view_all')&&!hippo_can($user,'customers.view_own')) hippo_require_permission($user,'dashboard.view_personal');
        $row=$pdo->query('SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1')->fetch();
        $full=$row?json_decode((string)$row['data'],true):[];if(!is_array($full))$full=[];
        $filtered=hippo_filter_state($pdo,$full,$user);
        $encoded=json_encode($filtered,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $revision=$row?(int)$row['revision']:0;
        $scope=hippo_state_scope($user);
        $contextToken=hippo_issue_state_context($user,$scope,$revision);
        hippo_json([
            'ok'=>true,'user'=>hippo_user_public_payload($user),'role'=>$user['role'],'display_name'=>$user['display_name'],
            'scope'=>$scope,'state_context_token'=>$contextToken,'data'=>$encoded?:'{}','revision'=>$revision,
            'updated_at'=>$row['updated_at']??null,'updated_by'=>$row['updated_by']??null,
        ]);
    }

    if($action==='backups' && $_SERVER['REQUEST_METHOD']==='GET'){
        hippo_require_manager_permission($user,'backups.view');
        hippo_require_manager_permission($user,'state.view_full');
        $rows=$pdo->query('SELECT id,revision,operation,source_backup_id,saved_at,saved_by FROM app_state_backups ORDER BY id DESC LIMIT 20')->fetchAll();
        hippo_json(['ok'=>true,'current_revision'=>hippo_state_metadata($pdo)['revision'],'backups'=>array_map(static fn(array $row):array=>[
            'id'=>(int)$row['id'],'revision'=>(int)$row['revision'],'operation'=>(string)$row['operation'],
            'source_backup_id'=>$row['source_backup_id']!==null?(int)$row['source_backup_id']:null,'saved_at'=>$row['saved_at'],'saved_by'=>$row['saved_by'],
        ],$rows)]);
    }

    if($action==='save' && $_SERVER['REQUEST_METHOD']==='POST'){
        $body=hippo_read_json_body();hippo_require_csrf($body);$expectedRevision=hippo_expected_revision($body);
        if(!isset($body['data'])||!is_array($body['data'])) hippo_json(['ok'=>false,'error'=>'invalid_state'],400);
        $pdo->beginTransaction();
        try{
            $row=$pdo->query('SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1 FOR UPDATE')->fetch();
            if(!$row){$stmt=$pdo->prepare('INSERT INTO app_state (id,data,updated_by,revision) VALUES (1,?,?,0)');$stmt->execute(['{}','setup']);$row=['data'=>'{}','revision'=>0,'updated_at'=>null,'updated_by'=>'setup'];}
            $currentRevision=(int)$row['revision'];
            if($expectedRevision!==$currentRevision){$pdo->rollBack();hippo_audit($pdo,(int)$user['id'],'state_conflict','state','1','conflict',['expected'=>$expectedRevision,'current'=>$currentRevision]);hippo_json(['ok'=>false,'error'=>'revision_conflict','current_revision'=>$currentRevision,'updated_at'=>$row['updated_at'],'updated_by'=>$row['updated_by']],409);}
            $context=hippo_validate_state_context($body,$user,$currentRevision);
            $current=json_decode((string)$row['data'],true);if(!is_array($current))$current=[];
            hippo_policy_take_events();
            $merged=hippo_scoped_merge($pdo,$current,$body['data'],$user);
            $policyEvents=hippo_policy_take_events();
            $newRaw=json_encode($merged,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if($newRaw===false||strlen($newRaw)>4_000_000){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'bad_payload'],400);}
            if($merged==$current){
                $pdo->rollBack();
                foreach($policyEvents as $event) hippo_audit($pdo,(int)$user['id'],(string)$event['code'],'state','1','denied',(array)($event['metadata']??[]));
                $token=hippo_issue_state_context($user,(string)$context['scope'],$currentRevision);
                hippo_json(['ok'=>true,'unchanged'=>true,'revision'=>$currentRevision,'updated_at'=>$row['updated_at'],'updated_by'=>$row['updated_by'],'scope'=>$context['scope'],'state_context_token'=>$token]);
            }
            $newRevision=$currentRevision+1;
            $stmt=$pdo->prepare('UPDATE app_state SET data=?,updated_by=?,updated_at=CURRENT_TIMESTAMP,revision=? WHERE id=1');
            $stmt->execute([$newRaw,$user['display_name'],$newRevision]);
            $fullSave=hippo_can_save_full_state($user)&&((string)$context['scope']==='organization');
            $operation=(($body['operation']??'')==='excel_import')?'excel_import':($fullSave?'save':'scoped_save');
            if($operation==='excel_import') hippo_require_permission($user,'excel_import.use');
            hippo_insert_backup($pdo,$newRaw,$user['display_name'],$newRevision,$operation);hippo_prune_backups($pdo);$pdo->commit();
            $meta=hippo_state_metadata($pdo);$token=hippo_issue_state_context($user,(string)$context['scope'],$newRevision);
            hippo_audit($pdo,(int)$user['id'],$operation==='excel_import'?'excel_import':'state_save','state','1','ok',['revision_before'=>$currentRevision,'revision_after'=>$newRevision,'scope'=>$fullSave?'full':'scoped']);
            foreach($policyEvents as $event) hippo_audit($pdo,(int)$user['id'],(string)$event['code'],'state','1','denied',(array)($event['metadata']??[]));
        }catch(HippoAuthorizationException $e){
            if($pdo->inTransaction())$pdo->rollBack();
            hippo_audit($pdo,(int)$user['id'],hippo_save_denied_audit_action($e),'state','1','denied',['error'=>$e->errorCode]+$e->metadata);
            hippo_json(['ok'=>false,'error'=>$e->errorCode],$e->httpStatus);
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();error_log('State save failed: '.$e->getMessage());hippo_json(['ok'=>false,'error'=>'save_failed'],500);
        }
        hippo_json(['ok'=>true,'scope'=>$context['scope'],'state_context_token'=>$token,'reload_required'=>!hippo_can_save_full_state($user)]+$meta);
    }

    if($action==='restore' && $_SERVER['REQUEST_METHOD']==='POST'){
        $body=hippo_read_json_body(50_000);hippo_require_csrf($body);
        hippo_require_manager_permission($user,'backups.restore');
        hippo_require_manager_permission($user,'state.view_full');
        hippo_require_manager_permission($user,'state.save_full');
        $expectedRevision=hippo_expected_revision($body);$backupId=isset($body['backup_id'])&&is_numeric($body['backup_id'])?(int)$body['backup_id']:0;
        if($backupId<=0)hippo_json(['ok'=>false,'error'=>'invalid_backup'],400);
        $pdo->beginTransaction();
        try{
            $current=$pdo->query('SELECT data,revision,updated_at,updated_by FROM app_state WHERE id=1 FOR UPDATE')->fetch();
            if(!$current){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'state_not_found'],404);}
            $currentRevision=(int)$current['revision'];
            if($expectedRevision!==$currentRevision){$pdo->rollBack();hippo_audit($pdo,(int)$user['id'],'restore_conflict','backup',(string)$backupId,'conflict');hippo_json(['ok'=>false,'error'=>'revision_conflict','current_revision'=>$currentRevision,'updated_at'=>$current['updated_at'],'updated_by'=>$current['updated_by']],409);}
            $stmt=$pdo->prepare('SELECT id,data FROM app_state_backups WHERE id=?');$stmt->execute([$backupId]);$backup=$stmt->fetch();
            if(!$backup){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'backup_not_found'],404);}
            $decoded=json_decode((string)$backup['data'],true);if(!is_array($decoded)){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'backup_corrupt'],422);}
            hippo_validate_full_state_payload($decoded);
            hippo_insert_backup($pdo,(string)$current['data'],$user['display_name'],$currentRevision,'pre_restore',$backupId);
            $newRevision=$currentRevision+1;$stmt=$pdo->prepare('UPDATE app_state SET data=?,updated_by=?,updated_at=CURRENT_TIMESTAMP,revision=? WHERE id=1');$stmt->execute([(string)$backup['data'],$user['display_name'],$newRevision]);
            hippo_insert_backup($pdo,(string)$backup['data'],$user['display_name'],$newRevision,'restore',$backupId);hippo_prune_backups($pdo);$pdo->commit();
            $meta=hippo_state_metadata($pdo);$token=hippo_issue_state_context($user,'organization',$newRevision);
            hippo_audit($pdo,(int)$user['id'],'backup_restore','backup',(string)$backupId,'ok',['revision_before'=>$currentRevision,'revision_after'=>$newRevision]);
        }catch(HippoAuthorizationException $e){
            if($pdo->inTransaction())$pdo->rollBack();hippo_audit($pdo,(int)$user['id'],'restore_access_denied','backup',(string)$backupId,'denied',['error'=>$e->errorCode]);hippo_json(['ok'=>false,'error'=>$e->errorCode],$e->httpStatus);
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();error_log('Restore failed: '.$e->getMessage());hippo_json(['ok'=>false,'error'=>'restore_failed'],500);
        }
        // Never return restored full state in the response. Frontend must GET its authorized state again.
        hippo_json(['ok'=>true,'restored_backup_id'=>$backupId,'state_context_token'=>$token,'scope'=>'organization']+$meta);
    }

    if($action==='addManagerTask' && $_SERVER['REQUEST_METHOD']==='POST'){
        $body=hippo_read_json_body(50_000);hippo_require_csrf($body);hippo_require_permission($user,'tasks.assign');
        $text=trim((string)($body['text']??''));$priority=(($body['priority']??'normal')==='urgent')?'urgent':'normal';$assignee=trim((string)($body['assignee']??''));
        if($text===''||mb_strlen($text)>500)hippo_json(['ok'=>false,'error'=>'invalid_text'],400);
        $pdo->beginTransaction();
        try{
            $row=$pdo->query('SELECT data,revision FROM app_state WHERE id=1 FOR UPDATE')->fetch();$data=$row?json_decode((string)$row['data'],true):[];if(!is_array($data))$data=[];
            $activeWeek=(int)($data['project']['activeWeek']??1);$idx=max(0,$activeWeek-1);
            if(!isset($data['weeks'][$idx])||!is_array($data['weeks'][$idx])){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'no_active_week'],500);}
            if(!isset($data['weeks'][$idx]['tasks'])||!is_array($data['weeks'][$idx]['tasks']))$data['weeks'][$idx]['tasks']=[];
            $teamIds=[];foreach((array)($data['team']??[])as$member)if(isset($member['id']))$teamIds[]=(string)$member['id'];
            if($assignee===''||!in_array($assignee,$teamIds,true)){$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'invalid_assignee'],422);}
            $data['weeks'][$idx]['tasks'][]=['id'=>'mgr'.time().random_int(100,999),'text'=>$text,'status'=>'not_started','assignee'=>$assignee,'note'=>'','custom'=>true,'fromManager'=>true,'priority'=>$priority,'createdAt'=>date('c')];
            $newRaw=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);if($newRaw===false)throw new RuntimeException('json_encode_failed');$newRevision=(int)($row['revision']??0)+1;
            $stmt=$pdo->prepare('UPDATE app_state SET data=?,updated_by=?,updated_at=CURRENT_TIMESTAMP,revision=? WHERE id=1');$stmt->execute([$newRaw,$user['display_name'],$newRevision]);
            hippo_insert_backup($pdo,$newRaw,$user['display_name'],$newRevision,'manager_task');hippo_prune_backups($pdo);$pdo->commit();$meta=hippo_state_metadata($pdo);
            hippo_audit($pdo,(int)$user['id'],'task_assign','task',null,'ok',['assignee'=>$assignee]);
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();hippo_json(['ok'=>false,'error'=>'task_save_failed'],500);}
        hippo_json(['ok'=>true]+$meta);
    }

    hippo_json(['ok'=>false,'error'=>'unknown_action'],404);
}catch(HippoAuthorizationException $e){
    $auditAction=match($action){'backups'=>'backup_access_denied','restore'=>'restore_access_denied',default=>'api_forbidden'};
    hippo_audit($pdo,(int)$user['id'],$auditAction,'api',$action,'denied',['error'=>$e->errorCode]+$e->metadata);
    hippo_json(['ok'=>false,'error'=>$e->errorCode],$e->httpStatus);
}
