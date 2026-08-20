<?php

namespace App\Console\Commands;

use App\Enums\DefectSeverity;
use App\Enums\DefectStatus;
use App\Enums\DocumentStatus;
use App\Enums\MaintenancePlanStatus;
use App\Models\Defect;
use App\Models\ReminderDelivery;
use App\Models\ServiceRecord;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Models\VehicleMaintenancePlan;
use App\Notifications\AnotehReminder;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class SendDailyReminders extends Command
{
    protected $signature = 'anoteh:send-reminders {--date= : UTC date (Y-m-d)}';

    protected $description = 'Send idempotent maintenance and document reminders';

    public function handle(): int
    {
        $today = $this->option('date') ? CarbonImmutable::parse($this->option('date'), 'UTC')->startOfDay() : CarbonImmutable::today('UTC');
        $users = User::query()->whereIn('role', ['admin', 'manager'])->get();

        VehicleMaintenancePlan::query()->where('active', true)->with(['vehicle', 'template'])->chunkById(100, function ($plans) use ($users, $today) {
            foreach ($plans as $plan) {
                $status = $plan->calculateStatus(null, $today);
                if ($plan->status !== $status) {
                    $plan->status = $status;
                    $plan->save();
                }

                if ($status === MaintenancePlanStatus::Cancelled) {
                    continue;
                }

                if ($plan->next_due_date !== null) {
                    $days = (int) $today->diffInDays($plan->next_due_date, false);
                    $dateState = $days < 0 ? 'overdue' : ($days === 0 ? 'due' : ($days <= (int) $plan->template->soon_days ? 'soon' : null));
                    if ($dateState !== null) {
                        $this->notify(
                            $users,
                            $plan,
                            'maintenance_date_'.$dateState,
                            'Maintenance date '.$dateState,
                            sprintf('%s: %s is %s by date (%s).', $plan->vehicle->registration_number, $plan->template->name, $dateState, $plan->next_due_date->toDateString()),
                            $today,
                        );
                    }
                }

                if ($plan->next_due_odometer !== null) {
                    $remainingKm = (float) $plan->next_due_odometer - (float) $plan->vehicle->current_odometer;
                    $kmState = $remainingKm < 0 ? 'overdue' : ($remainingKm == 0.0 ? 'due' : ($remainingKm <= (float) $plan->template->soon_km ? 'soon' : null));
                    if ($kmState !== null) {
                        $this->notify(
                            $users,
                            $plan,
                            'maintenance_km_'.$kmState,
                            'Maintenance mileage '.$kmState,
                            sprintf('%s: %s is %s by mileage (due at %s km).', $plan->vehicle->registration_number, $plan->template->name, $kmState, $plan->next_due_odometer),
                            $today,
                        );
                    }
                }
            }
        });

        VehicleDocument::query()->whereNotNull('expires_on')->with('vehicle')->chunkById(100, function ($documents) use ($users, $today) {
            foreach ($documents as $document) {
                $status = $document->calculateStatus($today);

                if ($document->status !== $status) {
                    $document->status = $status;
                    $document->saveQuietly();
                }

                if ($status !== DocumentStatus::Valid) {
                    $this->notify(
                        $users,
                        $document,
                        'document_'.$status->value,
                        'Vehicle document '.$status->value,
                        sprintf('%s: %s document %s on %s.', $document->vehicle->registration_number, $document->type->value, $status === DocumentStatus::Expired ? 'expired' : 'expires', $document->expires_on->toDateString()),
                        $today,
                    );
                }
            }
        });

        Defect::query()
            ->where('severity', DefectSeverity::Critical->value)
            ->whereNotIn('status', [DefectStatus::Resolved->value, DefectStatus::Rejected->value])
            ->with('vehicle')
            ->chunkById(100, function ($defects) use ($users, $today) {
                foreach ($defects as $defect) {
                    $this->notify(
                        $users,
                        $defect,
                        'critical_defect_unresolved',
                        'Critical vehicle defect',
                        sprintf('%s: unresolved critical defect "%s".', $defect->vehicle->registration_number, $defect->title),
                        $today,
                    );
                }
            });

        ServiceRecord::query()
            ->whereNotNull('completed_at')
            ->where(function ($query): void {
                $query->whereNotNull('warranty_until_date')->orWhereNotNull('warranty_until_odometer');
            })
            ->with('vehicle')
            ->chunkById(100, function ($services) use ($users, $today) {
                foreach ($services as $service) {
                    if ($service->warranty_until_date !== null) {
                        $days = (int) $today->diffInDays($service->warranty_until_date, false);
                        if ($days <= config('reminders.warranty_warning_days', 30)) {
                            $state = $days < 0 ? 'expired' : 'soon';
                            $this->notify(
                                $users,
                                $service,
                                'warranty_date_'.$state,
                                'Service warranty '.$state,
                                sprintf('%s: warranty for service #%d %s on %s.', $service->vehicle->registration_number, $service->getKey(), $state === 'expired' ? 'expired' : 'expires', $service->warranty_until_date->toDateString()),
                                $today,
                            );
                        }
                    }

                    if ($service->warranty_until_odometer !== null) {
                        $remainingKm = (float) $service->warranty_until_odometer - (float) $service->vehicle->current_odometer;
                        if ($remainingKm <= (float) config('reminders.warranty_warning_km', 1000)) {
                            $state = $remainingKm < 0 ? 'expired' : 'soon';
                            $this->notify(
                                $users,
                                $service,
                                'warranty_km_'.$state,
                                'Service mileage warranty '.$state,
                                sprintf('%s: mileage warranty for service #%d is %s at %s km.', $service->vehicle->registration_number, $service->getKey(), $state, $service->warranty_until_odometer),
                                $today,
                            );
                        }
                    }
                }
            });

        $this->info('Daily reminders processed for '.$today->toDateString().'.');

        return self::SUCCESS;
    }

    private function notify(iterable $users, Model $subject, string $kind, string $title, string $message, CarbonImmutable $date): void
    {
        foreach ($users as $user) {
            $created = DB::transaction(function () use ($user, $subject, $kind, $date) {
                return ReminderDelivery::query()->insertOrIgnore([
                    'user_id' => $user->getKey(),
                    'remindable_type' => $subject->getMorphClass(),
                    'remindable_id' => $subject->getKey(),
                    'kind' => $kind,
                    'delivery_date' => $date->toDateString(),
                    'created_at' => now('UTC'),
                    'updated_at' => now('UTC'),
                ]);
            });

            if ($created) {
                $user->notify(new AnotehReminder(
                    $kind,
                    $title,
                    $message,
                    $subject->getMorphClass(),
                    $subject->getKey(),
                ));
            }
        }
    }
}
