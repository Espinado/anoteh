<?php

namespace App\Actions;

use App\Models\Attachment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetPrimaryVehiclePhoto
{
    public function execute(Vehicle $vehicle, ?Attachment $attachment, User $actor): Vehicle
    {
        if (! $actor->canManage()) {
            throw new AuthorizationException('You are not allowed to update the primary vehicle photo.');
        }

        if ($attachment !== null) {
            if (! $attachment->attachable?->is($vehicle)) {
                throw ValidationException::withMessages([
                    'attachment' => 'The attachment must belong to this vehicle.',
                ]);
            }

            if (! str_starts_with((string) $attachment->mime_type, 'image/')) {
                throw ValidationException::withMessages([
                    'attachment' => 'The primary vehicle attachment must be an image.',
                ]);
            }

            if (! in_array($attachment->disk, config('attachments.private_disks', []), true)) {
                throw ValidationException::withMessages([
                    'attachment' => 'The attachment must use an approved private disk.',
                ]);
            }
        }

        return DB::transaction(function () use ($vehicle, $attachment) {
            $locked = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->getKey());
            $locked->update(['primary_attachment_id' => $attachment?->getKey()]);

            return $locked->refresh()->load('primaryAttachment');
        }, 3);
    }
}
