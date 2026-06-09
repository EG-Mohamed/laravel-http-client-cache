<?php

namespace MohamedSaid\HttpClientCache;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use MohamedSaid\HttpClientCache\Support\NormalizesHttpMethods;
use UnitEnum;

class CacheOptions
{
    use NormalizesHttpMethods;

    protected array $methods;

    protected array $tags;

    protected ?string $store;

    protected ?string $prefix;

    protected ?Closure $when = null;

    protected ?array $statuses;

    protected bool $enabled;

    protected bool $cacheSuccessfulOnly;

    protected bool $cacheFailed;

    protected bool $serializeHeaders;

    protected bool $respectNoStore;

    public function __construct(
        protected string $key,
        protected DateTimeInterface|DateInterval|int|array|null $ttl = null,
        array|string|UnitEnum|null $methods = null,
    ) {
        if (trim($key) === '') {
            throw new InvalidArgumentException('A cache key is required.');
        }

        $this->enabled = (bool) $this->config('enabled', true);
        $this->store = $this->config('default_store');
        $this->prefix = $this->config('key_prefix', 'http-client-cache');
        $this->tags = (array) $this->config('tags', []);
        $this->cacheSuccessfulOnly = (bool) $this->config('cache_successful_only', true);
        $this->cacheFailed = (bool) $this->config('cache_failed', false);
        $this->serializeHeaders = (bool) $this->config('serialize_headers', true);
        $this->respectNoStore = (bool) $this->config('respect_no_store', false);

        $configStatuses = $this->config('cache_statuses');
        $this->statuses = $configStatuses === null ? null : array_map('intval', (array) $configStatuses);

        $this->ttl ??= $this->config('default_ttl');

        $normalized = $this->normalizeMethods($methods);
        $this->methods = $normalized !== []
            ? $normalized
            : $this->normalizeMethods((array) $this->config('default_methods', ['GET']));
    }

    public static function make(
        string $key,
        DateTimeInterface|DateInterval|int|array|null $ttl = null,
        array|string|UnitEnum|null $methods = null,
    ): self {
        return new self($key, $ttl, $methods);
    }

    public function store(?string $store): self
    {
        $this->store = $store;

        return $this;
    }

    public function tags(array|string $tags): self
    {
        $this->tags = is_array($tags) ? array_values($tags) : [$tags];

        return $this;
    }

    public function prefix(?string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function when(Closure $callback): self
    {
        $this->when = $callback;

        return $this;
    }

    public function statuses(array|int|null $statuses): self
    {
        $this->statuses = $statuses === null ? null : array_map('intval', (array) $statuses);

        return $this;
    }

    public function methods(array|string|UnitEnum $methods): self
    {
        $this->methods = $this->normalizeMethods($methods);

        return $this;
    }

    public function dontCache(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isMethodCacheable(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods, true);
    }

    public function isResponseCacheable(Response $response): bool
    {
        if ($this->respectNoStore && $this->hasNoStoreDirective($response)) {
            return false;
        }

        if ($this->when !== null && ! ($this->when)($response)) {
            return false;
        }

        if ($this->statuses !== null) {
            return in_array($response->status(), $this->statuses, true);
        }

        if (! $this->cacheFailed && $this->cacheSuccessfulOnly && ! $response->successful()) {
            return false;
        }

        return true;
    }

    protected function hasNoStoreDirective(Response $response): bool
    {
        $cacheControl = strtolower($response->header('Cache-Control'));

        return str_contains($cacheControl, 'no-store') || str_contains($cacheControl, 'no-cache');
    }

    public function ttl(): DateTimeInterface|DateInterval|int|array|null
    {
        return $this->ttl;
    }

    public function getStore(): ?string
    {
        return $this->store;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function serializesHeaders(): bool
    {
        return $this->serializeHeaders;
    }

    public function cacheKey(string $method, string $url, array $options = []): string
    {
        $signature = json_encode([
            'method' => strtoupper($method),
            'url' => $url,
            'query' => $options['query'] ?? null,
            'body' => $options['body'] ?? ($options['json'] ?? ($options['form_params'] ?? null)),
            'key' => $this->key,
        ]);

        return $this->prefix.':'.sha1((string) $signature);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return config("http-client-cache.{$key}", $default);
    }
}
