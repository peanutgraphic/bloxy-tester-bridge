<?php

declare(strict_types=1);

namespace Peanutgraphic\BloxyTesterBridge\Clock;

use Carbon\Carbon;

/**
 * Cooperative test-clock control for the target app. The Hub tester-worker
 * POSTs to /tester/clock/advance during a time-warped run; this service
 * frame-shifts Carbon::now() forward by N seconds so Carbon-aware code on
 * the target (billing, schedulers, streaks, daily-challenge resets) sees
 * compressed app time without the worker waiting on wall-clock seconds.
 *
 * Spec: docs/superpowers/specs/2026-05-11-tester-time-warp-slider.md.
 *
 * Carbon::setTestNow() freezes the clock at the given instant. We call
 * now()->addSeconds(N) so the first call relative-shifts from the current
 * test-now (or real now, if no prior freeze), and subsequent calls keep
 * accumulating. The clock does not auto-advance with wall time once
 * frozen — that's the entire point: the worker dictates rate.
 *
 * Available only when TESTER_MODE is on. The service provider does not
 * register the binding otherwise, so the route is also unreachable.
 */
class TestClock
{
    public function advance(int $seconds): void
    {
        Carbon::setTestNow(Carbon::now()->addSeconds($seconds));
    }

    /**
     * Reset to real wall-clock time. Mostly for end-of-run cleanup; the
     * route itself does not expose this — the bridge serves a single run
     * at a time and the worker restarts the target between runs in
     * production deployments.
     */
    public function reset(): void
    {
        Carbon::setTestNow();
    }

    public function now(): Carbon
    {
        return Carbon::now();
    }
}
