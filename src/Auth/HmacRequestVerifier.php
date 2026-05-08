<?php

namespace Peanutgraphic\BloxyTesterBridge\Auth;

class HmacRequestVerifier implements RequestVerifier
{
    /** @var \Closure */
    private $clock;

    public function __construct(
        private readonly string $sharedSecret,
        ?\Closure $clock = null,
        private readonly int $clockSkewSeconds = 60,
    ) {
        $this->clock = $clock ?? fn () => time();
    }

    public function verify(string $method, string $path, string $body, array $headers): VerifyResult
    {
        $token = $headers['X-Tester-Token'] ?? null;
        $sig = $headers['X-Tester-Signature'] ?? null;
        $ts = $headers['X-Tester-Timestamp'] ?? null;
        $nonce = $headers['X-Tester-Nonce'] ?? null;

        if (! $token || ! $sig || ! $ts || ! $nonce) {
            return new VerifyResult(false, 'missing_headers');
        }

        $claims = $this->decodeToken($token);
        if ($claims === null || ! isset($claims['exp'], $claims['iat'])) {
            return new VerifyResult(false, 'malformed_token');
        }

        $now = ($this->clock)();
        if ($now > $claims['exp']) {
            return new VerifyResult(false, 'expired');
        }
        if ($now < ($claims['iat'] - $this->clockSkewSeconds)) {
            return new VerifyResult(false, 'expired');
        }

        $canonical = CanonicalRequest::build($method, $path, $body, $ts, $nonce, $token);
        $expected = hash_hmac('sha256', $canonical, $this->sharedSecret);

        if (! hash_equals($expected, $sig)) {
            return new VerifyResult(false, 'signature_mismatch');
        }

        return new VerifyResult(true, null, $claims);
    }

    private function decodeToken(string $b64): ?array
    {
        $padded = $b64 . str_repeat('=', (4 - strlen($b64) % 4) % 4);
        $json = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
