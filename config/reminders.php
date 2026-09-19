<?php

return [
    'warranty_warning_days' => (int) env('WARRANTY_WARNING_DAYS', 30),
    'warranty_warning_km' => (float) env('WARRANTY_WARNING_KM', 1000),

    'expiry_recipient_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('EXPIRY_REMINDER_EMAILS', 'av@serviscentrs.lv')),
    ))),
];
