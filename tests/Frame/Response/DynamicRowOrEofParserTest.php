<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\DynamicRowOrEofParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\EofPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRow;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow;

test('DynamicRowOrEofParser detects and parses a binary row', function () {
    $columns = [makeCol(MysqlType::LONG)];
    $payload = "\x00" . buildNullBitmap(1) . pack('V', 42);

    $row = (new DynamicRowOrEofParser($columns))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($row)->toBeInstanceOf(BinaryRow::class)
        ->and($row->values[0])->toBe(42);
});

test('DynamicRowOrEofParser detects binary format and reuses it on subsequent rows', function () {
    $columns = [makeCol(MysqlType::LONG)];
    $parser  = new DynamicRowOrEofParser($columns);
    $bitmap  = buildNullBitmap(1);

    $payload1 = "\x00" . $bitmap . pack('V', 1);
    $payload2 = "\x00" . $bitmap . pack('V', 2);

    $row1 = $parser->parse(createReader($payload1), strlen($payload1), 1);
    $row2 = $parser->parse(createReader($payload2), strlen($payload2), 2);

    expect($row1)->toBeInstanceOf(BinaryRow::class)
        ->and($row1->values[0])->toBe(1)
        ->and($row2)->toBeInstanceOf(BinaryRow::class)
        ->and($row2->values[0])->toBe(2);
});

// ─── Text Row Detection ──────────────────────────────────────────────────────

test('DynamicRowOrEofParser detects and parses a text row', function () {
    $columns = [makeCol(MysqlType::VAR_STRING)];
    $payload = buildTextRowPayload(['hello']);

    $row = (new DynamicRowOrEofParser($columns))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($row)->toBeInstanceOf(TextRow::class)
        ->and($row->values[0])->toBe('hello');
});

test('DynamicRowOrEofParser parses text row with multiple columns', function () {
    $columns = [makeCol(MysqlType::VAR_STRING), makeCol(MysqlType::VAR_STRING), makeCol(MysqlType::VAR_STRING)];
    $payload = buildTextRowPayload(['Alice', '30', 'admin']);

    $row = (new DynamicRowOrEofParser($columns))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($row)->toBeInstanceOf(TextRow::class)
        ->and($row->values[0])->toBe('Alice')
        ->and($row->values[1])->toBe('30')
        ->and($row->values[2])->toBe('admin');
});

test('DynamicRowOrEofParser parses text row with a null column', function () {
    $columns = [makeCol(MysqlType::VAR_STRING), makeCol(MysqlType::VAR_STRING)];
    $payload = buildTextRowPayload(['value', null]);

    $row = (new DynamicRowOrEofParser($columns))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($row)->toBeInstanceOf(TextRow::class)
        ->and($row->values[0])->toBe('value')
        ->and($row->values[1])->toBeNull();
});

test('DynamicRowOrEofParser detects text format and reuses it on subsequent rows', function () {
    $columns = [makeCol(MysqlType::VAR_STRING)];
    $parser  = new DynamicRowOrEofParser($columns);

    $payload1 = buildTextRowPayload(['foo']);
    $payload2 = buildTextRowPayload(['bar']);

    $row1 = $parser->parse(createReader($payload1), strlen($payload1), 1);
    $row2 = $parser->parse(createReader($payload2), strlen($payload2), 2);

    expect($row1)->toBeInstanceOf(TextRow::class)
        ->and($row1->values[0])->toBe('foo')
        ->and($row2)->toBeInstanceOf(TextRow::class)
        ->and($row2->values[0])->toBe('bar');
});


test('DynamicRowOrEofParser with forceTextFormat=true always parses text rows', function () {
    $columns = [makeCol(MysqlType::VAR_STRING)];
    $payload = buildTextRowPayload(['forced']);

    $row = (new DynamicRowOrEofParser($columns, forceTextFormat: true))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($row)->toBeInstanceOf(TextRow::class)
        ->and($row->values[0])->toBe('forced');
});

test('DynamicRowOrEofParser with forceTextFormat=false always parses binary rows', function () {
    $columns = [makeCol(MysqlType::LONG)];
    $payload = "\x00" . buildNullBitmap(1) . pack('V', 99);

    $row = (new DynamicRowOrEofParser($columns, forceTextFormat: false))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($row)->toBeInstanceOf(BinaryRow::class)
        ->and($row->values[0])->toBe(99);
});

test('DynamicRowOrEofParser parses EOF packet', function () {
    $payload = "\xFE" . pack('v', 3) . pack('v', 8);

    $result = (new DynamicRowOrEofParser([makeCol(MysqlType::LONG)]))
        ->parse(createReader($payload), strlen($payload), 5);

    expect($result)->toBeInstanceOf(EofPacket::class)
        ->and($result->warnings)->toBe(3)
        ->and($result->statusFlags)->toBe(8)
        ->and($result->sequenceNumber)->toBe(5);
});

test('DynamicRowOrEofParser parses EOF packet with zero warnings and flags', function () {
    $payload = "\xFE" . pack('v', 0) . pack('v', 0);

    $result = (new DynamicRowOrEofParser([makeCol(MysqlType::LONG)]))
        ->parse(createReader($payload), strlen($payload), 1);

    expect($result)->toBeInstanceOf(EofPacket::class)
        ->and($result->warnings)->toBe(0)
        ->and($result->statusFlags)->toBe(0);
});

test('DynamicRowOrEofParser parses ERR packet', function () {
    $errorMsg = "Table 'db.missing' doesn't exist";
    $payload  = "\xFF" . pack('v', 1146) . '#42S02' . $errorMsg;

    $result = (new DynamicRowOrEofParser([makeCol(MysqlType::LONG)]))
        ->parse(createReader($payload), strlen($payload), 2);

    expect($result)->toBeInstanceOf(ErrPacket::class)
        ->and($result->errorCode)->toBe(1146)
        ->and($result->sqlStateMarker)->toBe('#')
        ->and($result->sqlState)->toBe('42S02')
        ->and($result->errorMessage)->toBe($errorMsg);
});