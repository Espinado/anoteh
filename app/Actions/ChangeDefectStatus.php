<?php

namespace App\Actions;

use App\Enums\DefectStatus;
use App\Models\Defect;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ChangeDefectStatus
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function execute(Defect $defect, DefectStatus $status, User $actor, ?string $note = null): Defect
    {
        if (! $actor->canManage()) {
            throw new AuthorizationException('You are not allowed to change defect status.');
        }

        return DB::transaction(function () use ($defect, $status, $actor, $note) {
            $locked = Defect::query()->lockForUpdate()->findOrFail($defect->getKey());
            $from = $locked->status;
            if ($from === $status) {
                return $locked;
            } $locked->update(['status' => $status, 'resolved_at' => $status === DefectStatus::Resolved ? now('UTC') : null]);
            $locked->statusHistory()->create(['changed_by' => $actor->getKey(), 'from_status' => $from, 'to_status' => $status, 'note' => $note, 'changed_at' => now('UTC')]);
            $this->audit->log('defect.status_changed', $locked, $actor, ['status' => $from->value], ['status' => $status->value, 'note' => $note]);

            return $locked->refresh();
        }, 3);
    }
}
