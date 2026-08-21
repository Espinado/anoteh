<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('vehicles.index', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirectRoute('vehicles.index', navigate: true);
    }
}; ?>

<div>
    <div class="mb-4 text-sm leading-6 text-slate-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-5 grid gap-3 sm:flex sm:items-center sm:justify-between">
        <x-primary-button class="w-full sm:w-auto" wire:click="sendVerification">
            {{ __('Resend Verification Email') }}
        </x-primary-button>

        <button wire:click="logout" type="submit" class="min-h-11 rounded-xl px-4 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
            {{ __('Log Out') }}
        </button>
    </div>
</div>
