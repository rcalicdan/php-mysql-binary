<?php

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriter;
use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRow;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRowParser;


test('parses row with a single string value', function () {
    $columns = [createColumnDef(MysqlType::VAR_STRING)];

    $payload = "\x00" . "\x05hello";

    $parser = new BinaryRowParser($columns);
    /** @var BinaryRow $row */
    $row = $parser->parse(createBinaryRowReader($payload), strlen($payload) + 1, 1);

    expect($row->values)->toBe(['hello']);
});

test('parses row with multiple values including NULL', function () {
    $columns = [
        createColumnDef(MysqlType::VAR_STRING),
        createColumnDef(MysqlType::LONGLONG),
        createColumnDef(MysqlType::TINY), 
    ];

    $nullBitmap = "\x10";

    $values = (new BufferPayloadWriter())
        ->writeLengthEncodedString('test')
        ->writeUInt64(12345)
        ->toString();

    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($nullBitmap . $values), 0, 1);

    expect($row->values)->toBe(['test', 12345, null]);
});

test('parses LONGLONG (int)', function () {
    $columns = [createColumnDef(MysqlType::LONGLONG)];
    $payload = "\x00" . pack('P', 999999999);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe(999999999);
});

test('parses LONG (int)', function () {
    $columns = [createColumnDef(MysqlType::LONG)];
    $payload = "\x00" . pack('V', 123456);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe(123456);
});

test('parses SHORT (int)', function () {
    $columns = [createColumnDef(MysqlType::SHORT)];
    $payload = "\x00" . pack('v', 123);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe(123);
});

test('parses TINY (int)', function () {
    $columns = [createColumnDef(MysqlType::TINY)];
    $payload = "\x00" . pack('c', 12);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe(12);
});

test('parses DOUBLE (float)', function () {
    $columns = [createColumnDef(MysqlType::DOUBLE)];
    $payload = "\x00" . pack('e', 123.456);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe(123.456);
});

test('parses FLOAT', function () {
    $columns = [createColumnDef(MysqlType::FLOAT)];
    $expected = 99.5;
    $payload = "\x00" . pack('f', $expected);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);

    $epsilon = 0.0001; 
    $actual = $row->values[0];

    expect(abs($actual - $expected) < $epsilon)->toBeTrue();
});

test('parses DATETIME', function () {
    $columns = [createColumnDef(MysqlType::DATETIME)];
    $datePayload = "\x0b" . pack('v', 2024) . "\x01\x19\x0a\x1e\x05" . pack('V', 123456);
    $payload = "\x00" . $datePayload;

    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe('2024-01-25 10:30:05.123456');
});

test('parses DATE', function () {
    $columns = [createColumnDef(MysqlType::DATE)];
    $datePayload = "\x04" . pack('v', 2023) . "\x0c\x1f";
    $payload = "\x00" . $datePayload;

    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe('2023-12-31');
});

test('parses TIME', function () {
    $columns = [createColumnDef(MysqlType::TIME)];
    $timePayload = "\x08\x00" . pack('V', 0) . "\x0e\x2d\x0a";
    $payload = "\x00" . $timePayload;

    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe('14:45:10');
});

test('parses YEAR', function () {
    $columns = [createColumnDef(MysqlType::YEAR)];
    $payload = "\x00" . pack('v', 1999);
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe(1999);
});

test('parses BLOB types as string', function () {
    $columns = [createColumnDef(MysqlType::BLOB)];
    $payload = "\x00\x0a" . "binarydata"; 
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe("binarydata");
});

test('parses JSON as string', function () {
    $columns = [createColumnDef(MysqlType::JSON)];
    $json = '{"key":"value"}';
    $payload = "\x00" . chr(strlen($json)) . $json; 
    $parser = new BinaryRowParser($columns);
    $row = $parser->parse(createBinaryRowReader($payload), 0, 1);
    expect($row->values[0])->toBe($json);
});