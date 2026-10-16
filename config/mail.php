<?php
// PHP mail() uses the SMTP/sendmail transport configured in php.ini.
// Override these values using environment variables or mail.local.php.
$config = [
    'base_url' => getenv('PETAL_BASE_URL') ?: 'http://localhost/petal-moments',
    'from' => getenv('PETAL_MAIL_FROM') ?: '',
    'transport' => 'mail',
    'host' => '',
    'port' => 587,
    'encryption' => 'tls',
    'username' => '',
    'password' => '',
];
if (is_file(__DIR__ . '/mail.local.php')) {
    $config = array_replace($config, require __DIR__ . '/mail.local.php');
}
return $config;
