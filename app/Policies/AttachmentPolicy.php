<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AttachmentPolicy extends BasePolicy
{
    public function view(User $user, Model $model): bool
    {
        return $model instanceof Attachment && $model->attachable !== null && Gate::forUser($user)->allows('view', $model->attachable);
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }
}
