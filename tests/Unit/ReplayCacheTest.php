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
            return str_starts_with($key, 'tester:nonce:') && $nx === 'NX';
        })
        ->andReturn(true);

    $cache = new ReplayCache();
    expect($cache->seenOrAdd('abc-123', 60))->toBeFalse();
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
