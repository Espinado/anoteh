<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    public function log(string $event, Model $subject, ?User $actor = null, array $old = [], array $new = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'event' => $event,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => now('UTC'),
        ]);
    }
}
