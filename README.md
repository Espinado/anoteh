# Anoteh

Anoteh is a single-company fleet maintenance system. It manages vehicle service
books, odometer readings, maintenance plans, defects, service work, expenses,
documents, reminders, and reports. Logistics, cargo, and trip workflows are
intentionally outside the product scope.

## Stack

- PHP 8.4 and Laravel 13
- Livewire 3 with Volt and Breeze authentication
- Tailwind CSS, Alpine.js, and Vite
- MySQL in production; SQLite is supported for local development and tests

Laravel 13 is used instead of the originally planned Laravel 11 because Composer
blocks the Laravel 11 releases affected by published security advisories.

## Local setup

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Demo administrator:

- Email: `admin@anoteh.local`
- Password: `password`

Change the demo credentials before using the application outside local
development.

## Background processes

Run the queue worker for email notifications:

```bash
php artisan queue:work
```

Run Laravel Scheduler every minute in production. It invokes the idempotent
daily reminder command at 07:00 UTC:

```bash
php artisan schedule:run
```

The command can also be launched manually:

```bash
php artisan anoteh:send-reminders
```

## Verification

```bash
vendor/bin/pint --test
php artisan test
npm run build
```

Attachments are stored on the private local disk. They must only be downloaded
through the authenticated `attachments.download` route; do not publish or
symlink `storage/app/private`.
