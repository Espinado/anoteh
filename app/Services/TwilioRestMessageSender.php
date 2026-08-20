<?php

namespace App\Services;

use App\Contracts\TwilioMessageSender;
use Twilio\Rest\Client;

final class TwilioRestMessageSender implements TwilioMessageSender
{
    public function __construct(private readonly Client $client) {}

    public function send(string $to, string $from, string $body): void
    {
        $this->client->messages->create($to, [
            'from' => $from,
            'body' => $body,
        ]);
    }
}
