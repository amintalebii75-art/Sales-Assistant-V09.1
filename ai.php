<?php
declare(strict_types=1);
require __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/ai_service.php';

header('Content-Type: application/json; charset=utf-8');
$user = hippo_require_login_api();
$pdo = hippo_db();
hippo_ai_ensure_table($pdo);
$action = $_GET['action'] ?? '';

if (!hippo_can($user, 'ai.use')) {
    hippo_audit($pdo, (int)$user['id'], 'ai_access_denied', 'ai', $action, 'denied');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hippo_verify_csrf()) {
    hippo_audit($pdo, (int)$user['id'], 'ai_csrf_denied', 'ai', $action, 'denied');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_csrf'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $cfg = hippo_ai_config();
    $used = hippo_ai_daily_used($pdo, (int)$user['id']);
    echo json_encode([
        'ok' => true,
        'configured' => hippo_ai_is_configured(),
        'daily_limit' => (int)$cfg['daily_limit_per_user'],
        'used_today' => $used,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'analyzeNegotiation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > 60000) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resultLabels = [];
    if (is_array($body['resultLabels'] ?? null)) {
        foreach (array_slice($body['resultLabels'], 0, 10) as $lbl) {
            $lbl = trim((string)$lbl);
            if ($lbl !== '') $resultLabels[] = $lbl;
        }
    }

    $history = [];
    if (is_array($body['history'] ?? null)) {
        foreach (array_slice($body['history'], 0, 15) as $h) {
            if (!is_array($h)) continue;
            $history[] = [
                'date' => mb_substr((string)($h['date'] ?? ''), 0, 10),
                'results' => is_array($h['results'] ?? null)
                    ? array_values(array_map('strval', array_slice($h['results'], 0, 5)))
                    : [],
                'note' => mb_substr(trim((string)($h['note'] ?? '')), 0, 300),
            ];
        }
    }

    $input = [
        'customer_name'  => trim((string)($body['customerName'] ?? '')),
        'customer_stage' => trim((string)($body['customerStage'] ?? '')),
        'industry'       => trim((string)($body['industry'] ?? '')),
        'result_labels'  => $resultLabels,
        'channel'        => trim((string)($body['channel'] ?? '')),
        'note'           => mb_substr(trim((string)($body['note'] ?? '')), 0, 2000),
        'volume'         => (float)($body['volume'] ?? 0),
        'value'          => (float)($body['value'] ?? 0),
        'payment_pref'   => trim((string)($body['paymentPreference'] ?? '')),
        'competitor'     => trim((string)($body['competitor'] ?? '')),
        'history'        => $history,
    ];
    if ($input['note'] === '' && !$resultLabels) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nothing_to_analyze'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $r = hippo_ai_analyze_negotiation($pdo, $user, $input);
        echo json_encode(['ok' => true, 'data' => $r['result'], 'model' => $r['model']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $notConfigured = $msg === 'AI_NOT_CONFIGURED';
        http_response_code($notConfigured ? 503 : 500);
        echo json_encode([
            'ok' => false,
            'error' => $notConfigured ? 'ai_not_configured' : 'ai_failed',
            'message' => $notConfigured ? 'کلید API هوش مصنوعی هنوز روی سرور تنظیم نشده است.' : $msg,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'analyzeProcess' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > 60000) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $funnel = [];
    if (is_array($body['funnel'] ?? null)) {
        foreach (array_slice($body['funnel'], 0, 20) as $f) {
            if (!is_array($f)) continue;
            $funnel[] = ['stage' => mb_substr((string)($f['stage'] ?? ''), 0, 60), 'count' => (int)($f['count'] ?? 0)];
        }
    }
    $replyStats = [];
    if (is_array($body['replyStats'] ?? null)) {
        foreach (array_slice($body['replyStats'], 0, 20) as $r) {
            if (!is_array($r)) continue;
            $replyStats[] = [
                'label' => mb_substr((string)($r['label'] ?? ''), 0, 80),
                'category' => mb_substr((string)($r['category'] ?? ''), 0, 40),
                'used' => (int)($r['used'] ?? 0),
                'won' => (int)($r['won'] ?? 0),
            ];
        }
    }
    $fulfillment = [];
    if (is_array($body['fulfillment'] ?? null)) {
        foreach ($body['fulfillment'] as $k => $v) {
            $fulfillment[mb_substr((string)$k, 0, 30)] = (int)$v;
        }
    }

    $input = [
        'funnel' => $funnel,
        'reply_stats' => $replyStats,
        'fulfillment' => $fulfillment,
        'total_customers' => (int)($body['totalCustomers'] ?? 0),
        'total_orders' => (int)($body['totalOrders'] ?? 0),
    ];
    if (!$funnel && !$replyStats && !$fulfillment) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nothing_to_analyze'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $r = hippo_ai_analyze_process($pdo, $user, $input);
        echo json_encode(['ok' => true, 'data' => $r['result'], 'model' => $r['model']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $notConfigured = $msg === 'AI_NOT_CONFIGURED';
        http_response_code($notConfigured ? 503 : 500);
        echo json_encode([
            'ok' => false,
            'error' => $notConfigured ? 'ai_not_configured' : 'ai_failed',
            'message' => $notConfigured ? 'کلید API هوش مصنوعی هنوز روی سرور تنظیم نشده است.' : $msg,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'analyzeCustomer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > 60000) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'bad_payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orders = [];
    if (is_array($body['orders'] ?? null)) {
        foreach (array_slice($body['orders'], 0, 30) as $o) {
            if (!is_array($o)) continue;
            $orders[] = [
                'date' => mb_substr((string)($o['date'] ?? ''), 0, 10),
                'outcome' => mb_substr((string)($o['outcome'] ?? ''), 0, 20),
                'note' => mb_substr(trim((string)($o['note'] ?? '')), 0, 300),
            ];
        }
    }
    $recentInteractions = [];
    if (is_array($body['recentInteractions'] ?? null)) {
        foreach (array_slice($body['recentInteractions'], 0, 15) as $h) {
            if (!is_array($h)) continue;
            $recentInteractions[] = [
                'date' => mb_substr((string)($h['date'] ?? ''), 0, 10),
                'results' => is_array($h['results'] ?? null)
                    ? array_values(array_map('strval', array_slice($h['results'], 0, 5)))
                    : [],
                'note' => mb_substr(trim((string)($h['note'] ?? '')), 0, 300),
            ];
        }
    }

    $input = [
        'customer_name' => trim((string)($body['customerName'] ?? '')),
        'customer_stage' => trim((string)($body['customerStage'] ?? '')),
        'industry' => trim((string)($body['industry'] ?? '')),
        'orders' => $orders,
        'avg_interval_days' => isset($body['avgIntervalDays']) && $body['avgIntervalDays'] !== null ? (int)$body['avgIntervalDays'] : null,
        'days_since_last_order' => isset($body['daysSinceLastOrder']) && $body['daysSinceLastOrder'] !== null ? (int)$body['daysSinceLastOrder'] : null,
        'reorder_level' => trim((string)($body['reorderLevel'] ?? '')),
        'recent_interactions' => $recentInteractions,
    ];
    if (!$orders && !$recentInteractions) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'nothing_to_analyze'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $r = hippo_ai_analyze_customer($pdo, $user, $input);
        echo json_encode(['ok' => true, 'data' => $r['result'], 'model' => $r['model']], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $notConfigured = $msg === 'AI_NOT_CONFIGURED';
        http_response_code($notConfigured ? 503 : 500);
        echo json_encode([
            'ok' => false,
            'error' => $notConfigured ? 'ai_not_configured' : 'ai_failed',
            'message' => $notConfigured ? 'کلید API هوش مصنوعی هنوز روی سرور تنظیم نشده است.' : $msg,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'unknown_action'], JSON_UNESCAPED_UNICODE);
