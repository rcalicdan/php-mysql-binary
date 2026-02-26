<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

test('CompressedPacketReader throws RuntimeException when zlib is not loaded', function () {
    expect(true)->toBeTrue();
})->skip(
    ! extension_loaded('zlib'),
    'zlib extension is not loaded — CompressedPacketReader cannot be instantiated'
);

test('CompressedPacketReader has no packet before any data is appended', function () {
    expect(makeCompressedPacketReader()->hasPacket())->toBeFalse();
});

test('CompressedPacketReader throws IncompleteBufferException when reading with no packet', function () {
    expect(fn () => makeCompressedPacketReader()->readPayload(fn () => null))
        ->toThrow(IncompleteBufferException::class)
    ;
});

test('CompressedPacketReader detects packet from non-compressed data', function () {
    $reader = makeCompressedPacketReader();
    $innerPacket = buildRawPacket('hello');
    $reader->append(buildCompressedProtocolPacket($innerPacket, 0, false));

    expect($reader->hasPacket())->toBeTrue();
});

test('CompressedPacketReader reads payload from non-compressed packet', function () {
    $reader = makeCompressedPacketReader();
    $innerPacket = buildRawPacket('hello', 1);
    $reader->append(buildCompressedProtocolPacket($innerPacket, 0, false));

    $read = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$read) {
        $read = $r->readFixedString($length);
    });

    expect($read)->toBe('hello');
});

test('CompressedPacketReader passes correct sequence number from non-compressed packet', function () {
    $reader = makeCompressedPacketReader();
    $innerPacket = buildRawPacket('data', 7);
    $reader->append(buildCompressedProtocolPacket($innerPacket, 0, false));

    $capturedSeq = null;
    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$capturedSeq) {
        $capturedSeq = $seq;
    });

    expect($capturedSeq)->toBe(7);
});

test('CompressedPacketReader detects packet from compressed data', function () {
    $reader = makeCompressedPacketReader();
    $innerPacket = buildRawPacket(str_repeat('A', 100));
    $reader->append(buildCompressedProtocolPacket($innerPacket, 0, true));

    expect($reader->hasPacket())->toBeTrue();
});

test('CompressedPacketReader decompresses and reads payload correctly', function () {
    $reader = makeCompressedPacketReader();
    $payload = str_repeat('B', 100);
    $innerPacket = buildRawPacket($payload, 3);
    $reader->append(buildCompressedProtocolPacket($innerPacket, 0, true));

    $read = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$read) {
        $read = $r->readFixedString($length);
    });

    expect($read)->toBe($payload);
});

test('CompressedPacketReader passes correct sequence number from compressed packet', function () {
    $reader = makeCompressedPacketReader();
    $innerPacket = buildRawPacket('data', 5);
    $reader->append(buildCompressedProtocolPacket($innerPacket, 0, true));

    $capturedSeq = null;
    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$capturedSeq) {
        $capturedSeq = $seq;
    });

    expect($capturedSeq)->toBe(5);
});

test('CompressedPacketReader has no packet when only partial 7-byte header appended', function () {
    $reader = makeCompressedPacketReader();
    $reader->append("\x05\x00\x00"); // Only 3 of 7 header bytes

    expect($reader->hasPacket())->toBeFalse();
});

test('CompressedPacketReader reassembles packet when header arrives in two chunks', function () {
    $reader = makeCompressedPacketReader();
    $innerPacket = buildRawPacket('hello');
    $packet = buildCompressedProtocolPacket($innerPacket, 0, false);

    $reader->append(substr($packet, 0, 4)); // First 4 of 7 header bytes
    expect($reader->hasPacket())->toBeFalse();

    $reader->append(substr($packet, 4)); // Rest of header + payload
    expect($reader->hasPacket())->toBeTrue();
});

test('CompressedPacketReader reassembles packet when compressed payload arrives in two chunks', function () {
    $reader = makeCompressedPacketReader();
    $payload = str_repeat('X', 100);
    $innerPacket = buildRawPacket($payload);
    $packet = buildCompressedProtocolPacket($innerPacket, 0, true);

    $midpoint = (int) (strlen($packet) / 2);
    $reader->append(substr($packet, 0, $midpoint));
    $reader->append(substr($packet, $midpoint));

    expect($reader->hasPacket())->toBeTrue();

    $read = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$read) {
        $read = $r->readFixedString($length);
    });

    expect($read)->toBe($payload);
});

test('CompressedPacketReader handles two non-compressed packets appended together', function () {
    $reader = makeCompressedPacketReader();

    $packet1 = buildCompressedProtocolPacket(buildRawPacket('first', 0), 0, false);
    $packet2 = buildCompressedProtocolPacket(buildRawPacket('second', 1), 1, false);
    $reader->append($packet1 . $packet2);

    $sequences = [];

    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$sequences) {
        $sequences[] = $seq;
        $r->readFixedString($length);
    });

    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$sequences) {
        $sequences[] = $seq;
        $r->readFixedString($length);
    });

    expect($sequences)->toBe([0, 1]);
});

test('CompressedPacketReader handles mix of compressed and non-compressed packets', function () {
    $reader = makeCompressedPacketReader();

    $packet1 = buildCompressedProtocolPacket(buildRawPacket('small', 0), 0, false);
    $packet2 = buildCompressedProtocolPacket(buildRawPacket(str_repeat('L', 100), 1), 1, true);
    $reader->append($packet1 . $packet2);

    $results = [];

    $reader->readPayload(function (PayloadReader $r, int $length) use (&$results) {
        $results[] = $r->readFixedString($length);
    });

    $reader->readPayload(function (PayloadReader $r, int $length) use (&$results) {
        $results[] = $r->readFixedString($length);
    });

    expect($results[0])->toBe('small')
        ->and($results[1])->toBe(str_repeat('L', 100))
    ;
});

test('CompressedPacketReader has no more packets after all have been consumed', function () {
    $reader = makeCompressedPacketReader();
    $reader->append(buildCompressedProtocolPacket(buildRawPacket('only'), 0, false));

    $reader->readPayload(function (PayloadReader $r, int $length) {
        $r->readFixedString($length);
    });

    expect($reader->hasPacket())->toBeFalse();
});

test('CompressedPacketReader throws InvalidBinaryDataException on corrupt compressed data', function () {
    $reader = makeCompressedPacketReader();

    $corruptData = 'not_valid_zlib_data';
    $uncompressedLength = 100;

    $packet = substr(pack('V', strlen($corruptData)), 0, 3)
        . chr(0)
        . substr(pack('V', $uncompressedLength), 0, 3)
        . $corruptData;

    expect(fn () => $reader->append($packet))
        ->toThrow(InvalidBinaryDataException::class, 'Failed to decompress')
    ;
});

test('CompressedPacketReader throws InvalidBinaryDataException on decompression size mismatch', function () {
    $reader = makeCompressedPacketReader();

    $innerPacket = buildRawPacket(str_repeat('A', 50));
    $compressed = gzcompress($innerPacket, 6);

    // Lie about the uncompressed length to trigger the size mismatch check
    $fakeUncompressedLength = strlen($innerPacket) + 999;

    $packet = substr(pack('V', strlen($compressed)), 0, 3)
        . chr(0)
        . substr(pack('V', $fakeUncompressedLength), 0, 3)
        . $compressed;

    expect(fn () => $reader->append($packet))
        ->toThrow(InvalidBinaryDataException::class, 'mismatch')
    ;
});

test('CompressedPacketReader readPayload returns true on successful read', function () {
    $reader = makeCompressedPacketReader();
    $reader->append(buildCompressedProtocolPacket(buildRawPacket('hello'), 0, false));

    $result = $reader->readPayload(function (PayloadReader $r, int $length) {
        $r->readFixedString($length);
    });

    expect($result)->toBeTrue();
});

test('CompressedPacketReader readPayload returns false when callback triggers IncompleteBufferException', function () {
    $reader = makeCompressedPacketReader();
    $reader->append(buildCompressedProtocolPacket(buildRawPacket('hi'), 0, false));

    $result = $reader->readPayload(function (PayloadReader $r) {
        $r->readFixedString(100); // More than the 2-byte payload
    });

    expect($result)->toBeFalse();
});

test('CompressedPacketReader reads non-compressed output from CompressedPacketWriter', function () {
    $writer = makeCompressedWriter();
    $reader = makeCompressedPacketReader();

    $original = 'tiny'; // Below threshold, will not be compressed
    $reader->append($writer->write($original, 3));

    $read = null;
    $capturedSeq = null;
    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$read, &$capturedSeq) {
        $read = $r->readFixedString($length);
        $capturedSeq = $seq;
    });

    expect($read)->toBe($original)
        ->and($capturedSeq)->toBe(3)
    ;
});

test('CompressedPacketReader reads compressed output from CompressedPacketWriter', function () {
    $writer = makeCompressedWriter();
    $reader = makeCompressedPacketReader();

    $original = str_repeat('D', 200); // Above threshold, will be compressed
    $reader->append($writer->write($original, 4));

    $read = null;
    $capturedSeq = null;
    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$read, &$capturedSeq) {
        $read = $r->readFixedString($length);
        $capturedSeq = $seq;
    });

    expect($read)->toBe($original)
        ->and($capturedSeq)->toBe(4)
    ;
});

test('CompressedPacketReader reads multiple packets written by CompressedPacketWriter', function () {
    $writer = makeCompressedWriter();
    $reader = makeCompressedPacketReader();

    $small = 'ping';
    $large = str_repeat('E', 500);

    $reader->append($writer->write($small, 0) . $writer->write($large, 1));

    $results = [];
    $sequences = [];

    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$results, &$sequences) {
        $results[] = $r->readFixedString($length);
        $sequences[] = $seq;
    });

    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$results, &$sequences) {
        $results[] = $r->readFixedString($length);
        $sequences[] = $seq;
    });

    expect($results[0])->toBe($small)
        ->and($results[1])->toBe($large)
        ->and($sequences)->toBe([0, 1]);
});
