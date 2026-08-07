<?php
declare(strict_types=1);

// Production/shared-host sample. Never commit the real config.php to ZIP or Git.
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'CPANEL_PREFIX_DATABASE',
    'db_user' => 'CPANEL_PREFIX_USER',
    'db_pass' => 'REPLACE_WITH_DATABASE_PASSWORD',
    'db_charset' => 'utf8mb4',
];
