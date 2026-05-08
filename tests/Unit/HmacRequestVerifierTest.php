<?php

use Peanutgraphic\BloxyTesterBridge\Auth\HmacRequestVerifier;

function signHeaders(string $secret, string $method, string $path, string $body, array $claims, ?int $now = null): array
{
    $now ??= time();
    $tokenJson = json_encode($claims, JSON_UNESCAPED_SLASHES);
    $tokenB64 = rtrim(strtr(base64_encode($tokenJson), '+/', '-_'), '=');
    $ts = gmdate('Y-m-d\TH:i:s\Z', $now);
    $nonce = '01923456-7890-7abc-def0-123456789abc';
    $canonical = "{$method}\n{$path}\n" . hash('sha256', $body) . "\n{$ts}\n{$nonce}\n{$tokenB64}";

    return [
        'X-Tester-Token' => $tokenB64,
        'X-Tester-Signature' => hash_hmac('sha256', $canonical, $secret),
        'X-Tester-Timestamp' => $ts,
        'X-Tester-Nonce' => $nonce,
    ];
}

it('accepts a freshly signed request', function () {
    $secret = 'sek';
    $now = time();
    $h = signHeaders($secret, 'POST', '/x', '{"a":1}',
        ['iss' => 'tester', 'aud' => 'coffee-club', 'iat' => $now, 'exp' => $now + 60], $now);

    $v = new HmacRequestVerifier($secret);
    $r = $v->verify('POST', '/x', '{"a":1}', $h);
    expect($r->ok)->toBeTrue();
    expect($r->claims['aud'])->toBe('coffee-club');
});

it('rejects expired tokens', function () {
    $secret = 'sek';
    $past = time() - 600;
    $h = signHeaders($secret, 'GET', '/x', '', ['iss' => 'tester', 'iat' => $past, 'exp' => $past + 60], $past);
    $v = new HmacRequestVerifier($secret);
    expect($v->verify('GET', '/x', '', $h)->reason)->toBe('expired');
});

it('rejects signature mismatch', function () {
    $h = signHeaders('right', 'POST', '/x', '', ['iss' => 'tester', 'iat' => time(), 'exp' => time() + 60]);
    $v = new HmacRequestVerifier('wrong');
    expect($v->verify('POST', '/x', '', $h)->reason)->toBe('signature_mismatch');
});

it('rejects path tampering', function () {
    $h = signHeaders('s', 'POST', '/a', '', ['iss' => 'tester', 'iat' => time(), 'exp' => time() + 60]);
    $v = new HmacRequestVerifier('s');
    expect($v->verify('POST', '/b', '', $h)->reason)->toBe('signature_mismatch');
});

it('rejects missing headers', function () {
    $v = new HmacRequestVerifier('s');
    expect($v->verify('POST', '/x', '', [])->reason)->toBe('missing_headers');
});

it('rejects malformed token', function () {
    $v = new HmacRequestVerifier('s');
    $h = ['X-Tester-Token' => 'not-base64!!!', 'X-Tester-Signature' => 'x', 'X-Tester-Timestamp' => 'y', 'X-Tester-Nonce' => 'z'];
    expect($v->verify('POST', '/x', '', $h)->reason)->toBe('malformed_token');
});
