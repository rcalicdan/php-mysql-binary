<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Factory\DefaultPacketReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

beforeEach(function () {
    $this->reader = (new DefaultPacketReaderFactory())->createWithDefaultSettings();
});

function readPayload(callable $fragmentCallable)
{
    $data = null;
    test()->reader->readPayload(function (...$args) use ($fragmentCallable, &$data) {
        $data = $fragmentCallable(...$args);
    });

    return $data;
}

test('allows to read single byte integer from payload', function () {
    $this->reader->append("\x01\x00\x00\x00\xF1");

    $data = [];
    $this->reader->readPayload(function (PayloadReader $fragment) use (&$data) {
        $data[] = $fragment->readFixedInteger(1);
    });

    expect($data)->toEqual([241]);
});

test('allows to read multiple packets of integers', function () {
    $this->reader->append("\x01\x00\x00\x00\xF1");
    $this->reader->append("\x01\x00\x00\x00\xF2");
    $this->reader->append("\x01\x00\x00\x00\xF3");
    $this->reader->append("\x01\x00\x00\x00\xF4");

    $data = [];
    $readOneByte = function (PayloadReader $fragment) use (&$data) {
        $data[] = $fragment->readFixedInteger(1);
    };

    $this->reader->readPayload($readOneByte);
    $this->reader->readPayload($readOneByte);
    $this->reader->readPayload($readOneByte);
    $this->reader->readPayload($readOneByte);

    expect($data)->toEqual([241, 242, 243, 244]);
});

test('reports fragment is read', function () {
    $this->reader->append("\x01\x00\x00\x00\x01");

    $result = $this->reader->readPayload(function (PayloadReader $reader) {
        $reader->readFixedInteger(1);
    });

    expect($result)->toBeTrue();
});

test('reports fragment is not read', function () {
    $this->reader->append("\x02\x00\x00\x00\x01");

    $result = $this->reader->readPayload(function (PayloadReader $reader) {
        $reader->readFixedInteger(1);
        $reader->readFixedInteger(1);
    });

    expect($result)->toBeFalse();
});

test('allows reading various fixed integers', function () {
    $this->reader->append("\x0D\x00\x00\x00\x00\x02\x02\x00\x00\x00\x00\x00\x00\x00\x00\xF0\x00");

    $data = readPayload(function (PayloadReader $fragment) {
        return [
            $fragment->readFixedInteger(2),
            $fragment->readFixedInteger(3),
            $fragment->readFixedInteger(8),
        ];
    });

    expect($data)->toEqual([512, 2, 67553994410557440]);
});

test('allows reading different length encoded integers', function () {
    $this->reader->append(
        "\x12\x00\x00\x00\xf9\xfa\xfc\xfb\00\xfd\xff\xff\xf0\xfe\xff\xff\xff\xff\xff\xff\xff\xf0"
    );

    $data = readPayload(function (PayloadReader $fragment) {
        return [
            $fragment->readLengthEncodedIntegerOrNull(),
            $fragment->readLengthEncodedIntegerOrNull(),
            $fragment->readLengthEncodedIntegerOrNull(),
            $fragment->readLengthEncodedIntegerOrNull(),
            $fragment->readLengthEncodedIntegerOrNull(),
        ];
    });

    expect($data)->toEqual([249, 250, 251, 15794175, 17365880163140632575]);
});

test('throws invalid binary data exception when length encoded integer does not match expected format', function () {
    $this->reader->append("\x09\x00\x00\x00\xff\xff\xff\xff\xff\xff\xff\xff\xf0");

    expect(function () {
        $this->reader->readPayload(function (PayloadReader $fragment) {
            $fragment->readLengthEncodedIntegerOrNull();
        });
    })->toThrow(InvalidBinaryDataException::class);
});

test('reads null for length encoded integer', function () {
    $this->reader->append("\x01\x00\x00\x00\xfb");

    $result = readPayload(function (PayloadReader $fragment) {
        return $fragment->readLengthEncodedIntegerOrNull();
    });

    expect($result)->toBeNull();
});

test('reads fixed length string', function () {
    $this->reader->append("\x18\x00\x00\x00helloworld!awesomestring");

    $data = readPayload(function (PayloadReader $fragment) {
        return [
            $fragment->readFixedString(5),
            $fragment->readFixedString(6),
            $fragment->readFixedString(13),
        ];
    });

    expect($data)->toEqual(['hello', 'world!', 'awesomestring']);
});

test('reads length encoded string', function () {
    $veryLongString = str_repeat('a', 0xff);
    $this->reader->append("\x0c\x01\x00\x00\xfc\xff\x00$veryLongString\x05hello\xfb\x0202");

    $data = readPayload(function (PayloadReader $fragment) {
        return [
            $fragment->readLengthEncodedStringOrNull(),
            $fragment->readLengthEncodedStringOrNull(),
            $fragment->readLengthEncodedStringOrNull(),
            $fragment->readLengthEncodedStringOrNull(),
        ];
    });

    expect($data)->toEqual([$veryLongString, 'hello', null, '02']);
});

test('reads multiple null terminated strings', function () {
    $this->reader->append("\x31\x00\x00\x00first_string\x00second_string\x00third_string\x00");

    $data = readPayload(function (PayloadReader $fragmentReader) {
        return [
            $fragmentReader->readNullTerminatedString(),
            $fragmentReader->readNullTerminatedString(),
            $fragmentReader->readNullTerminatedString(),
        ];
    });

    expect($data)->toEqual(['first_string', 'second_string', 'third_string']);
});

test('stops reading payload when null character is not found for a string', function () {
    $this->reader->append("\x31\x00\x00\x00first_string");

    $data = readPayload(function (PayloadReader $fragmentReader) {
        return $fragmentReader->readNullTerminatedString();
    });

    expect($data)->toBeNull();
});

test('reports incomplete payload read when null terminated string is not completely read', function () {
    $this->reader->append("\x31\x00\x00\x00first_string");

    $result = $this->reader->readPayload(function (PayloadReader $fragmentReader) {
        $fragmentReader->readNullTerminatedString();
    });

    expect($result)->toBeFalse();
});

test('reads string that represents complete packet', function () {
    $this->reader->append("\x0a\x00\x00\x00This is 10\x00\x00\x00\x00");

    $result = readPayload(function (PayloadReader $payloadReader) {
        return $payloadReader->readRestOfPacketString();
    });

    expect($result)->toBe('This is 10');
});

test('reads string that represents remainder of the packet', function () {
    $this->reader->append("\x0C\x00\x00\x00\x01\x02This is 10\x00\x00\x00\x00");

    $result = readPayload(function (PayloadReader $payloadReader) {
        $payloadReader->readFixedInteger(1);
        $payloadReader->readFixedInteger(1);

        return $payloadReader->readRestOfPacketString();
    });

    expect($result)->toBe('This is 10');
});

test('reads multiple packets packet strings added as single network packet in single payload', function () {
    $this->reader->append("\x03\x00\x00\x00one\x03\x00\x00\x00two\x05\x00\x00\x00three\x04\x00\x00\x00four");

    $result = readPayload(function (PayloadReader $payloadReader) {
        return [
            $payloadReader->readRestOfPacketString(),
            $payloadReader->readRestOfPacketString(),
            $payloadReader->readRestOfPacketString(),
            $payloadReader->readRestOfPacketString(),
        ];
    });

    expect($result)->toEqual(['one', 'two', 'three', 'four']);
});

test('reads multiple packets packet strings added as single network packet in multiple payloads', function () {
    $this->reader->append("\x03\x00\x00\x00one\x03\x00\x00\x00two\x05\x00\x00\x00three\x04\x00\x00\x00four");

    $readString = function (PayloadReader $payloadReader) {
        return $payloadReader->readRestOfPacketString();
    };

    $results = [
        readPayload($readString),
        readPayload($readString),
        readPayload($readString),
        readPayload($readString),
    ];

    expect($results)->toEqual(['one', 'two', 'three', 'four']);
});

test('provides sequence number and packet length during reading of payload', function () {
    $this->reader->append("\x08\x00\x00\x00one\x00two\x00\x06\x00\x00\x01three\x00\x05\x00\x00\x05four\x00");

    $readString = function (PayloadReader $payloadReader, int $length, int $sequence) {
        $payloadReader->readNullTerminatedString();

        return [$length, $sequence];
    };

    $results = [
        readPayload($readString),
        readPayload($readString),
        readPayload($readString),
        readPayload($readString),
    ];

    expect($results)->toEqual([[8, 0], [8, 0], [6, 1], [5, 5]]);
});
