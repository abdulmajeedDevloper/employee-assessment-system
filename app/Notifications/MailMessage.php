<?php

declare(strict_types=1);

namespace App\Notifications;

final class MailMessage
{
    public function __construct(
        public string $recipient,
        public string $subject,
        public string $htmlBody,
        public string $textBody,
        public string $language = 'ar',
        public string $idempotencyKey = ''
    ) {
    }
}
