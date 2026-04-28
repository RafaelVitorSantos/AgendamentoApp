<?php

namespace App\Services;

/**
 * Mailer SMTP puro (sem dependências externas).
 * Suporta STARTTLS e autenticação LOGIN/PLAIN.
 */
class MailService
{
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $fromAddress;
    private string $fromName;
    private int    $timeout = 15;

    public function __construct()
    {
        $this->host        = env('MAIL_HOST', 'localhost');
        $this->port        = (int) env('MAIL_PORT', 587);
        $this->username    = env('MAIL_USERNAME', '');
        $this->password    = env('MAIL_PASSWORD', '');
        $this->fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@agendapro.com.br');
        $this->fromName    = env('MAIL_FROM_NAME', 'AgendaPRO');
    }

    /**
     * Envia um e-mail. Lança \RuntimeException em caso de falha.
     *
     * @param string|array $to       'email@x.com' ou ['email@x.com' => 'Nome']
     * @param string       $subject
     * @param string       $htmlBody
     * @param string|null  $textBody  Texto plano opcional
     */
    public function send(string|array $to, string $subject, string $htmlBody, ?string $textBody = null): void
    {
        [$toAddress, $toName] = $this->parseRecipient($to);

        if (empty($this->host) || empty($this->username)) {
            error_log("MailService: SMTP não configurado. Descartando e-mail para {$toAddress}: {$subject}");
            return;
        }

        $boundary = '=_' . md5(uniqid('', true));
        $message  = $this->buildMessage($toAddress, $toName, $subject, $htmlBody, $textBody, $boundary);

        $this->sendViaSMTP($toAddress, $message);
    }

    private function parseRecipient(string|array $to): array
    {
        if (is_array($to)) {
            $email = array_key_first($to);
            $name  = $to[$email];
            return [$email, $name];
        }
        return [$to, $to];
    }

    private function buildMessage(
        string  $toAddress,
        string  $toName,
        string  $subject,
        string  $htmlBody,
        ?string $textBody,
        string  $boundary
    ): string {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFrom    = '=?UTF-8?B?' . base64_encode($this->fromName) . '?=';
        $encodedTo      = '=?UTF-8?B?' . base64_encode($toName) . '?=';
        $date           = date('r');

        $plain = $textBody ?? strip_tags($htmlBody);

        $lines = [];
        $lines[] = "Date: {$date}";
        $lines[] = "From: {$encodedFrom} <{$this->fromAddress}>";
        $lines[] = "To: {$encodedTo} <{$toAddress}>";
        $lines[] = "Subject: {$encodedSubject}";
        $lines[] = "MIME-Version: 1.0";
        $lines[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $lines[] = "";
        $lines[] = "--{$boundary}";
        $lines[] = "Content-Type: text/plain; charset=UTF-8";
        $lines[] = "Content-Transfer-Encoding: base64";
        $lines[] = "";
        $lines[] = chunk_split(base64_encode($plain));
        $lines[] = "--{$boundary}";
        $lines[] = "Content-Type: text/html; charset=UTF-8";
        $lines[] = "Content-Transfer-Encoding: base64";
        $lines[] = "";
        $lines[] = chunk_split(base64_encode($htmlBody));
        $lines[] = "--{$boundary}--";

        return implode("\r\n", $lines);
    }

    private function sendViaSMTP(string $toAddress, string $message): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        if (!$socket) {
            throw new \RuntimeException("SMTP: não foi possível conectar a {$this->host}:{$this->port} — {$errstr}");
        }

        try {
            $this->expect($socket, 220);
            $this->cmd($socket, "EHLO " . gethostname(), 250);

            // STARTTLS em porta 587
            if ($this->port === 587) {
                $this->cmd($socket, "STARTTLS", 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException("SMTP: falha ao iniciar TLS");
                }
                $this->cmd($socket, "EHLO " . gethostname(), 250);
            }

            // Autenticação
            if (!empty($this->username)) {
                $this->cmd($socket, "AUTH LOGIN", 334);
                $this->cmd($socket, base64_encode($this->username), 334);
                $this->cmd($socket, base64_encode($this->password), 235);
            }

            $this->cmd($socket, "MAIL FROM:<{$this->fromAddress}>", 250);
            $this->cmd($socket, "RCPT TO:<{$toAddress}>", 250);
            $this->cmd($socket, "DATA", 354);

            fwrite($socket, $message . "\r\n.\r\n");
            $this->expect($socket, 250);
            $this->cmd($socket, "QUIT", 221);
        } finally {
            fclose($socket);
        }
    }

    private function cmd($socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $expectedCode);
    }

    private function expect($socket, int $expectedCode): string
    {
        $response = '';
        $deadline = time() + $this->timeout;

        while (!feof($socket) && time() < $deadline) {
            $line = fgets($socket, 512);
            if ($line === false) break;
            $response .= $line;

            // Linha final do response não tem hífen após o código
            if (strlen($line) >= 4 && $line[3] !== '-') break;
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new \RuntimeException("SMTP inesperado {$code} (esperado {$expectedCode}): {$response}");
        }

        return $response;
    }
}
