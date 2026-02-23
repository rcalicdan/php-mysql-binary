<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

function makeWriter(): UncompressedPacketWriter
{
    return new UncompressedPacketWriter();
}

test('UncompressedPacketWriter produces correct 4-byte header', function () {
    $payload = 'hello';
    $result = makeWriter()->write($payload, 0);

    $length = unpack('V', substr($result, 0, 3) . "\x00")[1];

    expect($length)->toBe(5)
        ->and(ord($result[3]))->toBe(0);
});

test('UncompressedPacketWriter encodes length as 3-byte little-endian', function () {
    // 256 = 0x000100 in little-endian = "\x00\x01\x00"
    $payload = str_repeat('A', 256);
    $result = makeWriter()->write($payload, 0);

    expect($result[0])->toBe("\x00")
        ->and($result[1])->toBe("\x01")
        ->and($result[2])->toBe("\x00");
});

test('UncompressedPacketWriter correctly encodes sequence ID in header byte', function () {
    $result = makeWriter()->write('data', 42);

    expect(ord($result[3]))->toBe(42);
});

test('UncompressedPacketWriter sequence ID at max value 255', function () {
    $result = makeWriter()->write('data', 255);

    expect(ord($result[3]))->toBe(255);
});

test('UncompressedPacketWriter appends payload after header', function () {
    $payload = 'hello world';
    $result = makeWriter()->write($payload, 0);

    expect(substr($result, 4))->toBe($payload);
});

test('UncompressedPacketWriter total length is header plus payload', function () {
    $payload = 'test';
    $result = makeWriter()->write($payload, 1);

    expect(strlen($result))->toBe(4 + strlen($payload));
});

test('UncompressedPacketWriter handles empty payload', function () {
    $result = makeWriter()->write('', 0);

    expect(strlen($result))->toBe(4)
        ->and($result[0])->toBe("\x00")
        ->and($result[1])->toBe("\x00")
        ->and($result[2])->toBe("\x00");
});

test('UncompressedPacketWriter accepts payload at exact max size', function () {
    $payload = str_repeat('X', 16777215);
    $result = makeWriter()->write($payload, 0);

    expect(strlen($result))->toBe(4 + 16777215);
});

test('UncompressedPacketWriter output matches expected packet format', function () {
    $payload = 'SELECT 1';
    $result = makeWriter()->write($payload, 3);

    expect($result)->toBe(buildRawPacket($payload, 3));
});


test('UncompressedPacketWriter throws when payload exceeds max packet size', function () {
    $payload = str_repeat('X', 16777216);

    expect(fn () => makeWriter()->write($payload, 0))
        ->toThrow(InvalidArgumentException::class);
});

test('UncompressedPacketWriter exception message contains actual and max size', function () {
    $payload = str_repeat('X', 16777216);

    expect(fn () => makeWriter()->write($payload, 0))
        ->toThrow(InvalidArgumentException::class, '16777216')
        ->toThrow(InvalidArgumentException::class, '16777215');
});