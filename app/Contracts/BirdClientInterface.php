<?php

namespace App\Contracts;

interface BirdClientInterface
{
    public function verifyConnection(): void;

    public function sendSms(string $to, string $text): void;

    /**
     * @param  list<array<string, mixed>>  $components
     */
    public function sendWhatsAppTemplate(string $to, string $slug, ?string $language, array $components): void;
}
