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

Run the queue worker for outbound notifications:

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

Send a test notification to the admin user:

```bash
php artisan anoteh:send-test-notification --sync
```

## Notifications

Reminders are emailed when vehicle inspection or OCTA dates reach configured
offsets: 30 and 20 days before expiry, then every day from 10 days before
expiry through the expiry date itself.

Recipients are configured with `EXPIRY_REMINDER_EMAILS` (comma-separated). Each
address must belong to an existing user account (default: `av@serviscentrs.lv`).

Channels:

- **Email** — Laravel mailer (`MAIL_*`). Set `MAIL_MAILER` to a real transport,
  not `log`, in production.
- **SMS** — Bird (`BIRD_*`).
- **WhatsApp** — Bird template send (`BIRD_WHATSAPP_*`).

Each recipient must have a valid E.164 phone (`+371...`) in profile for SMS and
WhatsApp. Enable Latvia under **Bird → SMS → Destinations** before sending SMS
there.

Example Bird configuration:

```env
BIRD_API_KEY=bk_eu1_...
BIRD_BASE_URL=https://eu1.platform.bird.com
BIRD_SMS_ENABLED=true
BIRD_SMS_FROM=ANOTEH
BIRD_WHATSAPP_ENABLED=true
BIRD_WHATSAPP_TEMPLATE_SLUG=your_template_slug
BIRD_WHATSAPP_TEMPLATE_LANGUAGE=ru
```

Test delivery:

```bash
php artisan anoteh:verify-bird --send
php artisan anoteh:verify-bird --send --sms
php artisan anoteh:verify-bird --send --whatsapp user@example.com
php artisan anoteh:send-test-notification --sync
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
