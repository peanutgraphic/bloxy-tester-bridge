<?php

namespace Peanutgraphic\BloxyTesterBridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Peanutgraphic\BloxyTesterBridge\Auth\ReplayCache;
use Peanutgraphic\BloxyTesterBridge\Auth\RequestVerifier;
use Symfony\Component\HttpFoundation\Response;

class VerifyTesterRequest
{
    public function __construct(
        private readonly RequestVerifier $verifier,
        private readonly ReplayCache $replayCache,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // S-27 (Pass 2 M4): return a generic "unauthorized" body to the
        // client and log the specific reason server-side. The pre-fix
        // behavior returned the reason code (mode_off / ip /
        // malformed_token / expired / signature_mismatch / replay) as
        // the response body, which gave an attacker a staged-attack
        // oracle: each error progressed them through the auth pipeline.
        if (! config('tester-bridge.mode')) {
            return $this->reject($request, 'mode_off');
        }

        $allowed = config('tester-bridge.allowed_ips', []);
        if (! empty($allowed) && ! in_array($request->ip(), $allowed, true)) {
            return $this->reject($request, 'ip');
        }

        $r = $this->verifier->verify(
            method: $request->getMethod(),
            path: $request->getPathInfo(),
            body: $request->getContent(),
            headers: [
                'X-Tester-Token' => $request->header('X-Tester-Token'),
                'X-Tester-Signature' => $request->header('X-Tester-Signature'),
                'X-Tester-Timestamp' => $request->header('X-Tester-Timestamp'),
                'X-Tester-Nonce' => $request->header('X-Tester-Nonce'),
            ],
        );

        if (! $r->ok) {
            return $this->reject($request, $r->reason ?? 'unknown');
        }

        // Replay defense: nonce must be unseen within the token's lifetime.
        // S-28 (Pass 2 M4): key the replay cache by app slug + nonce so
        // multi-tenant Redis deployments (HUB + BENCH + Coffee Club +
        // SPCTRM sharing one cluster) don't cross-burn each other's
        // nonces. The ReplayCache contract owns this prefixing.
        $nonce = (string) $request->header('X-Tester-Nonce');
        $exp = (int) ($r->claims['exp'] ?? (time() + 60));
        $ttl = max(1, $exp - time());
        if ($this->replayCache->seenOrAdd($nonce, $ttl)) {
            return $this->reject($request, 'replay');
        }

        $request->attributes->set('tester', $r->claims);

        return $next($request);
    }

    private function reject(Request $request, string $reason): Response
    {
        Log::warning('bloxy-tester-bridge: rejected request', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
        ]);

        return response('unauthorized', 401);
    }
}
