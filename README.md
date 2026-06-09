![Laravel HTTP Client Cache](https://raw.githubusercontent.com/eg-mohamed/laravel-http-client-cache/main/assets/og.png)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eg-mohamed/laravel-http-client-cache.svg?style=flat-square)](https://packagist.org/packages/eg-mohamed/laravel-http-client-cache)
[![Total Downloads](https://img.shields.io/packagist/dt/eg-mohamed/laravel-http-client-cache.svg?style=flat-square)](https://packagist.org/packages/eg-mohamed/laravel-http-client-cache)

Opt-in response caching for Laravel's HTTP client. Cache outbound API responses with a single
fluent call — without replacing the `Http` facade or touching Laravel core.

```php
use Illuminate\Support\Facades\Http;

$response = Http::cache('products-api', 600)
    ->get('https://api.example.com/products');

$response->fromCache(); // false on the first call, true on subsequent calls
```

The cache layer wraps the normal `PendingRequest`, so everything you already use keeps working:
`Http::fake()`, headers, query params, JSON payloads, retries, throwing, and the full
`Response` API (`body()`, `json()`, `status()`, `header()`, `successful()`, …).

## Installation

```bash
composer require eg-mohamed/laravel-http-client-cache
```

The service provider is auto-discovered. Publish the config file if you want to tweak defaults:

```bash
php artisan vendor:publish --tag="http-client-cache-config"
```

## Basic usage

Pass a cache key and a TTL (in seconds) to `Http::cache()`, then chain any normal HTTP client
method:

```php
$response = Http::cache('weather-today', 600)
    ->get('https://api.example.com/weather');

$response->fromCache(); // bool
$response->json();       // works exactly like a normal response
```

By default only **successful** `GET` requests are cached. The first request hits the network;
matching requests within the TTL are served from cache without sending another request.

## Flexible TTL (stale-while-revalidate)

Pass an array `[$fresh, $stale]` to use Laravel's `Cache::flexible()`. Values are served fresh
for `$fresh` seconds, then served stale (and refreshed in the background) up to `$stale`:

```php
$response = Http::cache('currency-rates', [60, 300])
    ->get('https://api.example.com/rates');
```

A plain integer, `DateInterval`, or `DateTimeInterface` uses normal `Cache::remember()` behavior.

## Caching read-only POST APIs

Some APIs expose read-only data over `POST` (reports, search, GraphQL). Opt those methods in
explicitly — string, array, and enum values are all accepted and normalized to uppercase:

```php
$response = Http::cache('erp-report', 600, methods: ['GET', 'POST'])
    ->post('https://legacy-erp.example.com/report', $payload);
```

Non-allowed methods simply bypass the cache and perform a normal request.

## Checking `fromCache()`

Every response carries a `fromCache()` flag:

```php
$response = Http::cache('products-api', 600)->get($url);

if ($response->fromCache()) {
    // served from cache
}
```

## Cache store

Route the cached payload to a specific store:

```php
Http::cache('github-releases', 600)
    ->cacheStore('redis')
    ->get('https://api.github.com/repos/laravel/framework/releases');
```

## Tags

Tags are applied only when the underlying store supports them (e.g. Redis). With a store that
does not support tagging, tags are silently ignored instead of throwing:

```php
Http::cache('github-releases', 600)
    ->cacheStore('redis')
    ->cacheTags(['github', 'releases'])
    ->get($url);
```

## Conditional caching

Decide per-response whether the result should be cached:

```php
Http::cache('products-api', 600)
    ->cacheWhen(fn ($response) => $response->successful() && $response->json('available'))
    ->get($url);
```

## Status-based caching

Limit caching to specific status codes:

```php
Http::cache('products-api', 600)
    ->cacheStatuses([200, 201])
    ->get($url);
```

## Disabling cache

Skip the cache for a single request with `dontCache()`:

```php
Http::cache('products-api', 600)
    ->dontCache()
    ->get($url);
```

Or disable the package globally via config (`enabled => false`) — every request passes straight
through to the network.

## Full fluent API

```php
Http::cache('products-api', 600, methods: ['GET'])
    ->cacheStore('redis')
    ->cacheTags(['external-api', 'products'])
    ->cacheKeyPrefix('http-client')
    ->cacheWhen(fn ($response) => $response->successful())
    ->cacheStatuses([200])
    ->cacheMethods(['GET', 'POST'])
    ->get($url);
```

| Method | Purpose |
| --- | --- |
| `cache($key, $ttl = null, $methods = null)` | Start a cached request. |
| `cacheStore(?string $store)` | Choose the cache store. |
| `cacheTags(array\|string $tags)` | Tag entries (tag-capable stores only). |
| `cacheKeyPrefix(?string $prefix)` | Override the cache key prefix. |
| `cacheWhen(Closure $callback)` | Cache only when the callback returns true. |
| `cacheStatuses(array\|int\|null $statuses)` | Cache only these status codes. |
| `cacheMethods(array\|string\|UnitEnum $methods)` | Override cacheable methods. |
| `dontCache()` | Bypass the cache for this request. |
| `fromCache()` *(on the response)* | Whether the response was served from cache. |

## Configuration

```php
return [
    'enabled' => true,                 // Master switch for the package
    'default_store' => null,           // Default cache store (null = app default)
    'key_prefix' => 'http-client-cache',
    'default_methods' => ['GET'],      // Methods cached by default
    'cache_successful_only' => true,   // Only cache 2xx responses
    'cache_statuses' => null,          // Restrict caching to these statuses
    'cache_failed' => false,           // Allow caching failed responses
    'respect_no_store' => false,       // Skip caching when the response sends Cache-Control: no-store/no-cache
    'default_ttl' => null,             // Fallback TTL when none is provided
    'tags' => [],                      // Default tags
    'serialize_headers' => true,       // Persist response headers
];
```

## IDE autocompletion

`Http::cache()` and `$response->fromCache()` are registered as macros, so editors don't
autocomplete them out of the box. This package ships an `ide-helper.php` stub at its root that
PhpStorm indexes automatically from `vendor/` — completion for `Http::cache(...)`,
`Factory`/`PendingRequest` chaining, and `Response::fromCache()` works with no setup.

If you use [`barryvdh/laravel-ide-helper`](https://github.com/barryvdh/laravel-ide-helper), the
macros are registered at boot and picked up automatically when you regenerate the helper:

```bash
php artisan ide-helper:generate
```

## Testing notes

`Http::cache()` wraps the real pending request, so `Http::fake()` keeps working. In tests, use
the `array` cache store and remember that a cache hit does **not** send another request:

```php
Http::fake(['*' => Http::response(['ok' => true], 200)]);

$first = Http::cache('k', 600)->get('https://api.test/a');
$second = Http::cache('k', 600)->get('https://api.test/a');

expect($first->fromCache())->toBeFalse();
expect($second->fromCache())->toBeTrue();

Http::assertSentCount(1);
```

## Behavior summary

- Only `GET` is cached by default; opt in to other methods explicitly.
- Only successful (2xx) responses are cached by default; failed responses are not.
- Method names are normalized to uppercase; string, array, and enum values are accepted.
- Array TTL uses `Cache::flexible()`; everything else uses `Cache::remember()`.
- Only a small serializable payload (body, status, reason, headers) is stored — never the full
  `Response` object.
- Tags are honored only on stores that support them.
- A cache hit never sends a second HTTP request.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
