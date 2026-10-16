<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
db()->exec(file_get_contents(__DIR__ . '/../migrations/001_password_resets.sql'));
echo "Password reset table is ready. Existing accounts are unchanged.\n";
