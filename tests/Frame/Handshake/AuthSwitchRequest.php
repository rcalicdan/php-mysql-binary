<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthSwitchRequest;

test('exposes all properties correctly', function () {
    $frame = new AuthSwitchRequest('caching_sha2_password', 'randomsalt', 3);

    expect($frame->pluginName)->toBe('caching_sha2_password')
        ->and($frame->authData)->toBe('randomsalt')
        ->and($frame->sequenceNumber)->toBe(3);
});