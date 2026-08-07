<?php
declare(strict_types=1);

function hippo_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $configFile = __DIR__ . '/config.php';
    if (!file_exists($configFile)) {
        http_response_code(500);
        die('فایل config.php پیدا نشد. برای localhost فایل config.localhost.sample.php و برای هاست فایل config.sample.php را کپی و تنظیم کنید.');
    }
    $cfg = require $configFile;

    $host = (string)($cfg['db_host'] ?? 'localhost');
    $port = (int)($cfg['db_port'] ?? 3306);
    $name = (string)($cfg['db_name'] ?? '');
    $charset = (string)($cfg['db_charset'] ?? 'utf8mb4');
    if ($name === '' || empty($cfg['db_user'])) {
        http_response_code(500);
        die('تنظیمات پایگاه داده کامل نیست. فایل config.php را بررسی کنید.');
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    try {
        $pdo = new PDO($dsn, (string)$cfg['db_user'], (string)($cfg['db_pass'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        die('اتصال به پایگاه داده برقرار نشد. لطفاً با مدیر سیستم تماس بگیرید.');
    }
    return $pdo;
}
