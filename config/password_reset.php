<?php
require_once __DIR__ . '/functions.php';

function reset_mail_config()
{
    static $config;
    if ($config === null) { $config = require __DIR__ . '/mail.php'; }
    return $config;
}

function reset_mail_ready()
{
    $config = reset_mail_config();
    if (!filter_var($config['from'], FILTER_VALIDATE_EMAIL)
        || !filter_var($config['base_url'], FILTER_VALIDATE_URL)
        || !in_array(parse_url($config['base_url'], PHP_URL_SCHEME), ['http', 'https'], true)
        || preg_match('/[\r\n]/', $config['from'])) {
        return false;
    }
    if (($config['transport'] ?? 'mail') === 'smtp') {
        return extension_loaded('openssl')
            && !empty($config['host']) && !empty($config['port'])
            && !empty($config['username']) && !empty($config['password']);
    }
    return true;
}

function create_password_reset($pdo, $user)
{
    $token = bin2hex(random_bytes(32));
    $pdo->prepare('INSERT INTO password_resets (token_hash, user_id, password_snapshot, expires_at)
        VALUES (:token, :user, :password, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))')
        ->execute([':token' => hash('sha256', $token), ':user' => $user['id'], ':password' => $user['password']]);
    return $token;
}

function find_password_reset($pdo, $token)
{
    if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) { return false; }
    $stmt = $pdo->prepare('SELECT r.user_id, r.password_snapshot, u.password, u.email
        FROM password_resets r JOIN users u ON u.id = r.user_id
        WHERE r.token_hash = :token AND r.expires_at > UTC_TIMESTAMP() AND u.is_active = 1');
    $stmt->execute([':token' => hash('sha256', $token)]);
    $row = $stmt->fetch();
    return $row && hash_equals($row['password_snapshot'], $row['password']) ? $row : false;
}

function complete_password_reset($pdo, $token, $password)
{
    if (password_strength($password) !== '' || strlen($password) > 72) { return false; }
    $reset = find_password_reset($pdo, $token);
    if (!$reset) { return false; }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $pdo->beginTransaction();
    try {
        // Serialize resets for this account, including two different valid links.
        $lock = $pdo->prepare('SELECT password FROM users WHERE id = :id AND is_active = 1 FOR UPDATE');
        $lock->execute([':id' => $reset['user_id']]);
        $current = $lock->fetchColumn();
        $tokenLock = $pdo->prepare('SELECT password_snapshot FROM password_resets
            WHERE token_hash = :token AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
        $tokenLock->execute([':token' => hash('sha256', $token)]);
        $snapshot = $tokenLock->fetchColumn();
        if (!$current || !$snapshot || !hash_equals($snapshot, $current)) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
            ->execute([':password' => $hash, ':id' => $reset['user_id']]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = :id')->execute([':id' => $reset['user_id']]);
        $pdo->commit();
        login_clear($reset['email']);
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function send_password_reset($email, $token)
{
    if (!reset_mail_ready() || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) { return false; }
    $config = reset_mail_config();
    $link = rtrim($config['base_url'], '/') . '/reset-password.php?token=' . rawurlencode($token);
    $message = "Reset your Petal Moments password\r\n\r\n"
        . "Open this link to choose a new password:\r\n" . $link . "\r\n\r\n"
        . "This link expires in 30 minutes and can be used only once.\r\n"
        . "If you did not request this, ignore this email. Your password has not changed.\r\n";
    $supportsSmtp = extension_loaded('openssl')
        && ($config['transport'] ?? 'mail') === 'smtp'
        && !empty($config['host']) && !empty($config['port'])
        && !empty($config['username']) && !empty($config['password'])
        && preg_match('/\A[0-9a-zA-Z._-]+\z/', $config['host']);
    if ($supportsSmtp) {
        return send_password_reset_smtp($config, $email, $message);
    }
    return @mail($email, 'Reset your Petal Moments password', $message, [
        'From' => 'Petal Moments <' . $config['from'] . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
}
function send_password_reset_smtp($config, $email, $message)
{
    $host = $config['host']; $port = (int)$config['port'];
    $tls = ($config['encryption'] ?? 'tls') === 'tls';
    $startTls = $tls && $port === 587;
    $socket = @stream_socket_client($host . ':' . $port, $errno, $errstr, 30);
    if (!$socket) { error_log('Gmail SMTP connect failed: ' . trim($errstr . ' (' . $errno . ')')); return false; }
    stream_set_timeout($socket, 30);
    $reply = function () use ($socket) {
        $lines = []; $rest = 0;
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) { break; }
            $lines[] = $line;
            if (preg_match("/[0-9]{3} /", substr($line, 0, 4), $matches)) { $rest = substr($line, 0, 3); break; }
        }
        if (!$lines) { return [false, '']; }
        return [intval($rest ?: substr($lines[0], 0, 3)), implode('', $lines)];
    };
    // 220 greeting
    [$code] = $reply();
    if ($code !== 220) { fclose($socket); return false; }
    $local = preg_replace('/[^A-Za-z0-9.-]/', '', parse_url($config['from'] ?? '', PHP_URL_HOST) ?: 'localhost');
    // EHLO
    fwrite($socket, "EHLO $local\r\n");
    if (!smtp_code_ok($reply, 250)) { fclose($socket); return false; }
    if ($startTls) {
        fwrite($socket, "STARTTLS\r\n");
        if (!smtp_code_ok($reply, 220)) { fclose($socket); return false; }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT)) { fclose($socket); return false; }
        fwrite($socket, "EHLO $local\r\n");
        if (!smtp_code_ok($reply, 250)) { fclose($socket); return false; }
    }
    fwrite($socket, "AUTH LOGIN\r\n");
    if (!smtp_code_ok($reply, 334)) { fclose($socket); return false; }
    fwrite($socket, base64_encode($config['username']) . "\r\n");
    if (!smtp_code_ok($reply, 334)) { fclose($socket); return false; }
    fwrite($socket, base64_encode($config['password']) . "\r\n");
    if (!smtp_code_ok($reply, 235)) { fclose($socket); return false; }
    fwrite($socket, "MAIL FROM:<" . $config['from'] . ">\r\n");
    if (!smtp_code_ok($reply, 250)) { fclose($socket); return false; }
    fwrite($socket, "RCPT TO:<$email>\r\n");
    if (!smtp_code_ok($reply, 250)) { fclose($socket); return false; }
    fwrite($socket, "DATA\r\n");
    if (!smtp_code_ok($reply, 354)) { fclose($socket); return false; }
    $data = "From: Petal Moments <" . $config['from'] . ">\r\n"
          . "To: $email\r\n"
          . "Subject: Reset your Petal Moments password\r\n"
          . "MIME-Version: 1.0\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: 8bit\r\n"
          . "\r\n" . str_replace("\r\n.\r\n", "\r\n..\r\n", str_replace("\n", "\r\n", $message)) . "\r\n.\r\n";
    fwrite($socket, $data);
    if (!smtp_code_ok($reply, 250)) { fclose($socket); return false; }
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}
function smtp_code_ok($reply, $expected) {
    [$code] = $reply();
    return is_int($code) && $code === $expected;
}
