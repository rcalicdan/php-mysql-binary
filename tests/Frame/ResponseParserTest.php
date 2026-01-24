<?php

use Rcalicdan\MySQLBinaryProtocol\Frame\Error\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Error\ErrPacketParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacketParser;

test('parses OK packet correctly', function () {
    $payloadData = "\x00\x01\x05\x02\x00\x00\x00Success";
    $reader = createReader($payloadData);

    $parser = new OkPacketParser();
    /** @var OkPacket $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(OkPacket::class)
        ->and($packet->affectedRows)->toBe(1)
        ->and($packet->lastInsertId)->toBe(5)
        ->and($packet->statusFlags)->toBe(2)
        ->and($packet->warnings)->toBe(0)
        ->and($packet->info)->toBe('Success');
});

test('parses OK packet with large LENENC integers', function () {
    $payloadData = "\x00\xFC\xFB\x00\x00\x02\x00\x00\x00";
    $reader = createReader($payloadData);

    /** @var OkPacket */
    $packet = (new OkPacketParser())->parse($reader, strlen($payloadData), 1);

    expect($packet->affectedRows)->toBe(251);
});

test('parses ERR packet correctly', function () {
    $errorMsg = "Unknown database 'test'";
    $payloadData = "\xFF\x19\x04#42000" . $errorMsg;
    $reader = createReader($payloadData);

    $parser = new ErrPacketParser();
    /** @var ErrPacket $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(ErrPacket::class)
        ->and($packet->errorCode)->toBe(1049)
        ->and($packet->sqlStateMarker)->toBe('#')
        ->and($packet->sqlState)->toBe('42000')
        ->and($packet->errorMessage)->toBe($errorMsg);
});