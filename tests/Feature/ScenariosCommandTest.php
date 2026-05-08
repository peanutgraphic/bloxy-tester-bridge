<?php

use Peanutgraphic\BloxyTesterBridge\Console\ScenariosCommand;

it('returns empty array when no scenarios path is configured', function () {
    config(['tester-bridge.scenarios_path' => null]);
    $exit = $this->artisan('tester:scenarios --json')->run();
    expect($exit)->toBe(0);
});

it('exits 1 when configured path does not exist', function () {
    config(['tester-bridge.scenarios_path' => '/nonexistent/path']);
    $this->artisan('tester:scenarios --json')->assertExitCode(1);
});
