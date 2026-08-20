<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['lv', 'ru'], true), 404);
        session(['locale' => $locale]);

        return back();
    }
}
