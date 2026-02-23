<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\ColumnFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\BinaryRowOrEofParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\EofPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRow;

test('BinaryRowOrEofParser parses EOF packet', function () {
    $payload = "\xFE" . pack('v', 0) . pack('v', 2);

    $reader = createReader($payload);
    $parser = new BinaryRowOrEofParser([makeCol(MysqlType::LONG)]);

    /** @var EofPacket $packet */
    $packet = $parser->parse($reader, strlen($payload), 5);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(0)
        ->and($packet->statusFlags)->toBe(2)
        ->and($packet->sequenceNumber)->toBe(5);
});

test('BinaryRowOrEofParser parses EOF packet with warnings', function () {
    $payload = "\xFE" . pack('v', 3) . pack('v', 0);

    $reader = createReader($payload);
    $parser = new BinaryRowOrEofParser([makeCol(MysqlType::LONG)]);

    /** @var EofPacket $packet */
    $packet = $parser->parse($reader, strlen($payload), 10);

    expect($packet)->toBeInstanceOf(EofPacket::class)
        ->and($packet->warnings)->toBe(3);
});

test('BinaryRowOrEofParser parses ERR packet', function () {
    $errorMsg = 'Deadlock found';
    $payload = "\xFF" . pack('v', 1213) . '#40001' . $errorMsg;

    $reader = createReader($payload);
    $parser = new BinaryRowOrEofParser([makeCol(MysqlType::LONG)]);

    /** @var ErrPacket $packet */
    $packet = $parser->parse($reader, strlen($payload), 2);

    expect($packet)->toBeInstanceOf(ErrPacket::class)
        ->and($packet->errorCode)->toBe(1213)
        ->and($packet->sqlState)->toBe('40001')
        ->and($packet->errorMessage)->toBe($errorMsg);
});

test('BinaryRowOrEofParser throws on unexpected header byte', function () {
    // Any byte that is not 0x00, 0xFE, or 0xFF is invalid for a binary row
    $payload = "\x01";

    $reader = createReader($payload);
    $parser = new BinaryRowOrEofParser([makeCol(MysqlType::LONG)]);

    expect(fn () => $parser->parse($reader, strlen($payload), 1))
        ->toThrow(InvalidBinaryDataException::class);
});

test('BinaryRowOrEofParser parses TINY signed positive', function () {
    $columns = [makeCol(MysqlType::TINY)];
    $payload = "\x00" . buildNullBitmap(1) . chr(42);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet)->toBeInstanceOf(BinaryRow::class)
        ->and($packet->values[0])->toBe(42);
});

test('BinaryRowOrEofParser parses TINY signed negative', function () {
    $columns = [makeCol(MysqlType::TINY)];
    // 200 raw → 200 >= 128 → 200 - 256 = -56
    $payload = "\x00" . buildNullBitmap(1) . chr(200);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe(-56);
});

test('BinaryRowOrEofParser parses SHORT signed', function () {
    $columns = [makeCol(MysqlType::SHORT)];
    // -1 in little-endian 2 bytes = 0xFFFF → 65535 >= 32768 → 65535 - 65536 = -1
    $payload = "\x00" . buildNullBitmap(1) . pack('v', 65535);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe(-1);
});

test('BinaryRowOrEofParser parses LONG signed', function () {
    $columns = [makeCol(MysqlType::LONG)];
    $payload = "\x00" . buildNullBitmap(1) . pack('V', 123456789);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe(123456789);
});

test('BinaryRowOrEofParser parses LONGLONG signed', function () {
    $columns = [makeCol(MysqlType::LONGLONG)];
    $value = 9999999;
    $payload = "\x00" . buildNullBitmap(1) . pack('VV', $value & 0xFFFFFFFF, 0);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe($value);
});

test('BinaryRowOrEofParser parses LONGLONG unsigned', function () {
    $columns = [makeCol(MysqlType::LONGLONG, ColumnFlags::UNSIGNED_FLAG)];
    $value = 4294967295; // 0xFFFFFFFF
    $bytes = strrev(hex2bin(str_pad(dechex($value), 16, '0', STR_PAD_LEFT)));
    $payload = "\x00" . buildNullBitmap(1) . $bytes;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe($value);
});

test('LONGLONG unsigned zero', function () {
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('0000000000000000'));

    expect($result)->toBe(0);
});

test('LONGLONG unsigned value with only lower 32 bits set', function () {
    // 0x00000000_000000FF = 255
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('ff00000000000000'));

    expect($result)->toBe(255);
});

test('LONGLONG unsigned value spanning both 32-bit halves below PHP_INT_MAX', function () {
    // 0x00000001_00000000 = 4294967296
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('0000000001000000'));

    expect($result)->toBe(4294967296);
});

test('LONGLONG unsigned PHP_INT_MAX fits as native int', function () {
    // PHP_INT_MAX = 9223372036854775807 = 0x7FFFFFFFFFFFFFFF (LE: FF FF FF FF FF FF FF 7F)
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('ffffffffffffff7f'));

    expect($result)->toBe(PHP_INT_MAX)
        ->and($result)->toBeInt();
});


test('LONGLONG unsigned PHP_INT_MAX + 1 returns string', function () {
    // 9223372036854775808 = 0x8000000000000000 (LE: 00 00 00 00 00 00 00 80)
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('0000000000000080'));

    expect($result)->toBeString()
        ->and($result)->toBe('9223372036854775808');
})->skip(!extension_loaded('bcmath'), 'BCMath required for exact large unsigned values');

test('LONGLONG unsigned max value (2^64 - 1) returns string', function () {
    // 18446744073709551615 = 0xFFFFFFFFFFFFFFFF (LE: FF FF FF FF FF FF FF FF)
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('ffffffffffffffff'));

    expect($result)->toBeString()
        ->and($result)->toBe('18446744073709551615');
})->skip(!extension_loaded('bcmath'), 'BCMath required for exact large unsigned values');

test('LONGLONG unsigned value above PHP_INT_MAX returns numeric string without BCMath', function () {
    // Without BCMath we still get a string back, just potentially float-approximated
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('0000000000000080'));

    expect($result)->toBeString()
        ->and(is_numeric($result))->toBeTrue();
})->skip(extension_loaded('bcmath'), 'Tests the non-BCMath fallback path');


test('LONGLONG signed zero', function () {
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('0000000000000000'));

    expect($result)->toBe(0);
});

test('LONGLONG signed small positive value', function () {
    // 1 (LE: 01 00 00 00 00 00 00 00)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('0100000000000000'));

    expect($result)->toBe(1);
});

test('LONGLONG signed value spanning both 32-bit halves', function () {
    // 4294967296 = 0x00000001_00000000 (LE: 00 00 00 00 01 00 00 00)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('0000000001000000'));

    expect($result)->toBe(4294967296);
});

test('LONGLONG signed PHP_INT_MAX fits as native int', function () {
    // 9223372036854775807 = 0x7FFFFFFFFFFFFFFF (LE: FF FF FF FF FF FF FF 7F)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('ffffffffffffff7f'));

    expect($result)->toBe(PHP_INT_MAX)
        ->and($result)->toBeInt();
});

test('LONGLONG signed value of -1', function () {
    // -1 in two's complement = 0xFFFFFFFFFFFFFFFF (LE: FF FF FF FF FF FF FF FF)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('ffffffffffffffff'));

    expect($result)->toBe(-1);
});

test('LONGLONG signed value of -128', function () {
    // -128 = 0xFFFFFFFFFFFFFF80 (LE: 80 FF FF FF FF FF FF FF)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('80ffffffffffffff'));

    expect($result)->toBe(-128);
});

test('LONGLONG signed value of -4294967296', function () {
    // -4294967296 = 0xFFFFFFFF_00000000 (LE: 00 00 00 00 FF FF FF FF)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('00000000ffffffff'));

    expect($result)->toBe(-4294967296);
});

test('LONGLONG signed PHP_INT_MIN fits as native int', function () {
    // PHP_INT_MIN = -9223372036854775808 = 0x8000000000000000 (LE: 00 00 00 00 00 00 00 80)
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('0000000000000080'));

    expect($result)->toBe(PHP_INT_MIN)
        ->and($result)->toBeInt();
});


test('LONGLONG unsigned large value result is always numeric regardless of BCMath availability', function () {
    $result = parseLongLong(unsignedCol(), buildUnsignedLongLongPayload('0100000000000080'));

    expect(is_int($result) || (is_string($result) && is_numeric($result)))->toBeTrue();
});

test('LONGLONG signed large negative value result is always numeric regardless of BCMath availability', function () {
    $result = parseLongLong(signedCol(), buildSignedLongLongPayload('ffffffffffffffff'));

    expect(is_int($result) || (is_string($result) && is_numeric($result)))->toBeTrue();
});

test('BinaryRowOrEofParser parses FLOAT', function () {
    $columns = [makeCol(MysqlType::FLOAT)];
    $payload = "\x00" . buildNullBitmap(1) . pack('g', 3.14);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBeFloat()
        ->and(round((float) $packet->values[0], 2))->toBe(3.14);
});

test('BinaryRowOrEofParser parses DOUBLE', function () {
    $columns = [makeCol(MysqlType::DOUBLE)];
    $payload = "\x00" . buildNullBitmap(1) . pack('e', 2.718281828);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBeFloat()
        ->and(round((float) $packet->values[0], 9))->toBe(2.718281828);
});

test('BinaryRowOrEofParser parses DATE', function () {
    $columns = [makeCol(MysqlType::DATE)];
    $dateBytes = chr(4) . pack('v', 2024) . chr(6) . chr(15); // length=4, 2024-06-15
    $payload = "\x00" . buildNullBitmap(1) . $dateBytes;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('2024-06-15');
});

test('BinaryRowOrEofParser parses DATE with zero length as zero date', function () {
    $columns = [makeCol(MysqlType::DATE)];
    $payload = "\x00" . buildNullBitmap(1) . chr(0);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('0000-00-00');
});

test('BinaryRowOrEofParser parses DATETIME with full precision', function () {
    $columns = [makeCol(MysqlType::DATETIME)];
    // length=7: year, month, day, hour, minute, second
    $dateBytes = chr(7) . pack('v', 2024) . chr(3) . chr(25) . chr(14) . chr(30) . chr(59);
    $payload = "\x00" . buildNullBitmap(1) . $dateBytes;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('2024-03-25 14:30:59');
});

test('BinaryRowOrEofParser parses DATETIME with microseconds', function () {
    $columns = [makeCol(MysqlType::DATETIME)];
    // length=11: year, month, day, hour, minute, second, 4-byte microseconds
    $dateBytes = chr(11) . pack('v', 2024) . chr(1) . chr(1) . chr(0) . chr(0) . chr(0) . pack('V', 123456);
    $payload = "\x00" . buildNullBitmap(1) . $dateBytes;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('2024-01-01 00:00:00.123456');
});

test('BinaryRowOrEofParser parses DATETIME with zero length as zero datetime', function () {
    $columns = [makeCol(MysqlType::DATETIME)];
    $payload = "\x00" . buildNullBitmap(1) . chr(0);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('0000-00-00 00:00:00');
});

test('BinaryRowOrEofParser parses TIME', function () {
    $columns = [makeCol(MysqlType::TIME)];
    // length=8: is_negative(1), days(4), hours(1), minutes(1), seconds(1)
    $timeBytes = chr(8) . chr(0) . pack('V', 0) . chr(10) . chr(30) . chr(45);
    $payload = "\x00" . buildNullBitmap(1) . $timeBytes;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('10:30:45');
});

test('BinaryRowOrEofParser parses negative TIME spanning multiple days', function () {
    $columns = [makeCol(MysqlType::TIME)];
    // is_negative=1, days=1, hours=2 → totalHours = 26
    $timeBytes = chr(8) . chr(1) . pack('V', 1) . chr(2) . chr(0) . chr(0);
    $payload = "\x00" . buildNullBitmap(1) . $timeBytes;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('-26:00:00');
});

test('BinaryRowOrEofParser parses TIME with zero length as zero time', function () {
    $columns = [makeCol(MysqlType::TIME)];
    $payload = "\x00" . buildNullBitmap(1) . chr(0);

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe('00:00:00');
});

test('BinaryRowOrEofParser parses NEWDECIMAL as string', function () {
    $columns = [makeCol(MysqlType::NEWDECIMAL)];
    $decStr = '12345.6789';
    $payload = "\x00" . buildNullBitmap(1) . chr(strlen($decStr)) . $decStr;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe($decStr);
});

test('BinaryRowOrEofParser parses VAR_STRING via default branch', function () {
    $columns = [makeCol(MysqlType::VAR_STRING)];
    $str = 'hello world';
    $payload = "\x00" . buildNullBitmap(1) . chr(strlen($str)) . $str;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values[0])->toBe($str);
});

test('BinaryRowOrEofParser handles null column in the middle', function () {
    $columns = [
        makeCol(MysqlType::TINY),
        makeCol(MysqlType::TINY),
        makeCol(MysqlType::TINY),
    ];
    // Column index 1 is null
    $nullBitmap = buildNullBitmap(3, [1]);
    $payload = "\x00" . $nullBitmap . chr(10) . chr(20); // col 0 = 10, col 1 = null, col 2 = 20

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values)->toBe([10, null, 20]);
});

test('BinaryRowOrEofParser handles all columns null', function () {
    $columns = [makeCol(MysqlType::TINY), makeCol(MysqlType::TINY)];
    $nullBitmap = buildNullBitmap(2, [0, 1]);
    $payload = "\x00" . $nullBitmap;

    $reader = createReader($payload);

    /** @var BinaryRow $packet */
    $packet = (new BinaryRowOrEofParser($columns))->parse($reader, strlen($payload), 1);

    expect($packet->values)->toBe([null, null]);
});


test('parseRemainingRow parses a row without the 0x00 header byte', function () {
    $columns = [makeCol(MysqlType::TINY)];

    $payload = buildNullBitmap(1) . chr(99);

    $reader = createReader($payload);

    $row = (new BinaryRowOrEofParser($columns))->parseRemainingRow($reader);

    expect($row)->toBeInstanceOf(BinaryRow::class)
        ->and($row->values[0])->toBe(99);
});