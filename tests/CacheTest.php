<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

enum HttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';
}

beforeEach(function () {
    config()->set('http-client-cache.enabled', true);
    config()->set('http-client-cache.default_methods', ['GET']);
    config()->set('http-client-cache.key_prefix', 'http-client-cache');
    config()->set('http-client-cache.default_store', null);
    config()->set('http-client-cache.cache_statuses', null);
    config()->set('http-client-cache.cache_failed', false);
    config()->set('http-client-cache.cache_successful_only', true);
    config()->set('http-client-cache.tags', []);

    Cache::store('array')->clear();
});

describe('basic', function () {
    it('caches successful GET responses', function () {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $first = Http::cache('products', 600)->get('https://api.test/products');
        $second = Http::cache('products', 600)->get('https://api.test/products');

        expect($first->fromCache())->toBeFalse();
        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('first response is not from cache', function () {
        Http::fake(['*' => Http::response('hello', 200)]);

        $response = Http::cache('k', 600)->get('https://api.test/a');

        expect($response->fromCache())->toBeFalse();
    });

    it('preserves body, status, reason, and headers on a hit', function () {
        Http::fake(['*' => Http::response('payload-body', 201, ['X-Custom' => 'value'])]);

        Http::cache('k', 600)->get('https://api.test/a');
        $hit = Http::cache('k', 600)->get('https://api.test/a');

        expect($hit->fromCache())->toBeTrue();
        expect($hit->body())->toBe('payload-body');
        expect($hit->status())->toBe(201);
        expect($hit->header('X-Custom'))->toBe('value');
    });

    it('works with Http::fake', function () {
        Http::fake(['*' => Http::response(['name' => 'Taylor'], 200)]);

        $response = Http::cache('k', 600)->get('https://api.test/a');

        expect($response->json('name'))->toBe('Taylor');
        expect($response->successful())->toBeTrue();
    });
});

describe('ttl', function () {
    it('uses normal cache behaviour for integer ttl', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->get('https://api.test/a');

        expect(Cache::store('array')->has('http-client-cache:'.sha1(json_encode([
            'method' => 'GET',
            'url' => 'https://api.test/a',
            'query' => null,
            'body' => null,
            'key' => 'k',
        ]))))->toBeTrue();
    });

    it('uses flexible cache behaviour for array ttl', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        $first = Http::cache('k', [60, 300])->get('https://api.test/a');
        $second = Http::cache('k', [60, 300])->get('https://api.test/a');

        expect($first->fromCache())->toBeFalse();
        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });
});

describe('methods', function () {
    it('caches GET by default', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->get('https://api.test/a');
        $hit = Http::cache('k', 600)->get('https://api.test/a');

        expect($hit->fromCache())->toBeTrue();
    });

    it('does not cache POST by default', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->post('https://api.test/a', ['a' => 1]);
        $second = Http::cache('k', 600)->post('https://api.test/a', ['a' => 1]);

        expect($second->fromCache())->toBeFalse();
        Http::assertSentCount(2);
    });

    it('caches POST when methods include POST', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600, methods: ['GET', 'POST'])->post('https://api.test/a', ['a' => 1]);
        $second = Http::cache('k', 600, methods: ['GET', 'POST'])->post('https://api.test/a', ['a' => 1]);

        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('normalizes lowercase methods', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600, methods: ['get', 'post'])->post('https://api.test/a', ['a' => 1]);
        $second = Http::cache('k', 600, methods: ['get', 'post'])->post('https://api.test/a', ['a' => 1]);

        expect($second->fromCache())->toBeTrue();
    });

    it('supports enum methods', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600, methods: [HttpMethod::Get, HttpMethod::Post])->post('https://api.test/a');
        $second = Http::cache('k', 600, methods: [HttpMethod::Get, HttpMethod::Post])->post('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
    });
});

describe('failures', function () {
    it('does not cache failed responses by default', function () {
        Http::fake(['*' => Http::response('error', 500)]);

        Http::cache('k', 600)->get('https://api.test/a');
        $second = Http::cache('k', 600)->get('https://api.test/a');

        expect($second->fromCache())->toBeFalse();
        Http::assertSentCount(2);
    });

    it('caches failed responses when explicitly configured', function () {
        config()->set('http-client-cache.cache_failed', true);
        config()->set('http-client-cache.cache_successful_only', false);

        Http::fake(['*' => Http::response('error', 500)]);

        Http::cache('k', 600)->get('https://api.test/a');
        $second = Http::cache('k', 600)->get('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('limits caching to specific statuses', function () {
        Http::fake(['*' => Http::response('created', 201)]);

        Http::cache('k', 600)->cacheStatuses([200])->get('https://api.test/a');
        $second = Http::cache('k', 600)->cacheStatuses([200])->get('https://api.test/a');

        expect($second->fromCache())->toBeFalse();
        Http::assertSentCount(2);
    });
});

describe('config', function () {
    it('can disable caching globally', function () {
        config()->set('http-client-cache.enabled', false);

        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->get('https://api.test/a');
        $second = Http::cache('k', 600)->get('https://api.test/a');

        expect($second->fromCache())->toBeFalse();
        Http::assertSentCount(2);
    });

    it('reads default methods from config', function () {
        config()->set('http-client-cache.default_methods', ['GET', 'POST']);

        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->post('https://api.test/a');
        $second = Http::cache('k', 600)->post('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
    });

    it('uses the default store from config', function () {
        config()->set('cache.stores.configured', ['driver' => 'array']);
        config()->set('http-client-cache.default_store', 'configured');

        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->get('https://api.test/a');
        $second = Http::cache('k', 600)->get('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('reads tags from config without breaking unsupported stores', function () {
        config()->set('http-client-cache.tags', ['external-api']);

        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->get('https://api.test/a');
        $second = Http::cache('k', 600)->get('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('reads key prefix from config', function () {
        config()->set('http-client-cache.key_prefix', 'custom-prefix');

        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->get('https://api.test/a');

        expect(Cache::store('array')->has('custom-prefix:'.sha1(json_encode([
            'method' => 'GET',
            'url' => 'https://api.test/a',
            'query' => null,
            'body' => null,
            'key' => 'k',
        ]))))->toBeTrue();
    });
});

describe('fluent api', function () {
    it('supports cacheKeyPrefix', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->cacheKeyPrefix('fluent')->get('https://api.test/a');

        expect(Cache::store('array')->has('fluent:'.sha1(json_encode([
            'method' => 'GET',
            'url' => 'https://api.test/a',
            'query' => null,
            'body' => null,
            'key' => 'k',
        ]))))->toBeTrue();
    });

    it('supports cacheWhen', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->cacheWhen(fn ($response) => false)->get('https://api.test/a');
        $second = Http::cache('k', 600)->cacheWhen(fn ($response) => false)->get('https://api.test/a');

        expect($second->fromCache())->toBeFalse();
        Http::assertSentCount(2);
    });

    it('supports cacheMethods', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->cacheMethods(['GET', 'POST'])->post('https://api.test/a');
        $second = Http::cache('k', 600)->cacheMethods(['GET', 'POST'])->post('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
    });

    it('supports cacheStore', function () {
        config()->set('cache.stores.secondary', ['driver' => 'array']);

        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->cacheStore('secondary')->get('https://api.test/a');
        $second = Http::cache('k', 600)->cacheStore('secondary')->get('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
        expect(Cache::store('array')->has('http-client-cache:'.sha1(json_encode([
            'method' => 'GET',
            'url' => 'https://api.test/a',
            'query' => null,
            'body' => null,
            'key' => 'k',
        ]))))->toBeFalse();
    });

    it('supports cacheTags without breaking on stores that do not support tags', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->cacheTags(['api', 'products'])->get('https://api.test/a');
        $second = Http::cache('k', 600)->cacheTags(['api', 'products'])->get('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('supports cacheStatuses', function () {
        Http::fake(['*' => Http::response('ok', 200)]);

        Http::cache('k', 600)->cacheStatuses([200])->get('https://api.test/a');
        $second = Http::cache('k', 600)->cacheStatuses([200])->get('https://api.test/a');

        expect($second->fromCache())->toBeTrue();
        Http::assertSentCount(1);
    });

    it('supports dontCache', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)->dontCache()->get('https://api.test/a');
        $second = Http::cache('k', 600)->dontCache()->get('https://api.test/a');

        expect($second->fromCache())->toBeFalse();
        Http::assertSentCount(2);
    });

    it('preserves headers and query params through the wrapper', function () {
        Http::fake(['*' => Http::response('x', 200)]);

        Http::cache('k', 600)
            ->withHeaders(['Authorization' => 'Bearer token'])
            ->get('https://api.test/a', ['page' => 2]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer token')
                && str_contains($request->url(), 'page=2');
        });
    });
});
