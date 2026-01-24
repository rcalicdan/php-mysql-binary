<?php

use Rcalicdan\MySQLBinaryProtocol\Factory\DefaultPacketWriterFactory;

beforeEach(function () {
    $this->writer = (new DefaultPacketWriterFactory())->createWithDefaultSettings();
});

test('writes correct packet header for small payload', function () {
    $payload = 'hello';
    $sequence = 1;
    
    
    $packet = $this->writer->write($payload, $sequence);
    
    expect($packet)->toBe("\x05\x00\x00\x01hello");
});

test('writes correct packet header for empty payload', function () {
    $payload = '';
    $sequence = 0;
    
    $packet = $this->writer->write($payload, $sequence);
    
    expect($packet)->toBe("\x00\x00\x00\x00");
});

test('writes correct packet header for large payload', function () {
    $payload = str_repeat('a', 256);
    $sequence = 2;
    
    
    $packet = $this->writer->write($payload, $sequence);
    
    expect(substr($packet, 0, 4))->toBe("\x00\x01\x00\x02")
        ->and(substr($packet, 4))->toBe($payload);
});

test('throws exception if payload is too large for single packet', function () {
    $payload = str_repeat('a', 16777216); 
    
    expect(fn() => $this->writer->write($payload, 0))
        ->toThrow(InvalidArgumentException::class);
});