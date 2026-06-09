<?php

/**
 * IDE autocompletion helper for eg-mohamed/laravel-http-client-cache.
 *
 * This file is NOT autoloaded or executed — it is intentionally excluded from Composer
 * autoload. It exists purely so IDEs such as PhpStorm can index the macros this package
 * registers on Laravel's HTTP client and offer completion for:
 *
 *   - Http::cache(...)         (facade, Factory, PendingRequest)
 *   - $response->fromCache()   (Response)
 *
 * Like Laravel's own `_ide_helper.php`, the class stubs below mirror framework classes
 * and must never be loaded at runtime.
 *
 * @noinspection PhpUnusedAliasInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

namespace Illuminate\Http\Client {
    /**
     * @method \MohamedSaid\HttpClientCache\CachedPendingRequest cache(string $key, \DateTimeInterface|\DateInterval|int|array|null $ttl = null, array|string|\UnitEnum|null $methods = null)
     */
    class Factory {}

    /**
     * @method \MohamedSaid\HttpClientCache\CachedPendingRequest cache(string $key, \DateTimeInterface|\DateInterval|int|array|null $ttl = null, array|string|\UnitEnum|null $methods = null)
     */
    class PendingRequest {}

    /**
     * @method bool fromCache()
     */
    class Response {}
}

namespace Illuminate\Support\Facades {
    /**
     * @method static \MohamedSaid\HttpClientCache\CachedPendingRequest cache(string $key, \DateTimeInterface|\DateInterval|int|array|null $ttl = null, array|string|\UnitEnum|null $methods = null)
     */
    class Http {}
}
