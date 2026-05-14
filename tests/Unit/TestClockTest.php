<?php

use Carbon\Carbon;
use Peanutgraphic\BloxyTesterBridge\Clock\TestClock;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('advances Carbon::now() by the given seconds', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01T00:00:00Z'));
    (new TestClock())->advance(120);
    expect(Carbon::now()->toIso8601String())->toBe('2026-01-01T00:02:00+00:00');
});

it('accumulates across calls (later advances stack on top of earlier ones)', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01T00:00:00Z'));
    $clock = new TestClock();
    $clock->advance(60);
    $clock->advance(60);
    $clock->advance(60);
    expect(Carbon::now()->toIso8601String())->toBe('2026-01-01T00:03:00+00:00');
});

it('reset() restores wall-clock time', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01T00:00:00Z'));
    $clock = new TestClock();
    $clock->advance(3600);

    $clock->reset();
    expect(Carbon::hasTestNow())->toBeFalse();
});
