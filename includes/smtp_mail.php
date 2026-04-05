<?php
declare(strict_types=1);

/**
 * Minimal SMTP client (AUTH LOGIN + SSL or STARTTLS). No external dependencies.
 */
function smtp_mail_send(
    string $host,
    int $port,
    string $encryption,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $plainBody,
    bool $verifyPeer = true
): void {
    $remote = $encryption === 'ssl'
        ? 'ssl://' . $host . ':' . $port
        : 'tcp://' . $host . ':' . $port;

    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => $verifyPeer,
            'verify_peer_name' => $verifyPeer,
            'allow_self_signed' => !$verifyPeer,
        ],
    ]);

    $fp = @stream_socket_client($remote, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx);
    if ($fp === false) {
        throw new RuntimeException('SMTP connect failed: ' . $errstr . ' (' . (string) $errno . ')');
    }
    stream_set_timeout($fp, 30);

    $ehlo = 'EHLO loan-application.local';
    smtp_expect_codes($fp, [220]);
    smtp_write_expect($fp, $ehlo, [250]);

    if ($encryption === 'tls') {
        smtp_write_expect($fp, 'STARTTLS', [220]);
        $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($ok !== true) {
            fclose($fp);
            throw new RuntimeException('SMTP STARTTLS negotiation failed.');
        }
        smtp_write_expect($fp, $ehlo, [250]);
    }

    smtp_write_expect($fp, 'AUTH LOGIN', [334]);
    smtp_write_expect($fp, base64_encode($username), [334]);
    smtp_write_expect($fp, base64_encode($password), [235]);

    smtp_write_expect($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
    smtp_write_expect($fp, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
    smtp_write_expect($fp, 'DATA', [354]);

    $safeBody = preg_replace('/^\./m', '..', $plainBody);
    $headers = 'From: ' . smtp_mime_header($fromName) . ' <' . $fromEmail . ">\r\n";
    $headers .= 'To: <' . $toEmail . ">\r\n";
    $headers .= 'Subject: ' . smtp_mime_header($subject) . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "\r\n";
    $payload = $headers . str_replace("\r\n", "\n", (string) $safeBody);
    $payload = str_replace("\n", "\r\n", $payload);
    fwrite($fp, $payload . "\r\n.\r\n");
    smtp_expect_codes($fp, [250]);
    smtp_write_expect($fp, 'QUIT', [221]);
    fclose($fp);
}

function smtp_mime_header(string $s): string
{
    if ($s === '') {
        return '';
    }
    if (preg_match('/[^\x20-\x7E]/', $s)) {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }
    return $s;
}

/**
 * @param resource $fp
 */
function smtp_expect_codes($fp, array $okCodes): string
{
    $all = '';
    while (true) {
        $line = fgets($fp, 8192);
        if ($line === false) {
            throw new RuntimeException('SMTP connection closed while reading.');
        }
        $all .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            $code = (int) substr($line, 0, 3);
            if (!in_array($code, $okCodes, true)) {
                throw new RuntimeException('SMTP error: ' . trim($all));
            }
            return $all;
        }
    }
}

/**
 * @param resource $fp
 */
function smtp_write_expect($fp, string $line, array $okCodes): void
{
    fwrite($fp, $line . "\r\n");
    smtp_expect_codes($fp, $okCodes);
}
