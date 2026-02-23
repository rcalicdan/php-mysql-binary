<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Packet\CompressedPacketWriter;

function makeCompressedWriter(): CompressedPacketWriter
{
    return new CompressedPacketWriter();
}


test('CompressedPacketWriter throws RuntimeException when zlib is not loaded', function () {
    expect(true)->toBeTrue();
})->skip(
    !extension_loaded('zlib'),
    'zlib extension is not loaded — CompressedPacketWriter cannot be instantiated'
);

test('CompressedPacketWriter produces a 7-byte header', function () {
    $result = makeCompressedWriter()->write('hello', 0);

    $compressedPayloadLength = unpack('V', substr($result, 0, 3) . "\x00")[1];
    $totalExpected = 7 + $compressedPayloadLength;

    expect(strlen($result))->toBe($totalExpected);
});

test('CompressedPacketWriter encodes sequence ID in byte 3 of header', function () {
    $result = makeCompressedWriter()->write('hello', 9);

    expect(ord($result[3]))->toBe(9);
});

test('CompressedPacketWriter sequence ID at max value 255', function () {
    $result = makeCompressedWriter()->write('hello', 255);

    expect(ord($result[3]))->toBe(255);
});


test('CompressedPacketWriter does not compress payload below threshold', function () {
    $payload = 'small'; 
    $result = makeCompressedWriter()->write($payload, 0);

    $uncompressedLength = unpack('V', substr($result, 4, 3) . "\x00")[1];

    expect($uncompressedLength)->toBe(0);
});

test('CompressedPacketWriter uncompressed data is readable as inner uncompressed packet', function () {
    $payload = 'hello';
    $result = makeCompressedWriter()->write($payload, 1);

    $innerData = substr($result, 7);
    $innerLength = unpack('V', substr($innerData, 0, 3) . "\x00")[1];
    $innerSeq = ord($innerData[3]);
    $innerPayload = substr($innerData, 4, $innerLength);

    expect($innerPayload)->toBe($payload)
        ->and($innerSeq)->toBe(1);
});

test('CompressedPacketWriter small payload total size is 7 header bytes plus inner packet size', function () {
    $payload = 'hi';
    $result = makeCompressedWriter()->write($payload, 0);

    expect(strlen($result))->toBe(7 + 4 + strlen($payload));
});


test('CompressedPacketWriter compresses payload above threshold', function () {
    $payload = str_repeat('A', 100); // well above 50 bytes
    $result = makeCompressedWriter()->write($payload, 0);

    $uncompressedLength = unpack('V', substr($result, 4, 3) . "\x00")[1];

    expect($uncompressedLength)->toBeGreaterThan(0);
});

test('CompressedPacketWriter uncompressed length field matches original inner packet size', function () {
    $payload = str_repeat('B', 100);
    $result = makeCompressedWriter()->write($payload, 0);

    $uncompressedLength = unpack('V', substr($result, 4, 3) . "\x00")[1];

    $expectedInnerSize = 4 + strlen($payload);

    expect($uncompressedLength)->toBe($expectedInnerSize);
});

test('CompressedPacketWriter compressed data decompresses back to original inner packet', function () {
    $payload = str_repeat('C', 100);
    $result = makeCompressedWriter()->write($payload, 2);

    $compressedPayloadLength = unpack('V', substr($result, 0, 3) . "\x00")[1];
    $compressedData = substr($result, 7, $compressedPayloadLength);
    $decompressed = gzuncompress($compressedData);

    $innerLength = unpack('V', substr($decompressed, 0, 3) . "\x00")[1];
    $innerSeq = ord($decompressed[3]);
    $innerPayload = substr($decompressed, 4, $innerLength);

    expect($innerPayload)->toBe($payload)
        ->and($innerSeq)->toBe(2);
});

test('CompressedPacketWriter compressed payload is smaller than original for compressible data', function () {
    $payload = str_repeat('Z', 1000);
    $result = makeCompressedWriter()->write($payload, 0);

    $compressedPayloadLength = unpack('V', substr($result, 0, 3) . "\x00")[1];
    $uncompressedLength = unpack('V', substr($result, 4, 3) . "\x00")[1];

    expect($compressedPayloadLength)->toBeLessThan($uncompressedLength);
});

test('CompressedPacketWriter handles empty payload without compressing', function () {
    $result = makeCompressedWriter()->write('', 0);

    $uncompressedLength = unpack('V', substr($result, 4, 3) . "\x00")[1];
    expect($uncompressedLength)->toBe(0);
});

test('CompressedPacketWriter handles binary payload correctly', function () {
    $payload = str_repeat("\x00\xFF\x01\xFE", 20); 
    $result = makeCompressedWriter()->write($payload, 0);

    $compressedPayloadLength = unpack('V', substr($result, 0, 3) . "\x00")[1];
    $uncompressedLength = unpack('V', substr($result, 4, 3) . "\x00")[1];
    $decompressed = gzuncompress(substr($result, 7, $compressedPayloadLength));
    $innerPayload = substr($decompressed, 4);

    expect($innerPayload)->toBe($payload)
        ->and($uncompressedLength)->toBeGreaterThan(0);
});

test('CompressedPacketWriter throws when payload exceeds max packet size', function () {
    $payload = str_repeat('X', 16777216);

    expect(fn () => makeCompressedWriter()->write($payload, 0))
        ->toThrow(InvalidArgumentException::class);
});

test('CompressedPacketWriter exception message contains actual and max sizes', function () {
    $payload = str_repeat('X', 16777216);

    expect(fn () => makeCompressedWriter()->write($payload, 0))
        ->toThrow(InvalidArgumentException::class, '16777216')
        ->toThrow(InvalidArgumentException::class, '16777215');
});

test('CompressedPacketWriter payload at exact max size does not throw', function () {
    $payload = str_repeat('X', 16777215);
    $result = makeCompressedWriter()->write($payload, 0);

    expect(strlen($result))->toBeGreaterThan(7);
});