<?php

declare(strict_types=1);

namespace App\Notifications;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class PhpMailerTransport implements MailTransportInterface
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config)
    {
        $this->validateConfig();
    }

    public static function fromEnvironment(): self
    {
        return new self([
            'host' => trim((string)env('MAIL_HOST', '')),
            'port' => (int)env('MAIL_PORT', 587),
            'username' => trim((string)env('MAIL_USERNAME', '')),
            'password' => (string)env('MAIL_PASSWORD', ''),
            'smtp_auth' => filter_var(env('MAIL_SMTP_AUTH', true), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true,
            'encryption' => strtolower(trim((string)env('MAIL_ENCRYPTION', 'tls'))),
            'from_address' => trim((string)env('MAIL_FROM_ADDRESS', '')),
            'from_name' => trim((string)env('MAIL_FROM_NAME', 'Employee Assessment System')),
            'timeout' => max(5, min(120, (int)env('MAIL_TIMEOUT_SECONDS', 20))),
        ]);
    }

    public function send(MailMessage $message): void
    {
        if (filter_var($message->recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('mail_recipient_invalid');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)$this->config['host'];
        $mail->Port = (int)$this->config['port'];
        $mail->SMTPAuth = (bool)$this->config['smtp_auth'];
        $mail->Username = (string)$this->config['username'];
        $mail->Password = (string)$this->config['password'];
        $mail->Timeout = (int)$this->config['timeout'];
        $mail->SMTPDebug = 0;
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Encoding = PHPMailer::ENCODING_BASE64;

        $encryption = (string)$this->config['encryption'];
        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom(
            (string)$this->config['from_address'],
            (string)$this->config['from_name']
        );
        if (preg_match('/^[a-f0-9]{64}$/D', $message->idempotencyKey) === 1) {
            $fromParts = explode('@', (string)$this->config['from_address'], 2);
            $messageDomain = preg_replace('/[^A-Za-z0-9.-]/', '', $fromParts[1] ?? 'localhost');
            $mail->MessageID = '<assessment-' . $message->idempotencyKey . '@' . $messageDomain . '>';
        }
        $mail->addAddress($message->recipient);
        $mail->isHTML(true);
        $mail->Subject = $message->subject;
        $mail->Body = $message->htmlBody;
        $mail->AltBody = $message->textBody;
        $mail->send();
    }

    private function validateConfig(): void
    {
        if ((string)($this->config['host'] ?? '') === '') {
            throw new RuntimeException('mail_host_missing');
        }

        $port = (int)($this->config['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('mail_port_invalid');
        }

        if (filter_var((string)($this->config['from_address'] ?? ''), FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('mail_from_address_invalid');
        }

        $encryption = (string)($this->config['encryption'] ?? '');
        if (!in_array($encryption, ['', 'none', 'tls', 'ssl'], true)) {
            throw new RuntimeException('mail_encryption_invalid');
        }

        if ((bool)($this->config['smtp_auth'] ?? false)) {
            if ((string)($this->config['username'] ?? '') === '') {
                throw new RuntimeException('mail_username_missing');
            }
            if ((string)($this->config['password'] ?? '') === '') {
                throw new RuntimeException('mail_password_missing');
            }
        }
    }
}
