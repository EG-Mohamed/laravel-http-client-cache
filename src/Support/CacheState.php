<?php

namespace MohamedSaid\HttpClientCache\Support;

use Illuminate\Http\Client\Response;
use WeakMap;

class CacheState
{
    /**
     * @var WeakMap<Response, bool>
     */
    protected static WeakMap $flags;

    public static function mark(Response $response, bool $fromCache): void
    {
        static::map()[$response] = $fromCache;
    }

    public static function fromCache(Response $response): bool
    {
        return static::map()[$response] ?? false;
    }

    /**
     * @return WeakMap<Response, bool>
     */
    protected static function map(): WeakMap
    {
        return static::$flags ??= new WeakMap;
    }
}
