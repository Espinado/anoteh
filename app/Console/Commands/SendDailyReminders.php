<?php

namespace App\Console\Commands;

use App\Models\ReminderDelivery;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AnotehReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SendDailyReminders extends Command
{
    protected $signature = 'anoteh:send-reminders {--date= : UTC date (Y-m-d)}';

    protected $description = 'Send idempotent inspection and OCTA expiry email reminders';

    public function handle(): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), 'UTC')->startOfDay()
            : CarbonImmutable::today('UTC');

        $users = $this->expiryRecipients();

        if ($users->isEmpty()) {
            $this->warn('No expiry reminder recipients found. Check EXPIRY_REMINDER_EMAILS and user accounts.');

            return self::FAILURE;
        }

        Vehicle::query()
            ->where(fn ($query) => $query->whereNotNull('inspection_until')->orWhereNotNull('octa_until'))
            ->chunkById(100, function ($vehicles) use ($today, $users): void {
                foreach ($vehicles as $vehicle) {
                    foreach (['inspection_until', 'octa_until'] as $kind) {
                        $expiry = $vehicle->{$kind};

                        if ($expiry === null) {
                            continue;
                        }

                        $expiryDate = CarbonImmutable::parse($expiry->toDateString(), 'UTC')->startOfDay();
                        $remainingDays = (int) $today->diffInDays($expiryDate, false);

                        if (! $this->shouldSendReminder($remainingDays)) {
                            continue;
                        }

                        $this->notify($users, $vehicle, $kind, $expiryDate, $remainingDays, $today);
                    }
                }
            });

        $this->info('Daily vehicle expiry reminders processed for '.$today->toDateString().'.');

        return self::SUCCESS;
    }

    /** @return Collection<int, User> */
    private function expiryRecipients(): Collection
    {
        $emails = config('reminders.expiry_recipient_emails', []);

        if ($emails === []) {
            return collect();
        }

        $users = User::query()->whereIn('email', $emails)->get();
        $found = $users->pluck('email')->map(static fn (string $email): string => strtolower($email))->all();
        $missing = array_values(array_diff($emails, $found));

        if ($missing !== []) {
            $this->warn('No user account for expiry reminder emails: '.implode(', ', $missing));
        }

        return $users;
    }

    private function shouldSendReminder(int $remainingDays): bool
    {
        if (in_array($remainingDays, [30, 20], true)) {
            return true;
        }

        return $remainingDays >= 0 && $remainingDays <= 10;
    }

    private function notify(iterable $users, Vehicle $vehicle, string $kind, CarbonImmutable $expiry, int $remainingDays, CarbonImmutable $today): void
    {
        $type = __('app.expiry_types.'.$kind);
        $state = $remainingDays === 0
            ? __('app.expiry_reminder_mail_today')
            : ($remainingDays > 0
                ? __('app.reminder_remaining', ['days' => $remainingDays])
                : __('app.reminder_overdue', ['days' => abs($remainingDays)]));
        $title = __('app.expiry_reminder_title', ['type' => $type]);
        $message = __('app.expiry_reminder_message', [
            'registration' => $vehicle->registration_number,
            'vehicle' => trim($vehicle->make.' '.$vehicle->model),
            'type' => $type,
            'date' => $expiry->format('d.m.Y'),
            'state' => $state,
        ]);
        $vehicleSummary = [
            'registration' => $vehicle->registration_number,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'year' => $vehicle->year,
            'type' => $type,
            'date' => $expiry->format('d.m.Y'),
            'remaining_days' => $remainingDays,
        ];

        foreach ($users as $user) {
            $created = DB::transaction(fn () => ReminderDelivery::query()->insertOrIgnore([
                'user_id' => $user->getKey(),
                'remindable_type' => $vehicle->getMorphClass(),
                'remindable_id' => $vehicle->getKey(),
                'kind' => $kind,
                'delivery_date' => $today->toDateString(),
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]));

            if ($created) {
                $user->notify(new AnotehReminder(
                    $kind,
                    $title,
                    $message,
                    $vehicle->getMorphClass(),
                    $vehicle->getKey(),
                    $vehicleSummary,
                ));
            }
        }
    }
}
