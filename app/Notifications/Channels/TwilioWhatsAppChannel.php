<?php

namespace App\Notifications\Channels;

use App\Contracts\TwilioMessageSender;
use Illuminate\Notifications\Notification;

final class TwilioWhatsAppChannel
{
    public function __construct(private readonly TwilioMessageSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone ?? null;
        $from = config('services.twilio.whatsapp_from');

        if (! config('services.twilio.whatsapp_enabled') || ! $this->isE164($phone) || ! $from || ! $this->credentialsPresent()) {
            return;
        }

        $this->sender->send(
            $this->whatsappAddress($phone),
            $this->whatsappAddress($from),
            $notification->toTwilioWhatsApp($notifiable),
        );
    }

    private function credentialsPresent(): bool
    {
        return filled(config('services.twilio.sid')) && filled(config('services.twilio.token'));
    }

    private function whatsappAddress(string $phone): string
    {
        return str_starts_with($phone, 'whatsapp:') ? $phone : 'whatsapp:'.$phone;
    }

    private function isE164(?string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', (string) $phone) === 1;
    }
}
