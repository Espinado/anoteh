<?php

namespace Tests\Feature;

use App\Contracts\BirdClientInterface;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AnotehReminder;
use App\Notifications\Channels\BirdSmsChannel;
use App\Notifications\Channels\BirdWhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bird_channels_are_skipped_without_configuration(): void
    {
        config([
            'services.bird.sms_enabled' => true,
            'services.bird.api_key' => null,
            'services.bird.base_url' => null,
            'services.bird.sms_from' => 'ANOTEH',
        ]);

        $bird = Mockery::mock(BirdClientInterface::class);
        $bird->shouldNotReceive('sendSms');
        $bird->shouldNotReceive('sendWhatsAppTemplate');

        (new BirdSmsChannel($bird))->send(
            User::factory()->make(['phone' => '+37120000000']),
            $this->notification(),
        );
    }

    public function test_bird_channels_send_expected_sms_and_whatsapp_payloads(): void
    {
        config([
            'services.bird.api_key' => 'bk_eu1_test',
            'services.bird.base_url' => 'https://eu1.platform.bird.com',
            'services.bird.sms_enabled' => true,
            'services.bird.sms_from' => 'ANOTEH',
            'services.bird.whatsapp_enabled' => true,
            'services.bird.whatsapp_template_slug' => 'bird_test',
            'services.bird.whatsapp_template_language' => 'ru',
        ]);
        $user = User::factory()->make(['phone' => '+37120000000']);
        $bird = Mockery::mock(BirdClientInterface::class);
        $bird->shouldReceive('sendSms')->once()->with('+37120000000', 'Reminder body');
        $bird->shouldReceive('sendWhatsAppTemplate')->once()->with(
            '+37120000000',
            'bird_test',
            'ru',
            Mockery::type('array'),
        );

        (new BirdSmsChannel($bird))->send($user, $this->notification());
        (new BirdWhatsAppChannel($bird))->send($user, $this->notification());
    }

    public function test_anoteh_reminder_uses_bird_channels_when_configured(): void
    {
        config([
            'mail.default' => 'array',
            'services.notifications.mail_enabled' => true,
            'services.bird.api_key' => 'bk_eu1_test',
            'services.bird.base_url' => 'https://eu1.platform.bird.com',
            'services.bird.sms_enabled' => true,
            'services.bird.sms_from' => 'ANOTEH',
            'services.bird.whatsapp_enabled' => true,
            'services.bird.whatsapp_template_slug' => 'bird_test',
        ]);

        $user = User::factory()->make(['phone' => '+37120000000']);
        $channels = $this->notification()->via($user);

        $this->assertContains(BirdSmsChannel::class, $channels);
        $this->assertContains(BirdWhatsAppChannel::class, $channels);
    }

    public function test_reminders_run_only_on_offsets_and_every_overdue_day(): void
    {
        Notification::fake();
        User::factory()->create(['role' => UserRole::Manager]);

        foreach ([30, 14, 7, 3, 1, -1, 0, 2] as $days) {
            Vehicle::factory()->create([
                'inspection_until' => now('UTC')->parse('2026-08-20')->addDays($days)->toDateString(),
                'octa_until' => null,
            ]);
        }

        $this->artisan('anoteh:send-reminders', ['--date' => '2026-08-20'])->assertSuccessful();
        $this->assertDatabaseCount('reminder_deliveries', 6);
        Notification::assertCount(6);

        $this->artisan('anoteh:send-reminders', ['--date' => '2026-08-21'])->assertSuccessful();
        $this->assertDatabaseCount('reminder_deliveries', 9);
        Notification::assertCount(9);
    }

    private function notification(): AnotehReminder
    {
        return new AnotehReminder('inspection_until', 'Reminder', 'Reminder body', 'vehicle', 1);
    }
}
