<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;

beforeEach(function () {
    $this->readBuffer = new ReadBuffer();
});

test('reads buffer by length', function () {
    $this->readBuffer->append('Some string');

    expect($this->readBuffer->read(4))->toBe('Some');
});

test('reads buffer by moving position forward', function () {
    $this->readBuffer->append('TDD is awesome');

    $this->readBuffer->read(3);

    expect($this->readBuffer->read(11))->toBe(' is awesome');
});

test('throws incomplete buffer exception when buffer is smaller than read size', function () {
    $this->readBuffer->append('TDD is');

    $this->readBuffer->read(11);
})->throws(IncompleteBufferException::class);

test('throws incomplete buffer exception when not enough data is left to read', function () {
    $this->readBuffer->append('TDD is great');

    $this->readBuffer->read(7);

    $this->readBuffer->read(7);
})->throws(IncompleteBufferException::class);

test('allows to read all added pieces to buffer', function () {
    $this->readBuffer->append('TDD is');

    $this->readBuffer->read(4);

    $this->readBuffer->append(' great');

    expect($this->readBuffer->read(8))->toBe('is great');
});

test('is readable when asked bytes are below buffer length', function () {
    $this->readBuffer->append('Some data');

    expect($this->readBuffer->isReadable(4))->toBeTrue();
});

test('is not readable when bytes are longer than buffer length', function () {
    $this->readBuffer->append('Some');

    expect($this->readBuffer->isReadable(5))->toBeFalse();
});

test('is not readable when asked length is lower than remaining bytes to read', function () {
    $this->readBuffer->append('Some data');
    $this->readBuffer->read(5);

    expect($this->readBuffer->isReadable(5))->toBeFalse();
});

test('is readable when exact amount of bytes available to read', function () {
    $this->readBuffer->append('Data in buffer');

    $this->readBuffer->read(7);

    expect($this->readBuffer->isReadable(7))->toBeTrue();
});

test('reset allows to read data again after incomplete read', function () {
    $this->readBuffer->append('Data in buffer');
    $this->readBuffer->flush();

    $this->readBuffer->read(4);
    $this->readBuffer->read(4);

    expect(fn () => $this->readBuffer->read(7))
        ->toThrow(IncompleteBufferException::class)
    ;

    $this->readBuffer->reset();

    expect($this->readBuffer->read(8))->toBe('Data in ');
});

test('allows to move read buffer pointer after read', function () {
    $this->readBuffer->append('Data in buffer');

    $this->readBuffer->read(5);
    $this->readBuffer->flush();

    try {
        $this->readBuffer->read(10);
    } catch (IncompleteBufferException $e) {
    }

    expect($this->readBuffer->read(9))->toBe('in buffer');
});

test('clears buffer when read limit is reached', function () {
    $limitedReadBuffer = new ReadBuffer(20);

    $limitedReadBuffer->append('Some data to read 2 remainder of buffer');
    $limitedReadBuffer->read(10);
    $limitedReadBuffer->read(10);
    $limitedReadBuffer->flush();

    $expectedReadBuffer = new ReadBuffer(20);
    $expectedReadBuffer->append('remainder of buffer');

    expect($limitedReadBuffer)->toEqual($expectedReadBuffer);
});

test('clears buffer limit is reached a long time ago', function () {
    $limitedReadBuffer = new ReadBuffer(20);

    $limitedReadBuffer->append('Some data to read 2 very long string to read remainder of buffer');
    $limitedReadBuffer->read(10);
    $limitedReadBuffer->flush();
    $limitedReadBuffer->read(20);
    $limitedReadBuffer->read(15);
    $limitedReadBuffer->flush();

    $expectedReadBuffer = new ReadBuffer(20);
    $expectedReadBuffer->append('remainder of buffer');

    expect($limitedReadBuffer)->toEqual($expectedReadBuffer);
});

test('flush returns number of read bytes', function () {
    $this->readBuffer->append('Some data');
    $this->readBuffer->read(4);
    $this->readBuffer->read(2);

    expect($this->readBuffer->flush())->toBe(6);
});

test('flush returns zero when no bytes read', function () {
    $this->readBuffer->append('Some data');

    expect($this->readBuffer->flush())->toBe(0);
});

test('returns length of read in order to read data up to this character', function () {
    $this->readBuffer->append('some:data');

    expect($this->readBuffer->scan(':'))->toBe(5);
});

test('returns negative index when no match found for scan', function () {
    $this->readBuffer->append('some data without character');

    expect($this->readBuffer->scan(':'))->toBe(-1);
});

test('returns length of read even if character for search is a first one in buffer', function () {
    $this->readBuffer->append(':some other data');

    expect($this->readBuffer->scan(':'))->toBe(1);
});

test('returns length of required read for the next character occurrence', function () {
    $this->readBuffer->append('some:other:data');
    $this->readBuffer->read(5);

    expect($this->readBuffer->scan(':'))->toBe(6);
});

test('default read position in buffer is zero', function () {
    expect($this->readBuffer->currentPosition())->toBe(0);
});

test('current position is moved with number of read bytes', function () {
    $this->readBuffer->append('Some very long string data');

    $this->readBuffer->read(4);
    $this->readBuffer->read(6);

    expect($this->readBuffer->currentPosition())->toBe(10);
});

test('current position is relative to flushed read data', function () {
    $this->readBuffer->append('Some very long string data');

    $this->readBuffer->read(10);

    $this->readBuffer->flush();

    $this->readBuffer->read(3);

    expect($this->readBuffer->currentPosition())->toBe(3);
});
