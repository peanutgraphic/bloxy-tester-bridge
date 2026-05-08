<?php

use Peanutgraphic\BloxyTesterBridge\Auth\CanonicalRequest;

it('matches the shared HUB fixture', function () {
    $fixture = json_decode(file_get_contents(__DIR__ . '/../fixtures/canonical-request-1.json'), true);
    $i = $fixture['input'];

    $actual = CanonicalRequest::build(
        method: $i['method'],
        path: $i['path'],
        body: $i['body'],
        timestamp: $i['timestamp'],
        nonce: $i['nonce'],
        tokenB64: $i['token_b64'],
    );

    expect($actual)->toBe($fixture['expected_canonical']);
});
