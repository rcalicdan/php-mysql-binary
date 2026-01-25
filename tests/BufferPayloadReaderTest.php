<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

beforeEach(function () {
    $this->payloadReaderFactory = new BufferPayloadReaderFactory();
    $this->buffer = new ReadBuffer();
});

function createPayloadReader(string $payload, int ...$packetLength): PayloadReader
{
    $buffer = new ReadBuffer();
    $buffer->append($payload);
    $packetLength = $packetLength ?: [strlen($payload)];

    return (new BufferPayloadReaderFactory())->createFromBuffer($buffer, $packetLength);
}

test('reads one byte fixed integer', function () {
    $payloadReader = createPayloadReader("\x01\x02\x03");

    $results = [
        $payloadReader->readFixedInteger(1),
        $payloadReader->readFixedInteger(1),
        $payloadReader->readFixedInteger(1),
    ];

    expect($results)->toEqual([1, 2, 3]);
});

test('reads multiple bytes of fixed integer', function () {
    $payloadReader = createPayloadReader(
        "\x00\x02\x02\x00\x00\x00\x00\x00\x00\x00\x00\xF0\x00"
    );

    $results = [
        $payloadReader->readFixedInteger(2),
        $payloadReader->readFixedInteger(3),
        $payloadReader->readFixedInteger(8),
    ];

    expect($results)->toEqual([512, 2, 67553994410557440]);
});

test('reads one byte length encoded integer', function () {
    $payloadReader = createPayloadReader("\x00\xfa\xf9\xa0");

    $results = [
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
    ];

    expect($results)->toEqual([0, 250, 249, 160]);
});

test('reads two byte length encoded integer', function () {
    $payloadReader = createPayloadReader("\xfc\xfb\x00\xfc\xfc\x00\xfc\xff\xf0");

    $results = [
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
    ];

    expect($results)->toEqual([251, 252, 61695]);
});

test('reads three byte length encoded integer', function () {
    $payloadReader = createPayloadReader("\xfd\xff\xf0\x00\xfd\xa9\xff\xf0");

    $results = [
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
    ];

    expect($results)->toEqual([61695, 15794089]);
});

test('reads eight byte length encoded integer', function () {
    $payloadReader = createPayloadReader(
        "\xfe\xa9\xff\xf0\x00\x00\x00\x00\x00\xfe\x09\xea\xca\xff\x0a\xff\xff\x0a"
    );

    $results = [
        $payloadReader->readLengthEncodedIntegerOrNull(),
        $payloadReader->readLengthEncodedIntegerOrNull(),
    ];

    expect($results)->toEqual([15794089, 792632482146740745]);
});

test('reads null value from length encoded integer spec', function () {
    $payloadReader = createPayloadReader("\xfb");

    $result = $payloadReader->readLengthEncodedIntegerOrNull();

    expect($result)->toBeNull();
});

test('reports incorrect length encoded integer given first byte is out of bounds', function () {
    $payloadReader = createPayloadReader("\xff");

    expect(fn () => $payloadReader->readLengthEncodedIntegerOrNull())
        ->toThrow(InvalidBinaryDataException::class)
    ;
});

test('reads fixed length string', function () {
    $payloadReader = createPayloadReader('onetwothree');

    $results = [
        $payloadReader->readFixedString(3),
        $payloadReader->readFixedString(3),
        $payloadReader->readFixedString(5),
    ];

    expect($results)->toEqual(['one', 'two', 'three']);
});

test('reads length encoded string', function () {
    $payloadReader = createPayloadReader("\x01a\x03one\x05three");

    $results = [
        $payloadReader->readLengthEncodedStringOrNull(),
        $payloadReader->readLengthEncodedStringOrNull(),
        $payloadReader->readLengthEncodedStringOrNull(),
    ];

    expect($results)->toEqual(['a', 'one', 'three']);
});

test('reads long length encoded string', function () {
    $payloadReader = createPayloadReader("\xfc\xe8\x03" . str_repeat('a', 1000));

    $result = $payloadReader->readLengthEncodedStringOrNull();

    expect($result)->toBe(str_repeat('a', 1000));
});

test('reads null string value', function () {
    $payloadReader = createPayloadReader("\xfb");

    $result = $payloadReader->readLengthEncodedStringOrNull();

    expect($result)->toBeNull();
});

test('reads null terminated string', function () {
    $payloadReader = createPayloadReader("null_terminated_string\x00other_data");

    $result = $payloadReader->readNullTerminatedString();

    expect($result)->toBe('null_terminated_string');
});

test('reads multiple null terminated strings', function () {
    $payloadReader = createPayloadReader("null_terminated_string\x00other_null_terminated_string\x00");

    $results = [
        $payloadReader->readNullTerminatedString(),
        $payloadReader->readNullTerminatedString(),
    ];

    expect($results)->toEqual(['null_terminated_string', 'other_null_terminated_string']);
});

test('throws incomplete buffer exception when null character is not present', function () {
    $payloadReader = createPayloadReader('some string without null character');

    expect(fn () => $payloadReader->readNullTerminatedString())
        ->toThrow(IncompleteBufferException::class)
    ;
});

test('reads string till end of the buffer', function () {
    $payloadReader = createPayloadReader('some string till end of buffer');

    $result = $payloadReader->readRestOfPacketString();

    expect($result)->toBe('some string till end of buffer');
});

test('reads multiple strings that represent complete packet', function () {
    $payloadReader = createPayloadReader(
        'packet1packet2packet3last packet',
        7,
        7,
        7,
        11
    );

    $results = [
        $payloadReader->readRestOfPacketString(),
        $payloadReader->readRestOfPacketString(),
        $payloadReader->readRestOfPacketString(),
        $payloadReader->readRestOfPacketString(),
    ];

    expect($results)->toEqual(['packet1', 'packet2', 'packet3', 'last packet']);
});

test('reads rest of packet string starting from current buffer position', function () {
    $payloadReader = createPayloadReader(
        'onerest of packetstring that should not be read',
        17,
        30
    );

    $payloadReader->readFixedString(3);
    $result = $payloadReader->readRestOfPacketString();

    expect($result)->toBe('rest of packet');
});
