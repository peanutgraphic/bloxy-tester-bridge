<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

function signedHeadersForClockAdvance(string $secret, string $body): array
{
    $now = time();
    $tokenJson = json_encode(['iss' => 'tester', 'iat' => $now, 'exp' => $now + 60]);
    $tokenB64 = rtrim(strtr(base64_encode($tokenJson), '+/', '-_'), '=');
    $ts = gmdate('Y-m-d\TH:i:s\Z', $now);
    $nonce = '01923456-7890-7abc-def0-aaaaaaaaaaaa';
    $canonical = "POST\n/tester/clock/advance\n".hash('sha256', $body)."\n{$ts}\n{$nonce}\n{$tokenB64}";
    $sig = hash_hmac('sha256', $canonical, $secret);

    return [
        'X-Tester-Token' => $tokenB64,
        'X-Tester-Signature' => $sig,
        'X-Tester-Timestamp' => $ts,
        'X-Tester-Nonce' => $nonce,
    ];
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('rejects unauthenticated POST /tester/clock/advance', function () {
    $r = $this->postJson('/tester/clock/advance', ['seconds_to_advance' => 60]);
    $r->assertStatus(401);
});

it('advances Carbon::now() by the requested seconds on a valid signed POST', function () {
    Redis::shouldReceive('connection->set')->andReturn(true);

    Carbon::setTestNow(Carbon::parse('2026-01-01T00:00:00Z'));

    $payload = ['seconds_to_advance' => 3600];
    $body = json_encode($payload);
    $headers = signedHeadersForClockAdvance('sek', $body);

    $r = $this->withHeaders($headers)->postJson('/tester/clock/advance', $payload);

    $r->assertOk();
    $r->assertJsonPath('advanced_seconds', 3600);
    expect(Carbon::now()->toIso8601String())->toBe('2026-01-01T01:00:00+00:00');
});

it('accumulates advances across multiple calls', function () {
    Redis::shouldReceive('connection->set')->andReturn(true);
    Carbon::setTestNow(Carbon::parse('2026-01-01T00:00:00Z'));

    foreach ([60, 60, 60] as $i => $advance) {
        $payload = ['seconds_to_advance' => $advance];
        $body = json_encode($payload);
        $h = signedHeadersForClockAdvance('sek', $body);
        // Each call needs a distinct nonce to bypass the replay cache.
        $h['X-Tester-Nonce'] = '01923456-7890-7abc-def0-bbbbbbbbbbb'.$i;
        $canonical = "POST\n/tester/clock/advance\n".hash('sha256', $body)."\n{$h['X-Tester-Timestamp']}\n{$h['X-Tester-Nonce']}\n{$h['X-Tester-Token']}";
        $h['X-Tester-Signature'] = hash_hmac('sha256', $canonical, 'sek');

        $this->withHeaders($h)->postJson('/tester/clock/advance', $payload)->assertOk();
    }

    expect(Carbon::now()->toIso8601String())->toBe('2026-01-01T00:03:00+00:00');
});

it('rejects seconds_to_advance below 1 or above 604800', function () {
    Redis::shouldReceive('connection->set')->andReturn(true);

    foreach ([0, -5, 604801, 999999] as $i => $bad) {
        $payload = ['seconds_to_advance' => $bad];
        $body = json_encode($payload);
        $h = signedHeadersForClockAdvance('sek', $body);
        $h['X-Tester-Nonce'] = '01923456-7890-7abc-def0-cccccccccc0'.$i;
        $canonical = "POST\n/tester/clock/advance\n".hash('sha256', $body)."\n{$h['X-Tester-Timestamp']}\n{$h['X-Tester-Nonce']}\n{$h['X-Tester-Token']}";
        $h['X-Tester-Signature'] = hash_hmac('sha256', $canonical, 'sek');

        $r = $this->withHeaders($h)->postJson('/tester/clock/advance', $payload);
        $r->assertStatus(422);
    }
});

it('reports clock_advance: true on the health endpoint now that the route is wired', function () {
    Redis::shouldReceive('connection->set')->andReturn(true);

    $now = time();
    $tokenJson = json_encode(['iss' => 'tester', 'iat' => $now, 'exp' => $now + 60]);
    $tokenB64 = rtrim(strtr(base64_encode($tokenJson), '+/', '-_'), '=');
    $ts = gmdate('Y-m-d\TH:i:s\Z', $now);
    $nonce = '01923456-7890-7abc-def0-ddddddddddd1';
    $canonical = "GET\n/tester/health\n".hash('sha256', '')."\n{$ts}\n{$nonce}\n{$tokenB64}";
    $sig = hash_hmac('sha256', $canonical, 'sek');

    $r = $this->withHeaders([
        'X-Tester-Token' => $tokenB64,
        'X-Tester-Signature' => $sig,
        'X-Tester-Timestamp' => $ts,
        'X-Tester-Nonce' => $nonce,
    ])->get('/tester/health');

    $r->assertOk();
    $r->assertJsonPath('capabilities.clock_advance', true);
});
