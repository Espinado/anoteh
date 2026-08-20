<?php

namespace App\Console\Commands;

use App\Models\ReminderDelivery;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AnotehReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SendDailyReminders extends Command
{
    private const OFFSETS = [30, 14, 7, 3, 1];

    protected $signature = 'anoteh:send-reminders {--date= : UTC date (Y-m-d)}';

    protected $description = 'Send idempotent inspection and OCTA expiry reminders';

    public function handle(): int
    {
        $today = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), 'UTC')->startOfDay()
            : CarbonImmutable::today('UTC');

        $users = User::query()->whereIn('role', ['admin', 'manager'])->get();

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

                        if ($remainingDays >= 0 && ! in_array($remainingDays, self::OFFSETS, true)) {
                            continue;
                        }

                        $this->notify($users, $vehicle, $kind, $expiryDate, $remainingDays, $today);
                    }
                }
            });

        $this->info('Daily vehicle expiry reminders processed for '.$today->toDateString().'.');

        return self::SUCCESS;
    }

    private function notify(iterable $users, Vehicle $vehicle, string $kind, CarbonImmutable $expiry, int $remainingDays, CarbonImmutable $today): void
    {
        $type = __('app.expiry_types.'.$kind);
        $state = $remainingDays < 0
            ? __('app.reminder_overdue', ['days' => abs($remainingDays)])
            : __('app.reminder_remaining', ['days' => $remainingDays]);
        $title = __('app.expiry_reminder_title', ['type' => $type]);
        $message = __('app.expiry_reminder_message', [
            'registration' => $vehicle->registration_number,
            'vehicle' => trim($vehicle->make.' '.$vehicle->model),
            'type' => $type,
            'date' => $expiry->format('d.m.Y'),
            'state' => $state,
        ]);

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
                ));
            }
        }
    }
}
