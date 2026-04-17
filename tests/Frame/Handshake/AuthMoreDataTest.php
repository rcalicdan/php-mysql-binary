<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\AuthPacketType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthMoreData;

test('isFullAuthRequired returns true when data is FULL_AUTH_REQUIRED byte', function () {
    $frame = new AuthMoreData(chr(AuthPacketType::FULL_AUTH_REQUIRED), 1);

    expect($frame->isFullAuthRequired())->toBeTrue()
        ->and($frame->isFastAuthSuccess())->toBeFalse()
    ;
});

test('isFastAuthSuccess returns true when data is FAST_AUTH_SUCCESS byte', function () {
    $frame = new AuthMoreData(chr(AuthPacketType::FAST_AUTH_SUCCESS), 2);

    expect($frame->isFastAuthSuccess())->toBeTrue()
        ->and($frame->isFullAuthRequired())->toBeFalse()
    ;
});

test('both methods return false when data is multi-byte (e.g. RSA key payload)', function () {
    $frame = new AuthMoreData('-----BEGIN PUBLIC KEY-----', 3);

    expect($frame->isFullAuthRequired())->toBeFalse()
        ->and($frame->isFastAuthSuccess())->toBeFalse()
    ;
});

test('exposes data and sequenceNumber as public properties', function () {
    $frame = new AuthMoreData('somedata', 5);

    expect($frame->data)->toBe('somedata')
        ->and($frame->sequenceNumber)->toBe(5);
});
