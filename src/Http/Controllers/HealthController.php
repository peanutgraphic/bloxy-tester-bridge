<?php

namespace Peanutgraphic\BloxyTesterBridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        // Pull discovered scenarios via the artisan command. We invoke it
        // with --json and capture the output. The command itself caches
        // for 60s, so frequent /health hits don't re-parse files every time.
        Artisan::call('tester:scenarios', ['--json' => true]);
        $rawJson = trim(Artisan::output());
        $scenarios = $rawJson === '' ? [] : (json_decode($rawJson, true) ?: []);

        return response()->json([
            'app_slug' => config('app.slug', config('app.name')),
            'tester_bridge_version' => '0.3.0',
            'tester_mode' => (bool) config('tester-bridge.mode'),
            'scenarios' => $scenarios,
            'capabilities' => [
                'clock_advance' => true,
                'coupon_seed' => false,
                'seed_pro_membership' => false,
                'replay_defense' => true,                      // Phase 2 ✓
                'scenario_discovery' => $rawJson !== '',      // depends on TESTER_SCENARIOS_PATH being configured
            ],
            'now' => now()->toIso8601String(),
        ]);
    }
}
