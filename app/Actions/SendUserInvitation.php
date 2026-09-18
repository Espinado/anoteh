<?php

namespace App\Actions;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;

class SendUserInvitation
{
    public function __invoke(User $user): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(new UserInvitation($token));
    }
}
