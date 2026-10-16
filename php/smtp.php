<?php

function nexusSmtpReadResponse($socket, array $expectedCodes): string
{
    $response = '';
    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('O servidor SMTP encerrou a conexão.');
        }

        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Resposta SMTP inesperada: ' . trim($response));
    }

    return $response;
}

function nexusSmtpWrite($socket, string $data): void
{
    while ($data !== '') {
        $written = fwrite($socket, $data);
        if ($written === false || $written === 0) {
            throw new RuntimeException('Não foi possível enviar dados ao servidor SMTP.');
        }
        $data = substr($data, $written);
    }
}

function nexusSmtpCommand($socket, string $command, array $expectedCodes): string
{
    nexusSmtpWrite($socket, $command . "\r\n");
    return nexusSmtpReadResponse($socket, $expectedCodes);
}

function nexusSendSupportEmail(string $recipient, string $name, string $subject, string $phone, string $message): void
{
    $fromAddress = 'tooltech.solutions@outlook.com';
    $host = getenv('SMTP_HOST') ?: 'smtp-mail.outlook.com';
    $port = (int) (getenv('SMTP_PORT') ?: 587);
    $encryption = strtolower(getenv('SMTP_ENCRYPTION') ?: 'tls');
    $username = getenv('SMTP_USERNAME') ?: $fromAddress;
    $password = getenv('SMTP_PASSWORD') ?: '';

    if (!filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
        || $port < 1 || $port > 65535
        || !in_array($encryption, ['tls', 'ssl'], true)
        || !filter_var($username, FILTER_VALIDATE_EMAIL)
        || $password === '') {
        throw new RuntimeException('SMTP não configurado: defina SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION, SMTP_USERNAME e SMTP_PASSWORD em .env.local.', 503);
    }

    if (strcasecmp($username, $fromAddress) !== 0) {
        throw new RuntimeException('SMTP_USERNAME deve corresponder ao endereço remetente.', 503);
    }

    $transport = $encryption === 'ssl' ? 'ssl' : 'tcp';
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
        ],
    ]);
    $socket = @stream_socket_client(
        "{$transport}://{$host}:{$port}",
        $errorNumber,
        $errorMessage,
        15,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if ($socket === false) {
        throw new RuntimeException("Não foi possível conectar ao servidor SMTP ({$errorNumber}): {$errorMessage}");
    }

    stream_set_timeout($socket, 15);

    try {
        nexusSmtpReadResponse($socket, [220]);
        $serverName = preg_replace('/[^A-Za-z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? '') ?: 'localhost';
        nexusSmtpCommand($socket, "EHLO {$serverName}", [250]);

        if ($encryption === 'tls') {
            nexusSmtpCommand($socket, 'STARTTLS', [220]);
            $cryptoEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new RuntimeException('Não foi possível iniciar uma conexão TLS com o servidor SMTP.');
            }
            nexusSmtpCommand($socket, "EHLO {$serverName}", [250]);
        }

        nexusSmtpCommand($socket, 'AUTH LOGIN', [334]);
        nexusSmtpCommand($socket, base64_encode($username), [334]);
        nexusSmtpCommand($socket, base64_encode($password), [235]);
        nexusSmtpCommand($socket, "MAIL FROM:<{$fromAddress}>", [250]);
        nexusSmtpCommand($socket, "RCPT TO:<{$recipient}>", [250, 251]);
        nexusSmtpCommand($socket, 'DATA', [354]);

        $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeRecipient = htmlspecialchars($recipient, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safePhone = htmlspecialchars($phone !== '' ? $phone : 'Não informado', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $boundary = 'nexus_' . bin2hex(random_bytes(16));
        $encodedSubject = '=?UTF-8?B?' . base64_encode("Confirmação de solicitação de suporte: {$subject}") . '?=';
        $encodedFromName = '=?UTF-8?B?' . base64_encode('Nexus HUB - Suporte') . '?=';
        $html = "<!doctype html><html lang=\"pt-br\"><meta charset=\"UTF-8\"><body style=\"font-family:Arial,sans-serif;color:#263247;line-height:1.6\"><h2>Recebemos sua solicitação de suporte</h2><p>Olá {$safeName}, recebemos sua mensagem e nossa equipe entrará em contato.</p><p><strong>E-mail para contato:</strong> {$safeRecipient}<br><strong>Assunto:</strong> {$safeSubject}<br><strong>Telefone informado:</strong> {$safePhone}</p><div style=\"padding:16px;background:#f4f7fa;border-left:4px solid #10b981\"><strong>Mensagem enviada</strong><br>{$safeMessage}</div><p>Atenciosamente,<br>Equipe Nexus HUB</p></body></html>";
        $text = "Olá {$name}, recebemos sua mensagem e nossa equipe entrará em contato.\n\nE-mail para contato: {$recipient}\nAssunto: {$subject}\nTelefone informado: " . ($phone !== '' ? $phone : 'Não informado') . "\n\nMensagem enviada:\n{$message}\n\nEquipe Nexus HUB";
        $mailData = implode("\r\n", [
            "Date: " . date(DATE_RFC2822),
            "From: {$encodedFromName} <{$fromAddress}>",
            "To: <{$recipient}>",
            "Reply-To: <{$fromAddress}>",
            "Subject: {$encodedSubject}",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($text), 76, "\r\n"),
            "--{$boundary}",
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html), 76, "\r\n"),
            "--{$boundary}--",
            '',
        ]);

        $mailData = preg_replace('/(?m)^\./', '..', $mailData);
        nexusSmtpWrite($socket, $mailData . ".\r\n");
        nexusSmtpReadResponse($socket, [250]);
        nexusSmtpCommand($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
