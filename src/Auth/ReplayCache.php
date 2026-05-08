<?php

namespace Peanutgraphic\BloxyTesterBridge\Auth;

use Illuminate\Support\Facades\Redis;

class ReplayCache
{
    private const KEY_PREFIX = 'tester:nonce:';

    /**
     * Atomically check-and-set the nonce. Returns true if the nonce has been
     * seen before (replay attempt), false if it's the first sighting.
     *
     * Implemented as Redis SET with NX (only-if-not-exists) and EX (ttl seconds).
     * Returns true (replay detected) when SET returns nil/false because the key
     * already existed.
     */
    public function seenOrAdd(string $nonce, int $ttlSeconds): bool
    {
        $ttl = max(1, $ttlSeconds);
        $key = self::KEY_PREFIX . $nonce;

        $result = Redis::connection()->set($key, '1', 'EX', $ttl, 'NX');

        // phpredis returns true on set, false/null on NX-conflict.
        // predis returns 'OK' on set, null on NX-conflict.
        if ($result === true || $result === 'OK') {
            return false;   // first sighting; not a replay
        }
        return true;        // already existed → replay
    }
}
