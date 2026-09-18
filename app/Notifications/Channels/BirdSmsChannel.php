<?php

namespace App\Notifications\Channels;

use App\Contracts\BirdClientInterface;
use Illuminate\Notifications\Notification;

final class BirdSmsChannel
{
    public function __construct(private readonly BirdClientInterface $bird) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $this->isConfigured() || ! $this->isE164($notifiable->phone ?? null)) {
            return;
        }

        $this->bird->sendSms(
            (string) $notifiable->phone,
            $notification->toBirdSms($notifiable),
        );
    }

    private function isConfigured(): bool
    {
        return (bool) config('services.bird.sms_enabled')
            && filled(config('services.bird.api_key'))
            && filled(config('services.bird.base_url'))
            && filled(config('services.bird.sms_from'));
    }

    private function isE164(?string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', (string) $phone) === 1;
    }
}
