<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ResponseParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ResultSetHeader;

test('ResponseParser routes to OK packet when first byte is 0x00', function () {
    $payloadData = "\x00\x01\x05\x02\x00\x00\x00Success";
    $reader = createReader($payloadData);

    $parser = new ResponseParser();

    /** @var OkPacket $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(OkPacket::class)
        ->and($packet->affectedRows)->toBe(1)
        ->and($packet->lastInsertId)->toBe(5)
        ->and($packet->statusFlags)->toBe(2)
        ->and($packet->warnings)->toBe(0)
        ->and($packet->info)->toBe('Success')
        ->and($packet->sequenceNumber)->toBe(1);
});

test('ResponseParser routes to ERR packet when first byte is 0xFF', function () {
    $errorMsg = "Unknown database 'test'";
    $payloadData = "\xFF\x19\x04#42000" . $errorMsg;
    $reader = createReader($payloadData);

    $parser = new ResponseParser();

    /** @var ErrPacket $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 2);

    expect($packet)->toBeInstanceOf(ErrPacket::class)
        ->and($packet->errorCode)->toBe(1049)
        ->and($packet->sqlStateMarker)->toBe('#')
        ->and($packet->sqlState)->toBe('42000')
        ->and($packet->errorMessage)->toBe($errorMsg)
        ->and($packet->sequenceNumber)->toBe(2);
});

test('ResponseParser routes to ResultSetHeader for column count < 251', function () {
    $payloadData = "\x03"; 
    $reader = createReader($payloadData);

    $parser = new ResponseParser();
    
    /** @var ResultSetHeader $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(ResultSetHeader::class)
        ->and($packet->columnCount)->toBe(3)
        ->and($packet->sequenceNumber)->toBe(1);
});

test('ResponseParser parses ResultSetHeader with single column', function () {
    $payloadData = "\x01";
    $reader = createReader($payloadData);

    $parser = new ResponseParser();
    $packet = $parser->parseResponse($reader, strlen($payloadData), 3);

    /** @var ResultSetHeader $packet */
    expect($packet)->toBeInstanceOf(ResultSetHeader::class)
        ->and($packet->columnCount)->toBe(1)
        ->and($packet->sequenceNumber)->toBe(3);
});

test('ResponseParser parses ResultSetHeader with 2-byte length encoding (0xFC)', function () {
    $payloadData = "\xFC\xFB\x00";
    $reader = createReader($payloadData);

    $parser = new ResponseParser();

    /** @var ResultSetHeader $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(ResultSetHeader::class)
        ->and($packet->columnCount)->toBe(251);
});

test('ResponseParser parses ResultSetHeader with 3-byte length encoding (0xFD)', function () {
    $payloadData = "\xFD\x00\x00\x01"; 
    $reader = createReader($payloadData);

    $parser = new ResponseParser();

    /** @var ResultSetHeader $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(ResultSetHeader::class)
        ->and($packet->columnCount)->toBe(65536);
});

test('ResponseParser parses ResultSetHeader with 8-byte length encoding (0xFE)', function () {
    $payloadData = "\xFE\x00\x00\x00\x01\x00\x00\x00\x00"; 
    $reader = createReader($payloadData);

    $parser = new ResponseParser();
    /** @var ResultSetHeader $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(ResultSetHeader::class)
        ->and($packet->columnCount)->toBe(16777216);
});

test('ResponseParser throws exception for NULL marker (0xFB) in result set header', function () {
    $payloadData = "\xFB";
    $reader = createReader($payloadData);

    $parser = new ResponseParser();
    
    expect(fn() => $parser->parseResponse($reader, strlen($payloadData), 1))
        ->toThrow(\RuntimeException::class, 'Unexpected NULL (0xFB) in result set header');
});

test('ResponseParser throws exception for EOF packet (0xFE) with length < 9', function () {
    $payloadData = "\xFE\x00\x00\x00\x00"; // 0xFE with only 5 bytes total
    $reader = createReader($payloadData);

    $parser = new ResponseParser();
    
    expect(fn() => $parser->parseResponse($reader, strlen($payloadData), 1))
        ->toThrow(\RuntimeException::class, 'Unexpected EOF packet when expecting result set header');
});

test('ResponseParser parses OK packet with empty info string', function () {
    $payloadData = "\x00\x00\x00\x00\x00\x00\x00";
    $reader = createReader($payloadData);

    $parser = new ResponseParser();
    /** @var OkPacket $packet */
    $packet = $parser->parseResponse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(OkPacket::class)
        ->and($packet->affectedRows)->toBe(0)
        ->and($packet->lastInsertId)->toBe(0)
        ->and($packet->info)->toBe('');
});