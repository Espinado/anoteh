<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification
{

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject(__('app.invitation.subject'))
            ->greeting(__('app.invitation.greeting', ['name' => $notifiable->name]))
            ->line(__('app.invitation.message'))
            ->action(__('app.invitation.action'), $url)
            ->line(__('app.invitation.expires'));
    }
}
