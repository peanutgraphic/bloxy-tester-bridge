<?php

use Peanutgraphic\BloxyTesterBridge\BloxyTesterBridgeServiceProvider;
use Illuminate\Support\Facades\Redis;

it('first signed health request 200, second with same nonce 401 replay', function () {
    config(['tester-bridge.mode' => true]);
    config(['tester-bridge.shared_secret' => 'sek']);

    // Mock Redis: first call returns true (success), second returns false (already exists)
    Redis::shouldReceive('connection->set')->andReturn(true, false);

    $secret = 'sek';
    $now = time();
    $tokenJson = json_encode(['iss' => 'tester', 'iat' => $now, 'exp' => $now + 60]);
    $tokenB64 = rtrim(strtr(base64_encode($tokenJson), '+/', '-_'), '=');
    $ts = gmdate('Y-m-d\TH:i:s\Z', $now);
    $nonce = '01923456-7890-7abc-def0-123456789abc';
    $canonical = "GET\n/tester/health\n" . hash('sha256', '') . "\n{$ts}\n{$nonce}\n{$tokenB64}";
    $sig = hash_hmac('sha256', $canonical, $secret);

    $headers = [
        'X-Tester-Token' => $tokenB64,
        'X-Tester-Signature' => $sig,
        'X-Tester-Timestamp' => $ts,
        'X-Tester-Nonce' => $nonce,
    ];

    $first = $this->withHeaders($headers)->get('/tester/health');
    $first->assertOk();

    $second = $this->withHeaders($headers)->get('/tester/health');
    $second->assertStatus(401);
    expect($second->getContent())->toBe('replay');
});
