<?php

namespace Peanutgraphic\BloxyTesterBridge\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Peanutgraphic\BloxyTesterBridge\BloxyTesterBridgeServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected bool $testerModeEnabled = true;

    protected function getPackageProviders($app): array
    {
        return [BloxyTesterBridgeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('tester-bridge.mode', $this->testerModeEnabled);
        $app['config']->set('tester-bridge.shared_secret', 'sek');
    }
}
