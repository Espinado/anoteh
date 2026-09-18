<?php

namespace App\Services;

use App\Contracts\BirdClientInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BirdHttpClient implements BirdClientInterface
{
    public function verifyConnection(): void
    {
        $baseUrl = rtrim((string) config('services.bird.base_url'), '/');
        $apiKey = (string) config('services.bird.api_key');

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post($baseUrl.'/v1/sms/messages', []);

        if ($response->status() === 401) {
            $response->throw();
        }

        if ($response->successful() || in_array($response->status(), [400, 403, 422], true)) {
            return;
        }

        $response->throw();
    }

    public function sendSms(string $to, string $text): void
    {
        $this->request('post', '/v1/sms/messages', [
            'to' => $to,
            'from' => (string) config('services.bird.sms_from'),
            'text' => $text,
            'category' => (string) config('services.bird.sms_category', 'transactional'),
        ]);
    }

    public function sendWhatsAppTemplate(string $to, string $slug, ?string $language, array $components): void
    {
        $template = array_filter([
            'slug' => $slug,
            'language' => $language,
            'components' => $components === [] ? null : $components,
        ], fn (mixed $value) => $value !== null);

        $this->request('post', '/v1/whatsapp/messages', [
            'to' => $to,
            'template' => $template,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $path, array $payload = []): void
    {
        $baseUrl = rtrim((string) config('services.bird.base_url'), '/');
        $apiKey = (string) config('services.bird.api_key');

        try {
            $request = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20);

            $response = match (strtolower($method)) {
                'get' => $request->get($baseUrl.$path, $payload),
                default => $request->asJson()->{$method}($baseUrl.$path, $payload),
            };

            $response->throw();
        } catch (RequestException $exception) {
            Log::error('Bird API request failed', [
                'path' => $path,
                'status' => $exception->response?->status(),
                'body' => $exception->response?->json(),
            ]);

            throw $exception;
        }
    }
}
