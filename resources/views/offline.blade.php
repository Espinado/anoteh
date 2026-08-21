<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1d4ed8">
        <title>{{ __('app.offline_title') }} · Anoteh</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #f1f5f9; color: #0f172a; font-family: system-ui, -apple-system, sans-serif; }
            main { width: min(100%, 420px); padding: 28px; border: 1px solid #dbeafe; border-radius: 24px; background: white; box-shadow: 0 20px 45px rgba(15, 23, 42, .12); text-align: center; }
            .logo { display: block; width: 64px; height: 64px; margin: 0 auto 20px; border-radius: 18px; }
            h1 { margin: 0; font-size: 24px; }
            p { margin: 12px 0 22px; color: #475569; line-height: 1.55; }
            button { min-height: 44px; width: 100%; border: 0; border-radius: 12px; background: #1d4ed8; color: white; font: inherit; font-weight: 700; cursor: pointer; }
        </style>
    </head>
    <body>
        <main>
            <img class="logo" src="/images/icons/icon-192.png" alt="">
            <h1>{{ __('app.offline_title') }}</h1>
            <p>{{ __('app.offline_message') }}</p>
            <button type="button" onclick="window.location.reload()">{{ __('app.try_again') }}</button>
        </main>
    </body>
</html>
