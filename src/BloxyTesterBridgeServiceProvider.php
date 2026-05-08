<?php

namespace Peanutgraphic\BloxyTesterBridge;

use Illuminate\Support\ServiceProvider;
use Peanutgraphic\BloxyTesterBridge\Auth\HmacRequestVerifier;
use Peanutgraphic\BloxyTesterBridge\Auth\ReplayCache;
use Peanutgraphic\BloxyTesterBridge\Auth\RequestVerifier;

class BloxyTesterBridgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tester-bridge.php', 'tester-bridge');

        $this->app->bind(RequestVerifier::class, function ($app) {
            return new HmacRequestVerifier(
                sharedSecret: (string) config('tester-bridge.shared_secret'),
                clockSkewSeconds: (int) config('tester-bridge.clock_skew_seconds', 60),
            );
        });

        $this->app->bind(ReplayCache::class, fn () => new ReplayCache());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Peanutgraphic\BloxyTesterBridge\Console\ScenariosCommand::class,
            ]);
        }

        // Production-safe: register nothing (no routes) if mode is off.
        if (! config('tester-bridge.mode')) {
            $this->publishes([
                __DIR__ . '/../config/tester-bridge.php' => config_path('tester-bridge.php'),
            ], 'tester-bridge-config');

            return;
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->publishes([
            __DIR__ . '/../config/tester-bridge.php' => config_path('tester-bridge.php'),
        ], 'tester-bridge-config');
    }
}
