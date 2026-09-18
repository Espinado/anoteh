<?php

namespace App\Notifications\Channels;

use App\Contracts\BirdClientInterface;
use Illuminate\Notifications\Notification;

final class BirdWhatsAppChannel
{
    public function __construct(private readonly BirdClientInterface $bird) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone ?? null;
        $slug = (string) config('services.bird.whatsapp_template_slug');

        if (! $this->isConfigured($slug) || ! $this->isE164($phone)) {
            return;
        }

        $payload = $notification->toBirdWhatsApp($notifiable);

        $this->bird->sendWhatsAppTemplate(
            (string) $phone,
            $payload['slug'] ?? $slug,
            $payload['language'] ?? config('services.bird.whatsapp_template_language'),
            $payload['components'] ?? [],
        );
    }

    private function isConfigured(string $slug): bool
    {
        return (bool) config('services.bird.whatsapp_enabled')
            && filled(config('services.bird.api_key'))
            && filled(config('services.bird.base_url'))
            && filled($slug);
    }

    private function isE164(?string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', (string) $phone) === 1;
    }
}
