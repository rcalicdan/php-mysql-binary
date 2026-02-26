<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\StmtPrepareOkPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\StmtPrepareResponseParser;

test('StmtPrepareResponseParser parses OK packet with all fields', function () {
    $payload =
        "\x00"
        . pack('V', 7)
        . pack('v', 3)
        . pack('v', 2)
        . "\x00"
        . pack('v', 1);

    $reader = createReader($payload);

    /** @var StmtPrepareOkPacket $packet */
    $packet = (new StmtPrepareResponseParser())->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(StmtPrepareOkPacket::class)
        ->and($packet->statementId)->toBe(7)
        ->and($packet->numColumns)->toBe(3)
        ->and($packet->numParams)->toBe(2)
        ->and($packet->warningCount)->toBe(1)
        ->and($packet->sequenceNumber)->toBe(1)
    ;
});

test('StmtPrepareResponseParser parses OK packet with zero columns and params', function () {
    $payload =
        "\x00"
        . pack('V', 42)
        . pack('v', 0)
        . pack('v', 0)
        . "\x00"
        . pack('v', 0);

    $reader = createReader($payload);

    /** @var StmtPrepareOkPacket $packet */
    $packet = (new StmtPrepareResponseParser())->parse($reader, strlen($payload), 2);

    expect($packet)->toBeInstanceOf(StmtPrepareOkPacket::class)
        ->and($packet->statementId)->toBe(42)
        ->and($packet->numColumns)->toBe(0)
        ->and($packet->numParams)->toBe(0)
        ->and($packet->warningCount)->toBe(0)
    ;
});

test('StmtPrepareResponseParser parses ERR packet', function () {
    $errorMsg = 'You have an error in your SQL syntax';
    $payload = "\xFF" . pack('v', 1064) . '#42000' . $errorMsg;

    $reader = createReader($payload);

    /** @var ErrPacket $packet */
    $packet = (new StmtPrepareResponseParser())->parse($reader, strlen($payload), 3);

    expect($packet)->toBeInstanceOf(ErrPacket::class)
        ->and($packet->errorCode)->toBe(1064)
        ->and($packet->sqlStateMarker)->toBe('#')
        ->and($packet->sqlState)->toBe('42000')
        ->and($packet->errorMessage)->toBe($errorMsg)
        ->and($packet->sequenceNumber)->toBe(3)
    ;
});

test('StmtPrepareResponseParser throws on unexpected packet type', function () {
    $payload = "\x01";

    $reader = createReader($payload);

    expect(fn () => (new StmtPrepareResponseParser())->parse($reader, strlen($payload), 1))
        ->toThrow(RuntimeException::class)
    ;
});

test('StmtPrepareResponseParser parses OK packet with large statement ID', function () {
    $payload =
        "\x00"
        . pack('V', 0xFFFFFFFF)
        . pack('v', 10)
        . pack('v', 5)
        . "\x00"
        . pack('v', 3);

    $reader = createReader($payload);

    /** @var StmtPrepareOkPacket $packet */
    $packet = (new StmtPrepareResponseParser())->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(StmtPrepareOkPacket::class)
        ->and($packet->statementId)->toBe(0xFFFFFFFF)
        ->and($packet->numColumns)->toBe(10)
        ->and($packet->numParams)->toBe(5);
});
