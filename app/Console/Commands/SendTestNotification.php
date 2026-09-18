<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AnotehReminder;
use Illuminate\Console\Command;

final class SendTestNotification extends Command
{
    protected $signature = 'anoteh:send-test-notification {email? : Recipient email} {--sync : Send immediately without queue}';

    protected $description = 'Send a test reminder via mail and Bird SMS/WhatsApp channels';

    public function handle(): int
    {
        $email = $this->argument('email') ?? User::query()->where('role', 'admin')->value('email');

        if ($email === null) {
            $this->error('No admin user found. Pass an email argument.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("User not found: {$email}");

            return self::FAILURE;
        }

        $notification = new AnotehReminder(
            'test',
            __('app.test_notification_title'),
            __('app.test_notification_message', ['name' => $user->name]),
            'user',
            $user->id,
        );

        if ($this->option('sync')) {
            $user->notifyNow($notification);
        } else {
            $user->notify($notification);
        }

        $this->info("Test notification queued for {$user->email} (phone: ".($user->phone ?: 'not set').').');
        $this->line('Channels: '.implode(', ', $notification->via($user)));

        if (! $this->option('sync')) {
            $this->line('Run queue worker or wait for cron queue job.');
        }

        return self::SUCCESS;
    }
}
