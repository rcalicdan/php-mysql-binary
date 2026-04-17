<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\AuthPacketType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthMoreData;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthResponseParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthSwitchRequest;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacket;

test('parses OK packet', function () {
    $payload = "\x00\x01\x00\x02\x00\x00\x00";
    $reader = createReader($payload);

    /** @var OkPacket $frame */
    $frame = (new AuthResponseParser())->parse($reader, strlen($payload), 2);

    expect($frame)->toBeInstanceOf(OkPacket::class)
        ->and($frame->sequenceNumber)->toBe(2)
    ;
});

test('parses ERR packet', function () {
    $errorMsg = "Access denied for user 'root'";
    $payload = "\xFF\x15\x04#28000" . $errorMsg;
    $reader = createReader($payload);

    /** @var ErrPacket $frame */
    $frame = (new AuthResponseParser())->parse($reader, strlen($payload), 2);

    expect($frame)->toBeInstanceOf(ErrPacket::class)
        ->and($frame->errorCode)->toBe(1045)
        ->and($frame->sqlState)->toBe('28000')
        ->and($frame->errorMessage)->toBe($errorMsg)
    ;
});

test('parses AuthSwitchRequest packet', function () {
    $pluginName = 'mysql_native_password';
    $authData = 'randomsalt12345678901';
    $payload = chr(AuthPacketType::AUTH_SWITCH_REQUEST) . $pluginName . "\x00" . $authData;
    $reader = createReader($payload);

    /** @var AuthSwitchRequest $frame */
    $frame = (new AuthResponseParser())->parse($reader, strlen($payload), 3);

    expect($frame)->toBeInstanceOf(AuthSwitchRequest::class)
        ->and($frame->pluginName)->toBe($pluginName)
        ->and($frame->authData)->toBe($authData)
        ->and($frame->sequenceNumber)->toBe(3)
    ;
});

test('parses AuthMoreData with fast auth success', function () {
    $payload = chr(AuthPacketType::AUTH_MORE_DATA) . chr(AuthPacketType::FAST_AUTH_SUCCESS);
    $reader = createReader($payload);

    /** @var AuthMoreData $frame */
    $frame = (new AuthResponseParser())->parse($reader, strlen($payload), 4);

    expect($frame)->toBeInstanceOf(AuthMoreData::class)
        ->and($frame->isFastAuthSuccess())->toBeTrue()
        ->and($frame->sequenceNumber)->toBe(4)
    ;
});

test('parses AuthMoreData with full auth required', function () {
    $payload = chr(AuthPacketType::AUTH_MORE_DATA) . chr(AuthPacketType::FULL_AUTH_REQUIRED);
    $reader = createReader($payload);

    /** @var AuthMoreData $frame */
    $frame = (new AuthResponseParser())->parse($reader, strlen($payload), 5);

    expect($frame)->toBeInstanceOf(AuthMoreData::class)
        ->and($frame->isFullAuthRequired())->toBeTrue()
    ;
});

test('parses AuthMoreData with RSA key payload', function () {
    $rsaKey = '-----BEGIN PUBLIC KEY-----';
    $payload = chr(AuthPacketType::AUTH_MORE_DATA) . $rsaKey;
    $reader = createReader($payload);

    /** @var AuthMoreData $frame */
    $frame = (new AuthResponseParser())->parse($reader, strlen($payload), 6);

    expect($frame)->toBeInstanceOf(AuthMoreData::class)
        ->and($frame->data)->toBe($rsaKey)
        ->and($frame->isFullAuthRequired())->toBeFalse()
        ->and($frame->isFastAuthSuccess())->toBeFalse()
    ;
});

test('throws RuntimeException on unexpected packet type', function () {
    $payload = "\x42somedata";
    $reader = createReader($payload);

    expect(fn () => (new AuthResponseParser())->parse($reader, strlen($payload), 1))
        ->toThrow(RuntimeException::class, 'Unexpected packet type during authentication phase: 0x42');
});
