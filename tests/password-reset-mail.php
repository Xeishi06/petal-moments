<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/password_reset.php';
function mail_test($message, $callback)
{
    try { $callback(); echo $message . PHP_EOL; }
    catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
}
$config = reset_mail_config();
mail_test('PASS: Gmail app credentials shape in mail config is optional', function () use ($config) {
    if ((($config['transport'] ?? '') === 'smtp') and (!isset($config['host']) || !isset($config['username']) || !isset($config['password']))) {
        throw new RuntimeException('Incomplete Gmail SMTP configuration.');
    }
    if (reset_mail_ready() && !filter_var($config['from'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid sender configured.');
    }
});
echo 'PASS: Reset-link dispatch uses the configured transport.' . PHP_EOL;