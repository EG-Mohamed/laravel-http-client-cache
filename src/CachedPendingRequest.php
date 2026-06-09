<?php

namespace MohamedSaid\HttpClientCache;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use MohamedSaid\HttpClientCache\Exceptions\UncacheableResponseException;
use MohamedSaid\HttpClientCache\Support\ResponsePayload;
use UnitEnum;

class CachedPendingRequest
{
    public function __construct(
        protected PendingRequest $pendingRequest,
        protected CacheOptions $options,
    ) {}

    public function cacheStore(?string $store): self
    {
        $this->options->store($store);

        return $this;
    }

    public function cacheTags(array|string $tags): self
    {
        $this->options->tags($tags);

        return $this;
    }

    public function cacheKeyPrefix(?string $prefix): self
    {
        $this->options->prefix($prefix);

        return $this;
    }

    public function cacheWhen(Closure $callback): self
    {
        $this->options->when($callback);

        return $this;
    }

    public function cacheStatuses(array|int|null $statuses): self
    {
        $this->options->statuses($statuses);

        return $this;
    }

    public function cacheMethods(array|string|UnitEnum $methods): self
    {
        $this->options->methods($methods);

        return $this;
    }

    public function dontCache(): self
    {
        $this->options->dontCache();

        return $this;
    }

    public function get(string $url, $query = null): Response
    {
        return $this->intercept('GET', $url, ['query' => $query], fn () => $this->pendingRequest->get($url, $query));
    }

    public function head(string $url, $query = null): Response
    {
        return $this->intercept('HEAD', $url, ['query' => $query], fn () => $this->pendingRequest->head($url, $query));
    }

    public function post(string $url, $data = []): Response
    {
        return $this->intercept('POST', $url, ['body' => $data], fn () => $this->pendingRequest->post($url, $data));
    }

    public function patch(string $url, $data = []): Response
    {
        return $this->intercept('PATCH', $url, ['body' => $data], fn () => $this->pendingRequest->patch($url, $data));
    }

    public function put(string $url, $data = []): Response
    {
        return $this->intercept('PUT', $url, ['body' => $data], fn () => $this->pendingRequest->put($url, $data));
    }

    public function delete(string $url, $data = []): Response
    {
        return $this->intercept('DELETE', $url, ['body' => $data], fn () => $this->pendingRequest->delete($url, $data));
    }

    public function send(string $method, string $url, array $options = []): Response
    {
        return $this->intercept($method, $url, $options, fn () => $this->pendingRequest->send($method, $url, $options));
    }

    protected function intercept(string $method, string $url, array $options, Closure $request): Response
    {
        if (! $this->options->isEnabled() || ! $this->options->isMethodCacheable($method)) {
            return $request();
        }

        $produced = false;

        $payload = (new ResponseCacheRepository($this->options))->remember(
            $this->options->cacheKey($method, $url, $options),
            function () use ($request, &$produced): array {
                $produced = true;

                $response = $request();

                $payload = ResponsePayload::fromResponse($response, $this->options->serializesHeaders());

                if (! $this->options->isResponseCacheable($response)) {
                    throw new UncacheableResponseException($payload);
                }

                return $payload;
            },
        );

        return ResponsePayload::toResponse($payload, fromCache: ! $produced);
    }

    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->pendingRequest->{$method}(...$parameters);

        if ($result instanceof PendingRequest) {
            $this->pendingRequest = $result;

            return $this;
        }

        return $result;
    }
}
