<?php

namespace Peanutgraphic\BloxyTesterBridge\Auth;

class CanonicalRequest
{
    public static function build(
        string $method,
        string $path,
        string $body,
        string $timestamp,
        string $nonce,
        string $tokenB64,
    ): string {
        $bodyHash = hash('sha256', $body);

        return implode("\n", [
            strtoupper($method),
            $path,
            $bodyHash,
            $timestamp,
            $nonce,
            $tokenB64,
        ]);
    }
}
