<?php

namespace Tests\Feature;

use App\Contracts\TwilioMessageSender;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AnotehReminder;
use App\Notifications\Channels\TwilioSmsChannel;
use App\Notifications\Channels\TwilioWhatsAppChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class NotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_twilio_channels_are_skipped_without_configuration(): void
    {
        config([
            'services.twilio.sms_enabled' => true,
            'services.twilio.sid' => null,
            'services.twilio.token' => null,
            'services.twilio.sms_from' => '+15005550006',
        ]);
        $sender = Mockery::mock(TwilioMessageSender::class);
        $sender->shouldNotReceive('send');

        (new TwilioSmsChannel($sender))->send(
            User::factory()->make(['phone' => '+37120000000']),
            $this->notification(),
        );
    }

    public function test_twilio_channels_send_expected_sms_and_whatsapp_payloads(): void
    {
        config([
            'services.twilio.sid' => 'ACtest',
            'services.twilio.token' => 'secret',
            'services.twilio.sms_enabled' => true,
            'services.twilio.sms_from' => '+15005550006',
            'services.twilio.whatsapp_enabled' => true,
            'services.twilio.whatsapp_from' => '+14155238886',
        ]);
        $user = User::factory()->make(['phone' => '+37120000000']);
        $sender = Mockery::mock(TwilioMessageSender::class);
        $sender->shouldReceive('send')->once()->with('+37120000000', '+15005550006', 'Reminder body');
        $sender->shouldReceive('send')->once()->with('whatsapp:+37120000000', 'whatsapp:+14155238886', 'Reminder body');

        (new TwilioSmsChannel($sender))->send($user, $this->notification());
        (new TwilioWhatsAppChannel($sender))->send($user, $this->notification());
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
