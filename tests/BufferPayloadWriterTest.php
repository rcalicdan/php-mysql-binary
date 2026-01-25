<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriter;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriterFactory;

beforeEach(function () {
    $this->writer = (new BufferPayloadWriterFactory())->create();
});

test('writes single bytes', function () {
    $result = $this->writer
        ->writeUInt8(1)
        ->writeUInt8(2)
        ->toString()
    ;

    expect($result)->toBe("\x01\x02");
});

test('writes length encoded integers', function () {
    expect((new BufferPayloadWriter())->writeLengthEncodedInteger(250)->toString())
        ->toBe("\xFA")
    ;

    expect((new BufferPayloadWriter())->writeLengthEncodedInteger(251)->toString())
        ->toBe("\xFC\xFB\x00")
    ;

    expect((new BufferPayloadWriter())->writeLengthEncodedInteger(65536)->toString())
        ->toBe("\xFD\x00\x00\x01")
    ;

    expect((new BufferPayloadWriter())->writeLengthEncodedInteger(16777216)->toString())
        ->toBe("\xFE\x00\x00\x00\x01\x00\x00\x00\x00")
    ;
});

test('writes raw string', function () {
    $result = $this->writer->writeString('hello')->toString();
    expect($result)->toBe('hello');
});

test('writes null terminated string', function () {
    $result = $this->writer->writeNullTerminatedString('mysql')->toString();
    expect($result)->toBe("mysql\x00");
});

test('writes length encoded string', function () {
    $result = $this->writer->writeLengthEncodedString('a')->toString();
    expect($result)->toBe("\x01a");

    $longString = str_repeat('a', 251);
    $result = (new BufferPayloadWriter())->writeLengthEncodedString($longString)->toString();

    expect($result)->toBe("\xFC\xFB\x00" . $longString);
});

test('writes zeros', function () {
    $result = $this->writer->writeZeros(5)->toString();
    expect($result)->toBe("\x00\x00\x00\x00\x00");
});

test('chains multiple writes correctly', function () {
    $result = $this->writer
        ->writeUInt8(1)
        ->writeString('AB')
        ->writeNullTerminatedString('C')
        ->toString()
    ;

    expect($result)->toBe("\x01AB\x43\x00");
});
