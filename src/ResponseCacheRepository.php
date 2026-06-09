<?php

namespace MohamedSaid\HttpClientCache;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use MohamedSaid\HttpClientCache\Exceptions\UncacheableResponseException;
use RuntimeException;

class ResponseCacheRepository
{
    public function __construct(protected CacheOptions $options) {}

    public function remember(string $key, Closure $producer): array
    {
        $ttl = $this->options->ttl();

        $store = $this->store();

        try {
            if (is_array($ttl)) {
                return $store->flexible($key, $ttl, $producer);
            }

            return $store->remember($key, $ttl, $producer);
        } catch (UncacheableResponseException $exception) {
            return $exception->payload;
        }
    }

    protected function store(): Repository
    {
        $store = Cache::store($this->options->getStore());

        if (! $store instanceof Repository) {
            throw new RuntimeException('The configured cache store is not supported.');
        }

        $tags = $this->options->getTags();

        if ($tags !== [] && $store->supportsTags()) {
            return $store->tags($tags);
        }

        return $store;
    }
}
