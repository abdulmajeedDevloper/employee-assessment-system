<?php

declare(strict_types=1);

namespace App\Notifications;

interface MailTransportInterface
{
    public function send(MailMessage $message): void;
}
