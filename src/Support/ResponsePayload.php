<?php

namespace MohamedSaid\HttpClientCache\Support;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;

class ResponsePayload
{
    public static function fromResponse(Response $response, bool $serializeHeaders = true): array
    {
        return [
            'body' => $response->body(),
            'status' => $response->status(),
            'reason' => $response->reason(),
            'headers' => $serializeHeaders ? $response->headers() : [],
        ];
    }

    public static function toResponse(array $payload, bool $fromCache = true): Response
    {
        $psr = new PsrResponse(
            $payload['status'] ?? 200,
            $payload['headers'] ?? [],
            $payload['body'] ?? '',
            '1.1',
            $payload['reason'] ?? null,
        );

        $response = new Response($psr);

        CacheState::mark($response, $fromCache);

        return $response;
    }
}
