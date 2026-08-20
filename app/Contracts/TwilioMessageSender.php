<?php

namespace App\Contracts;

interface TwilioMessageSender
{
    public function send(string $to, string $from, string $body): void;
}
