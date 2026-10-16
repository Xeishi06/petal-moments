<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
db()->exec(file_get_contents(__DIR__ . '/../migrations/002_employee_role.sql'));
echo "Employee role is ready. Existing account roles are unchanged.\n";
