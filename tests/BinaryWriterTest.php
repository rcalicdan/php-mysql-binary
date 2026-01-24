<?php

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BinaryWriter;

beforeEach(function () {
    $this->writer = new BinaryWriter();
});

test('writes unsigned 1-byte integer', function () {
    expect($this->writer->writeUInt8(0))->toBe("\x00")
        ->and($this->writer->writeUInt8(255))->toBe("\xFF")
        ->and($this->writer->writeUInt8(1))->toBe("\x01");
});

test('writes unsigned 2-byte integer little endian', function () {
    expect($this->writer->writeUInt16(0))->toBe("\x00\x00")
        ->and($this->writer->writeUInt16(255))->toBe("\xFF\x00")
        ->and($this->writer->writeUInt16(65535))->toBe("\xFF\xFF")
        ->and($this->writer->writeUInt16(0x1234))->toBe("\x34\x12");
});

test('writes unsigned 3-byte integer little endian', function () {
    expect($this->writer->writeUInt24(0))->toBe("\x00\x00\x00")
        ->and($this->writer->writeUInt24(0x123456))->toBe("\x56\x34\x12")
        ->and($this->writer->writeUInt24(16777215))->toBe("\xFF\xFF\xFF");
});

test('writes unsigned 4-byte integer little endian', function () {
    expect($this->writer->writeUInt32(0))->toBe("\x00\x00\x00\x00")
        ->and($this->writer->writeUInt32(0x12345678))->toBe("\x78\x56\x34\x12");
});

test('writes unsigned 8-byte integer little endian', function () {
    expect($this->writer->writeUInt64(0x123456789ABCDEF0))
        ->toBe("\xF0\xDE\xBC\x9A\x78\x56\x34\x12");
});

test('writes float little endian', function () {
    expect($this->writer->writeFloat(1.5))->toBe("\x00\x00\xC0\x3F");
});

test('writes double little endian', function () {
    expect($this->writer->writeDouble(1.5))->toBe("\x00\x00\x00\x00\x00\x00\xF8\x3F");
});