<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Frame\Response\RowOrEofParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\EofPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow;

test('RowOrEofParser parses EOF packet when first byte is 0xFE and length < 9', function () {
    $payloadData = "\xFE\x00\x00\x02\x00"; 
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(3);

    /** @var EofPacket $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 5);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(0)
        ->and($packet->statusFlags)->toBe(2)
        ->and($packet->sequenceNumber)->toBe(5);
});

test('RowOrEofParser parses EOF packet with warnings', function () {
    $payloadData = "\xFE\x03\x00\x00\x00"; 
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(2);

     /** @var EofPacket $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 10);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(3)
        ->and($packet->statusFlags)->toBe(0)
        ->and($packet->sequenceNumber)->toBe(10);
});

test('RowOrEofParser parses EOF packet at exact boundary (length = 8)', function () {
    $payloadData = "\xFE\xFF\x00\x40\x00"; 
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(1);

     /** @var EofPacket $packet */
    $packet = $parser->parse($reader, 5, 2);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(255)
        ->and($packet->statusFlags)->toBe(64);
});

test('RowOrEofParser parses text row when first byte is not 0xFE', function () {
    $payloadData = "\x05hello\x05world";
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(2);

     /** @var TextRow $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 3);

    expect($packet)->toBeInstanceOf(TextRow::class)
        ->and($packet->values)->toBe(['hello', 'world']);
});

test('RowOrEofParser parses text row when first byte is 0xFE but length >= 9', function () {
    // First column: 0xFE (8-byte length) = 3, value = "foo"
    // The 8-byte length should encode the number 3
    $payloadData = "\xFE\x03\x00\x00\x00\x00\x00\x00\x00" . "foo";
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(1);

     /** @var TextRow $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 4);

    expect($packet)->toBeInstanceOf(TextRow::class)
        ->and($packet->values)->toBe(['foo']);
});

test('RowOrEofParser parses row with NULL values', function () {
    $payloadData = "\x04test\xFB\x05value";
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(3);

     /** @var TextRow $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(TextRow::class)
        ->and($packet->values)->toBe(['test', null, 'value']);
});

test('RowOrEofParser parses row with single column', function () {
    $payloadData = "\x0csingle value";
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(1);

     /** @var TextRow $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 7);

    expect($packet)->toBeInstanceOf(TextRow::class)
        ->and($packet->values)->toBe(['single value']);
});

test('RowOrEofParser parses row with empty string values', function () {
    $payloadData = "\x00\x04data";
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(2);

     /** @var TextRow $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 2);

    expect($packet)->toBeInstanceOf(TextRow::class)
        ->and($packet->values)->toBe(['', 'data']);
});

test('RowOrEofParser parses row with multiple columns', function () {
    $payloadData = "\x01A\x01B\x01C\x01D";
    $reader = createReader($payloadData);

    $parser = new RowOrEofParser(4);

     /** @var TextRow $packet */
    $packet = $parser->parse($reader, strlen($payloadData), 1);

    expect($packet)->toBeInstanceOf(TextRow::class)
        ->and($packet->values)->toBe(['A', 'B', 'C', 'D']);
});