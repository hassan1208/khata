<?php
/*
 * Chhota sa SMTP client (koi library nahi chahiye).
 * Encryption: 'ssl' (port 465), 'tls' = STARTTLS (port 587), 'none'.
 * Gmail k liye App Password use karein.
 */
class SmtpMailer
{
    private $sock;
    private array $log = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $encryption,
        private string $user,
        private string $pass,
        private string $fromEmail,
        private string $fromName
    ) {}

    public static function fromSettings(): self
    {
        return new self(
            setting('smtp_host'),
            (int)setting('smtp_port', 587),
            setting('smtp_encryption', 'tls'),
            setting('smtp_user'),
            decrypt_secret(setting('smtp_pass')),
            setting('smtp_from_email'),
            setting('smtp_from_name', APP_NAME)
        );
    }

    public function getLog(): string
    {
        return implode("\n", $this->log);
    }

    /** @throws RuntimeException */
    public function send(string $to, string $subject, string $html): void
    {
        if ($this->host === '') {
            throw new RuntimeException('SMTP host set nahi hai.');
        }
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $ctx = stream_context_create(['ssl' => ['SNI_enabled' => true, 'peer_name' => $this->host]]);
        $this->sock = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) {
            throw new RuntimeException("SMTP server se connect nahi ho saka: $errstr ($errno)");
        }
        stream_set_timeout($this->sock, 20);

        try {
            $this->expect(220);
            $helo = gethostname() ?: 'localhost';
            $this->cmd("EHLO $helo", 250);

            if ($this->encryption === 'tls') {
                $this->cmd('STARTTLS', 220);
                $ok = stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$ok) {
                    throw new RuntimeException('STARTTLS fail ho gaya.');
                }
                $this->cmd("EHLO $helo", 250);
            }

            if ($this->user !== '') {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($this->user), 334, true);
                $this->cmd(base64_encode($this->pass), 235, true);
            }

            $this->cmd('MAIL FROM:<' . $this->fromEmail . '>', 250);
            $this->cmd('RCPT TO:<' . $to . '>', [250, 251]);
            $this->cmd('DATA', 354);

            $headers = [
                'Date: ' . date('r'),
                'From: ' . $this->encodeHeader($this->fromName) . ' <' . $this->fromEmail . '>',
                'To: <' . $to . '>',
                'Subject: ' . $this->encodeHeader($subject),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $this->fromEmail)[1] ?? 'localhost') . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $body = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($html), 76, "\r\n");
            fwrite($this->sock, $body . "\r\n.\r\n");
            $this->expect(250);
            $this->cmd('QUIT', 221);
        } finally {
            if (is_resource($this->sock)) {
                fclose($this->sock);
            }
        }
    }

    private function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private function cmd(string $line, $expect, bool $secret = false): string
    {
        $this->log[] = 'C: ' . ($secret ? '********' : $line);
        fwrite($this->sock, $line . "\r\n");
        return $this->expect($expect);
    }

    private function expect($codes): string
    {
        $codes = (array)$codes;
        $resp = '';
        while (($line = fgets($this->sock, 1024)) !== false) {
            $resp .= $line;
            // "250-..." ka matlab aur lines aa rahi hain, "250 ..." aakhri line
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = 'S: ' . trim($resp);
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: ' . ($resp === '' ? 'server ne jawab nahi diya' : trim($resp)));
        }
        return $resp;
    }
}

function send_mail(string $to, string $subject, string $html): void
{
    SmtpMailer::fromSettings()->send($to, $subject, $html);
}
