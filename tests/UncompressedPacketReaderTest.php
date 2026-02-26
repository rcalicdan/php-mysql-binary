<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

test('UncompressedPacketReader has no packet before any data is appended', function () {
    expect(makePacketReader()->hasPacket())->toBeFalse();
});

test('UncompressedPacketReader throws IncompleteBufferException when reading with no packet', function () {
    expect(fn () => makePacketReader()->readPayload(fn () => null))
        ->toThrow(IncompleteBufferException::class)
    ;
});

test('UncompressedPacketReader detects packet after complete data is appended', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('hello'));

    expect($reader->hasPacket())->toBeTrue();
});

test('UncompressedPacketReader has no packet when only partial header appended', function () {
    $reader = makePacketReader();
    $reader->append("\x05\x00"); // Only 2 of 4 header bytes

    expect($reader->hasPacket())->toBeFalse();
});

test('UncompressedPacketReader registers packet from header even when payload not yet received', function () {
    $reader = makePacketReader();
    $reader->append("\x05\x00\x00\x00"); // Full header, no payload

    expect($reader->hasPacket())->toBeTrue();
});

test('UncompressedPacketReader passes correct length to readPayload callback', function () {
    $reader = makePacketReader();
    $payload = 'hello';
    $reader->append(buildRawPacket($payload));

    $capturedLength = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$capturedLength) {
        $capturedLength = $length;
    });

    expect($capturedLength)->toBe(strlen($payload));
});

test('UncompressedPacketReader passes correct sequence number to readPayload callback', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('data', 7));

    $capturedSeq = null;
    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$capturedSeq) {
        $capturedSeq = $seq;
    });

    expect($capturedSeq)->toBe(7);
});

test('UncompressedPacketReader passes PayloadReader instance to readPayload callback', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('X'));

    $capturedReader = null;
    $reader->readPayload(function (PayloadReader $r) use (&$capturedReader) {
        $capturedReader = $r;
    });

    expect($capturedReader)->toBeInstanceOf(PayloadReader::class);
});

test('UncompressedPacketReader readPayload returns true on successful read', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('hello'));

    $result = $reader->readPayload(function (PayloadReader $r, int $length) {
        $r->readFixedString($length);
    });

    expect($result)->toBeTrue();
});

test('UncompressedPacketReader readPayload returns false when callback triggers IncompleteBufferException', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('hi'));

    $result = $reader->readPayload(function (PayloadReader $r) {
        $r->readFixedString(100); // More than the 2-byte payload
    });

    expect($result)->toBeFalse();
});

test('UncompressedPacketReader reassembles packet when header arrives in two chunks', function () {
    $reader = makePacketReader();
    $packet = buildRawPacket('hello');

    $reader->append(substr($packet, 0, 2));
    expect($reader->hasPacket())->toBeFalse();

    $reader->append(substr($packet, 2));
    expect($reader->hasPacket())->toBeTrue();
});

test('UncompressedPacketReader reassembles packet when payload arrives in two chunks', function () {
    $reader = makePacketReader();
    $payload = 'hello world';
    $packet = buildRawPacket($payload);

    $reader->append(substr($packet, 0, 4 + 5)); // Header + first 5 payload bytes
    $reader->append(substr($packet, 4 + 5));    // Remaining payload

    expect($reader->hasPacket())->toBeTrue();

    $capturedLength = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$capturedLength) {
        $capturedLength = $length;
        $r->readFixedString($length);
    });

    expect($capturedLength)->toBe(strlen($payload));
});

test('UncompressedPacketReader handles two packets appended together', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('first', 0) . buildRawPacket('second', 1));

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

test('UncompressedPacketReader has no more packets after all have been consumed', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('only'));

    $reader->readPayload(function (PayloadReader $r, int $length) {
        $r->readFixedString($length);
    });

    expect($reader->hasPacket())->toBeFalse();
});

test('UncompressedPacketReader payload bytes are readable via PayloadReader', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket('hello'));

    $read = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$read) {
        $read = $r->readFixedString($length);
    });

    expect($read)->toBe('hello');
});

test('UncompressedPacketReader handles empty payload packet', function () {
    $reader = makePacketReader();
    $reader->append(buildRawPacket(''));

    $result = $reader->readPayload(fn () => null);

    expect($result)->toBeTrue();
});

test('UncompressedPacketReader handles binary payload correctly', function () {
    $reader = makePacketReader();
    $payload = "\x00\xFF\x01\xFE\x02\xFD";
    $reader->append(buildRawPacket($payload));

    $read = null;
    $reader->readPayload(function (PayloadReader $r, int $length) use (&$read) {
        $read = $r->readFixedString($length);
    });

    expect($read)->toBe($payload);
});

test('UncompressedPacketReader writer output is readable by reader', function () {
    $reader = makePacketReader();
    $writer = new Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter();

    $original = 'SELECT 1';
    $reader->append($writer->write($original, 5));

    $read = null;
    $capturedSeq = null;
    $reader->readPayload(function (PayloadReader $r, int $length, int $seq) use (&$read, &$capturedSeq) {
        $read = $r->readFixedString($length);
        $capturedSeq = $seq;
    });

    expect($read)->toBe($original)
        ->and($capturedSeq)->toBe(5)
    ;
});
