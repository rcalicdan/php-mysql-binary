<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Packet\PacketFramer;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

function makeFramer(): PacketFramer
{
    return new PacketFramer();
}

function makeFramerWriter(): UncompressedPacketWriter
{
    return new UncompressedPacketWriter();
}

test('frame returns a Generator', function () {
    $seqId = 0;
    $result = makeFramer()->frame('hello', makeFramerWriter(), $seqId);

    expect($result)->toBeInstanceOf(Generator::class);
});

test('frame yields exactly one packet for a small payload', function () {
    $seqId = 0;
    $packets = iterator_to_array(makeFramer()->frame('hello', makeFramerWriter(), $seqId));

    expect($packets)->toHaveCount(1);
});

test('frame yields exactly one packet for an empty payload', function () {
    $seqId = 0;
    $packets = iterator_to_array(makeFramer()->frame('', makeFramerWriter(), $seqId));

    expect($packets)->toHaveCount(1);
});

test('frame single packet content matches writer output', function () {
    $seqId = 0;
    $payload = 'SELECT 1';
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    expect($packets[0])->toBe(makeFramerWriter()->write($payload, 0));
});

test('frame empty payload packet is a valid 4-byte header with zero length', function () {
    $seqId = 0;
    $packets = iterator_to_array(makeFramer()->frame('', makeFramerWriter(), $seqId));

    expect(strlen($packets[0]))->toBe(4)
        ->and($packets[0][0])->toBe("\x00")
        ->and($packets[0][1])->toBe("\x00")
        ->and($packets[0][2])->toBe("\x00")
    ;
});

test('frame uses the provided starting sequenceId', function () {
    $seqId = 7;
    $packets = iterator_to_array(makeFramer()->frame('hello', makeFramerWriter(), $seqId));

    expect(ord($packets[0][3]))->toBe(7);
});

test('frame increments sequenceId by reference after a single yield', function () {
    $seqId = 3;
    iterator_to_array(makeFramer()->frame('hello', makeFramerWriter(), $seqId));

    expect($seqId)->toBe(4);
});

test('frame increments sequenceId by reference across multiple chunks', function () {
    $seqId = 0;
    $payload = str_repeat('A', PacketFramer::MAX_PACKET_SIZE + 100);
    iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    expect($seqId)->toBe(2);
});

test('frame yields two packets when payload length equals MAX_PACKET_SIZE', function () {
    $seqId = 0;
    $payload = str_repeat('X', PacketFramer::MAX_PACKET_SIZE);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    expect($packets)->toHaveCount(2);
});

test('frame second packet is an empty terminator when payload equals MAX_PACKET_SIZE', function () {
    $seqId = 0;
    $payload = str_repeat('X', PacketFramer::MAX_PACKET_SIZE);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    expect($packets[1])->toBe(makeFramerWriter()->write('', 1));
});

test('frame first chunk of MAX_PACKET_SIZE payload has correct sequence ID', function () {
    $seqId = 5;
    $payload = str_repeat('X', PacketFramer::MAX_PACKET_SIZE);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    expect(ord($packets[0][3]))->toBe(5)
        ->and(ord($packets[1][3]))->toBe(6)
    ;
});

test('frame yields three packets for a payload of 2x MAX_PACKET_SIZE', function () {
    $seqId = 0;
    $payload = str_repeat('A', PacketFramer::MAX_PACKET_SIZE * 2);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    expect($packets)->toHaveCount(3);
});

test('frame chunk payloads reassemble into the original payload', function () {
    $seqId = 0;
    $payload = str_repeat('Z', PacketFramer::MAX_PACKET_SIZE + 500);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    $reassembled = implode('', array_map(fn ($p) => substr($p, 4), $packets));

    expect($reassembled)->toBe($payload);
});

test('frame each chunk except the last has exactly MAX_PACKET_SIZE bytes of payload', function () {
    $seqId = 0;
    $payload = str_repeat('B', PacketFramer::MAX_PACKET_SIZE + 1);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    $firstChunkPayloadLength = unpack('V', substr($packets[0], 0, 3) . "\x00")[1];

    expect($firstChunkPayloadLength)->toBe(PacketFramer::MAX_PACKET_SIZE);
});

test('frame last chunk contains the remaining payload bytes', function () {
    $seqId = 0;
    $remainder = 42;
    $payload = str_repeat('C', PacketFramer::MAX_PACKET_SIZE + $remainder);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    $lastChunkPayloadLength = unpack('V', substr(end($packets), 0, 3) . "\x00")[1];

    expect($lastChunkPayloadLength)->toBe($remainder);
});

test('frame sequence IDs are contiguous across all chunks', function () {
    $seqId = 2;
    $payload = str_repeat('D', PacketFramer::MAX_PACKET_SIZE * 2 + 1);
    $packets = iterator_to_array(makeFramer()->frame($payload, makeFramerWriter(), $seqId));

    $ids = array_map(fn ($p) => ord($p[3]), $packets);

    expect($ids)->toBe([2, 3, 4]);
});
