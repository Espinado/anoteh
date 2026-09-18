<?php

namespace App\Notifications;

use App\Notifications\Channels\BirdSmsChannel;
use App\Notifications\Channels\BirdWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AnotehReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $kind, public readonly string $title, public readonly string $message, public readonly string $subjectType, public readonly int $subjectId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->mailEnabled()) {
            $channels[] = 'mail';
        }

        if ($this->birdSmsAvailable($notifiable)) {
            $channels[] = BirdSmsChannel::class;
        }

        if ($this->birdWhatsAppAvailable($notifiable)) {
            $channels[] = BirdWhatsAppChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting(__('app.reminder_greeting', ['name' => $notifiable->name]))
            ->line($this->message)
            ->line(__('app.reminder_review'));
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'message' => $this->message, 'subject_type' => $this->subjectType, 'subject_id' => $this->subjectId];
    }

    public function toBirdSms(object $notifiable): string
    {
        return $this->message;
    }

    /**
     * @return array{slug?: string, language?: string|null, components: list<array<string, mixed>>}
     */
    public function toBirdWhatsApp(object $notifiable): array
    {
        return [
            'language' => config('services.bird.whatsapp_template_language'),
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $this->message],
                    ],
                ],
            ],
        ];
    }

    private function mailEnabled(): bool
    {
        return (bool) config('services.notifications.mail_enabled', true)
            && config('mail.default') !== 'log';
    }

    private function birdSmsAvailable(object $notifiable): bool
    {
        return $this->hasE164Phone($notifiable)
            && (bool) config('services.bird.sms_enabled')
            && filled(config('services.bird.api_key'))
            && filled(config('services.bird.base_url'))
            && filled(config('services.bird.sms_from'));
    }

    private function birdWhatsAppAvailable(object $notifiable): bool
    {
        return $this->hasE164Phone($notifiable)
            && (bool) config('services.bird.whatsapp_enabled')
            && filled(config('services.bird.api_key'))
            && filled(config('services.bird.base_url'))
            && filled(config('services.bird.whatsapp_template_slug'));
    }

    private function hasE164Phone(object $notifiable): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', (string) ($notifiable->phone ?? '')) === 1;
    }
}
