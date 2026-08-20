<?php

namespace App\Notifications;

use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\Channels\TwilioWhatsAppChannel;
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
        $channels = ['database', 'mail'];

        if ($this->twilioAvailable($notifiable, 'sms')) {
            $channels[] = TwilioSmsChannel::class;
        }

        if ($this->twilioAvailable($notifiable, 'whatsapp')) {
            $channels[] = TwilioWhatsAppChannel::class;
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

    public function toTwilioSms(object $notifiable): string
    {
        return $this->message;
    }

    public function toTwilioWhatsApp(object $notifiable): string
    {
        return $this->message;
    }

    private function twilioAvailable(object $notifiable, string $channel): bool
    {
        return preg_match('/^\+[1-9]\d{7,14}$/', (string) ($notifiable->phone ?? '')) === 1
            && (bool) config("services.twilio.{$channel}_enabled")
            && filled(config('services.twilio.sid'))
            && filled(config('services.twilio.token'))
            && filled(config("services.twilio.{$channel}_from"));
    }
}
