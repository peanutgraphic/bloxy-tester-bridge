<?php

use Peanutgraphic\BloxyTesterBridge\Auth\ReplayCache;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    config(['tester-bridge.replay_cache_redis_connection' => 'default']);
});

it('first nonce is accepted', function () {
    Redis::shouldReceive('connection->set')
        ->once()
        ->withArgs(function ($key, $val, $ex, $ttl, $nx) {
            // S-28: key is now `tester:<app-slug>:nonce:<nonce>` so
            // multi-tenant Redis doesn't cross-burn nonces between apps.
            return preg_match('/^tester:[^:]+:nonce:/', $key) === 1
                && $nx === 'NX';
        })
        ->andReturn(true);

    $cache = new ReplayCache();
    expect($cache->seenOrAdd('abc-123', 60))->toBeFalse();
});

it('keys are prefixed with the app slug for multi-tenant safety (S-28)', function () {
    config()->set('app.slug', 'bench');

    Redis::shouldReceive('connection->set')
        ->once()
        ->withArgs(function ($key) {
            return $key === 'tester:bench:nonce:nonce-x';
        })
        ->andReturn(true);

    $cache = new ReplayCache();
    $cache->seenOrAdd('nonce-x', 60);
});

it('replayed nonce is rejected', function () {
    Redis::shouldReceive('connection->set')
        ->once()
        ->andReturn(false);   // Redis returns null/false when NX fails

    $cache = new ReplayCache();
    expect($cache->seenOrAdd('abc-123', 60))->toBeTrue();
});

it('clamps ttl to a positive minimum', function () {
    Redis::shouldReceive('connection->set')
        ->withArgs(function ($key, $val, $ex, $ttl, $nx) {
            return $ttl >= 1;
        })
        ->andReturn(true);

    $cache = new ReplayCache();
    expect($cache->seenOrAdd('x', -100))->toBeFalse();   // negative TTL → clamped to 1
});
