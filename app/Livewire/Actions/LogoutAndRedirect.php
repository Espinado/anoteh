<?php

namespace App\Livewire\Actions;

use Illuminate\Http\RedirectResponse;

class LogoutAndRedirect
{
    public function __invoke(Logout $logout): RedirectResponse
    {
        $logout();

        return redirect('/');
    }
}
