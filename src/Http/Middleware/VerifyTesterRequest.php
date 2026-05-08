<?php

namespace Peanutgraphic\BloxyTesterBridge\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        if (! config('tester-bridge.mode')) {
            return response('mode_off', 401);
        }

        $allowed = config('tester-bridge.allowed_ips', []);
        if (! empty($allowed) && ! in_array($request->ip(), $allowed, true)) {
            return response('ip', 401);
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
            return response($r->reason ?? 'unauthorized', 401);
        }

        // Replay defense: nonce must be unseen within the token's lifetime
        $nonce = (string) $request->header('X-Tester-Nonce');
        $exp = (int) ($r->claims['exp'] ?? (time() + 60));
        $ttl = max(1, $exp - time());
        if ($this->replayCache->seenOrAdd($nonce, $ttl)) {
            return response('replay', 401);
        }

        $request->attributes->set('tester', $r->claims);

        return $next($request);
    }
}
