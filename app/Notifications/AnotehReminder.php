<?php

namespace App\Notifications;

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
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->greeting('Hello '.$notifiable->name.',')->line($this->message)->line('Please review this item in Anoteh.');
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'message' => $this->message, 'subject_type' => $this->subjectType, 'subject_id' => $this->subjectId];
    }
}
