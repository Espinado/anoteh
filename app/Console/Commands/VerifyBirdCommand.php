<?php

namespace App\Console\Commands;

use App\Contracts\BirdClientInterface;
use App\Models\User;
use App\Notifications\AnotehReminder;
use Illuminate\Console\Command;
use Throwable;

final class VerifyBirdCommand extends Command
{
    protected $signature = 'anoteh:verify-bird
                            {email? : User email for channel preview and test delivery}
                            {--send : Send test SMS and/or WhatsApp via Bird}
                            {--sms : With --send, send SMS only}
                            {--whatsapp : With --send, send WhatsApp only}';

    protected $description = 'Verify Bird SMS/WhatsApp configuration and optionally send test messages';

    public function handle(BirdClientInterface $bird): int
    {
        $checks = [
            'api_key' => filled(config('services.bird.api_key')),
            'base_url' => filled(config('services.bird.base_url')),
            'sms_enabled' => (bool) config('services.bird.sms_enabled'),
            'sms_from' => filled(config('services.bird.sms_from')),
            'whatsapp_enabled' => (bool) config('services.bird.whatsapp_enabled'),
            'whatsapp_template_slug' => filled(config('services.bird.whatsapp_template_slug')),
        ];

        foreach ($checks as $name => $ok) {
            $this->line(sprintf('%s %s', $ok ? '✓' : '✗', $name));
        }

        if (! $checks['api_key'] || ! $checks['base_url']) {
            $this->error('Bird API credentials are missing in .env (BIRD_API_KEY, BIRD_BASE_URL).');

            return self::FAILURE;
        }

        if (! $checks['sms_enabled'] && ! $checks['whatsapp_enabled']) {
            $this->warn('Enable BIRD_SMS_ENABLED and/or BIRD_WHATSAPP_ENABLED to use messaging channels.');
        }

        if ($checks['sms_enabled'] && ! $checks['sms_from']) {
            $this->error('BIRD_SMS_FROM is required when SMS is enabled.');

            return self::FAILURE;
        }

        if ($checks['whatsapp_enabled'] && ! $checks['whatsapp_template_slug']) {
            $this->error('BIRD_WHATSAPP_TEMPLATE_SLUG is required when WhatsApp is enabled.');

            return self::FAILURE;
        }

        try {
            $bird->verifyConnection();
            $this->info('Bird API connection: OK');
        } catch (Throwable $exception) {
            $this->error('Bird API connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $email = $this->argument('email') ?? User::query()->where('role', 'admin')->orderBy('id')->value('email');
        $user = $email ? User::query()->where('email', $email)->first() : null;

        if ($user === null) {
            $this->warn('No user found for channel preview. Pass an email argument or create an admin user.');

            return self::SUCCESS;
        }

        $notification = new AnotehReminder(
            'test',
            __('app.test_notification_title'),
            __('app.test_notification_message', ['name' => $user->name]),
            'user',
            $user->id,
        );

        $this->line('User: '.$user->email.' | phone: '.($user->phone ?: 'not set'));
        $this->line('Active channels: '.implode(', ', $notification->via($user)));

        if (! $this->option('send')) {
            $this->line('Run with --send to deliver test SMS/WhatsApp through Bird.');

            return self::SUCCESS;
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', (string) ($user->phone ?? ''))) {
            $this->error('User phone must be in E.164 format (+371...). Update the profile phone number first.');

            return self::FAILURE;
        }

        $sendSms = $this->option('sms') || ! $this->option('whatsapp');
        $sendWhatsApp = $this->option('whatsapp') || ! $this->option('sms');
        $message = __('app.test_notification_message', ['name' => $user->name]);

        if ($sendSms) {
            if (! $checks['sms_enabled']) {
                $this->warn('SMS test skipped: BIRD_SMS_ENABLED=false');
            } else {
                $bird->sendSms((string) $user->phone, $message);
                $this->info('Test SMS sent via Bird.');
            }
        }

        if ($sendWhatsApp) {
            if (! $checks['whatsapp_enabled']) {
                $this->warn('WhatsApp test skipped: BIRD_WHATSAPP_ENABLED=false');
            } else {
                $bird->sendWhatsAppTemplate(
                    (string) $user->phone,
                    (string) config('services.bird.whatsapp_template_slug'),
                    (string) config('services.bird.whatsapp_template_language'),
                    [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $message],
                            ],
                        ],
                    ],
                );
                $this->info('Test WhatsApp template sent via Bird.');
            }
        }

        return self::SUCCESS;
    }
}
