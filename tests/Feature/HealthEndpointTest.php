<?php

use Illuminate\Support\Facades\Redis;

it('returns 401 when unauthed', function () {
    $r = $this->get('/tester/health');
    $r->assertStatus(401);
});

it('returns 200 + capability JSON when properly signed', function () {
    Redis::shouldReceive('connection->set')->andReturn(true);

    $secret = 'sek';
    $now = time();
    $tokenJson = json_encode(['iss' => 'tester', 'iat' => $now, 'exp' => $now + 60]);
    $tokenB64 = rtrim(strtr(base64_encode($tokenJson), '+/', '-_'), '=');
    $ts = gmdate('Y-m-d\TH:i:s\Z', $now);
    $nonce = '01923456-7890-7abc-def0-123456789abc';
    $canonical = "GET\n/tester/health\n" . hash('sha256', '') . "\n{$ts}\n{$nonce}\n{$tokenB64}";
    $sig = hash_hmac('sha256', $canonical, $secret);

    $r = $this->withHeaders([
        'X-Tester-Token' => $tokenB64,
        'X-Tester-Signature' => $sig,
        'X-Tester-Timestamp' => $ts,
        'X-Tester-Nonce' => $nonce,
    ])->get('/tester/health');

    $r->assertOk();
    $r->assertJsonStructure([
        'app_slug',
        'tester_mode',
        'scenarios',
        'capabilities' => ['clock_advance', 'coupon_seed', 'replay_defense', 'scenario_discovery'],
        'now',
    ]);
    $r->assertJsonPath('capabilities.replay_defense', true);
});

it('registers no routes when mode is off', function () {
    $this->testerModeEnabled = false;
    $this->refreshApplication();

    $r = $this->get('/tester/health');
    $r->assertStatus(404);
});
