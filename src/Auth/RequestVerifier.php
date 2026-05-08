<?php

namespace Peanutgraphic\BloxyTesterBridge\Auth;

interface RequestVerifier
{
    public function verify(
        string $method,
        string $path,
        string $body,
        array $headers,
    ): VerifyResult;
}

class VerifyResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $reason = null,
        public readonly array $claims = [],
    ) {
    }
}
