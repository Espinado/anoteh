<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class PasswordlessAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('auth.passwordless_access') && Auth::guest()) {
            $admin = User::query()
                ->where('role', UserRole::Admin->value)
                ->orderBy('id')
                ->first();

            abort_if($admin === null, 503, 'Passwordless access requires an administrator account.');

            Auth::login($admin);
        }

        return $next($request);
    }
}
