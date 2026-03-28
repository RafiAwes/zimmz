<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Support\Facades\Http;

class HttpBroadcaster extends Broadcaster
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function auth($request)
    {
        abort(403);
    }

    public function validAuthenticationResponse($request, $result)
    {
        return $result;
    }

    public function broadcast(array $channels, $event, array $payload = []): void
    {
        $endpoint = $this->config['endpoint'] ?? env('HTTP_BROADCAST_URL');
        if (empty($endpoint)) {
            return;
        }

        $body = [
            'channels' => $channels,
            'event' => $event,
            'payload' => $payload,
        ];

        try {
            Http::timeout(5)->post($endpoint, $body);
        } catch (\Throwable $e) {
            // swallow network errors; logging handled elsewhere
        }
    }
}
