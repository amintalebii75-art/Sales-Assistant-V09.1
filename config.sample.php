<?php
declare(strict_types=1);

// Shared-host / cPanel sample. Copy to config.php and keep it outside public backups.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'cpaneluser_salespilot',
    'db_user' => 'cpaneluser_salespilot',
    'db_pass' => 'CHANGE_WITH_A_STRONG_DATABASE_PASSWORD',
    'db_charset' => 'utf8mb4',
];
