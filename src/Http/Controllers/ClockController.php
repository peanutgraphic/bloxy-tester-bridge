<?php

declare(strict_types=1);

namespace Peanutgraphic\BloxyTesterBridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Peanutgraphic\BloxyTesterBridge\Clock\TestClock;

class ClockController
{
    public function __construct(private readonly TestClock $clock) {}

    /**
     * Advance the target's app clock by the requested number of seconds.
     *
     * Body: { "seconds_to_advance": int (1..604800) }
     *
     * Upper bound 604800 = 1 week per single call. The worker's tick loop
     * stays well below that (at the 1y/10m max stop, one wall-tick =
     * 52,560s = 14.6 hours, also under the cap). Rejecting values above
     * the cap protects against a misconfigured client locking the target
     * into a far-future state in a single call.
     */
    public function advance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seconds_to_advance' => ['required', 'integer', 'min:1', 'max:604800'],
        ]);

        $this->clock->advance((int) $data['seconds_to_advance']);

        return response()->json([
            'now' => $this->clock->now()->toIso8601String(),
            'advanced_seconds' => (int) $data['seconds_to_advance'],
        ]);
    }
}
