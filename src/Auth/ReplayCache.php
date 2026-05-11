<?php

namespace Peanutgraphic\BloxyTesterBridge\Auth;

use Illuminate\Support\Facades\Redis;

class ReplayCache
{
    /**
     * Atomically check-and-set the nonce. Returns true if the nonce has been
     * seen before (replay attempt), false if it's the first sighting.
     *
     * Implemented as Redis SET with NX (only-if-not-exists) and EX (ttl seconds).
     * Returns true (replay detected) when SET returns nil/false because the key
     * already existed.
     *
     * S-28 (Pass 2 M4): keys are prefixed with the host app's slug so
     * multi-tenant Redis (HUB + BENCH + Coffee Club + SPCTRM sharing one
     * cluster, common in the Peanut ecosystem) doesn't cross-burn nonces
     * across apps. Slug source priority: app.slug, then app.name,
     * lowercased.
     */
    public function seenOrAdd(string $nonce, int $ttlSeconds): bool
    {
        $ttl = max(1, $ttlSeconds);
        $key = $this->key($nonce);

        $result = Redis::connection()->set($key, '1', 'EX', $ttl, 'NX');

        // phpredis returns true on set, false/null on NX-conflict.
        // predis returns 'OK' on set, null on NX-conflict.
        if ($result === true || $result === 'OK') {
            return false;   // first sighting; not a replay
        }
        return true;        // already existed → replay
    }

    private function key(string $nonce): string
    {
        $slug = (string) (config('app.slug') ?? config('app.name', 'app'));
        $slug = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $slug) ?? 'app');
        return "tester:{$slug}:nonce:{$nonce}";
    }
}
