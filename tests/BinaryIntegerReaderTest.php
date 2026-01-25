<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;

test('reads fixed one byte integer')
    ->with([
        'zero' => ["\x00", 0],
        'max_value' => ["\xFF", 255],
        'mid_value' => ["\x7F", 127],
    ])
    ->expect(function ($input, $expected) {
        $reader = new BinaryIntegerReader();
        $result = $reader->readFixed($input, 1);
        expect($result)->toEqual($expected);
    })
;

test('reads fixed two byte integer')
    ->with([
        'zero' => ["\x00\x00", 0],
        'little_endian' => ["\x34\x12", 0x1234],
        'max_value' => ["\xFF\xFF", 65535],
    ])
    ->expect(function ($input, $expected) {
        $reader = new BinaryIntegerReader();
        $result = $reader->readFixed($input, 2);
        expect($result)->toEqual($expected);
    })
;

test('reads fixed three byte integer')
    ->with([
        'zero' => ["\x00\x00\x00", 0],
        'little_endian' => ["\x56\x34\x12", 0x123456],
        'max_value' => ["\xFF\xFF\xFF", 16777215],
    ])
    ->expect(function ($input, $expected) {
        $reader = new BinaryIntegerReader();
        $result = $reader->readFixed($input, 3);
        expect($result)->toEqual($expected);
    })
;

test('reads fixed four byte integer')
    ->with([
        'zero' => ["\x00\x00\x00\x00", 0],
        'little_endian' => ["\x78\x56\x34\x12", 0x12345678],
        'max_value' => ["\xFF\xFF\xFF\xFF", 4294967295],
    ])
    ->expect(function ($input, $expected) {
        $reader = new BinaryIntegerReader();
        $result = $reader->readFixed($input, 4);
        expect($result)->toEqual($expected);
    })
;

test('reads fixed eight byte integers')
    ->with([
        'zero' => ["\x00\x00\x00\x00\x00\x00\x00\x00", 0],
        'little_endian' => ["\xBC\x9A\x78\x56\x34\x12\x00\x00", 0x123456789ABC],
        'simple_value' => ["\x01\x00\x00\x00\x00\x00\x00\x00", 1],
    ])
    ->expect(function ($input, $expected) {
        $reader = new BinaryIntegerReader();
        $result = $reader->readFixed($input, 8);
        expect($result)->toEqual($expected);
    })
;

// Error cases - Updated syntax
test('throws exception for invalid fixed integer length', function () {
    $reader = new BinaryIntegerReader();

    expect(fn () => $reader->readFixed("\x00", 9))
        ->toThrow(InvalidArgumentException::class, 'Cannot read integers above 8 bytes')
    ;
});

test('throws exception for insufficient data for 2 byte integer', function () {
    $reader = new BinaryIntegerReader();

    expect(fn () => $reader->readFixed("\x00", 2))
        ->toThrow(InvalidArgumentException::class, 'Insufficient data for 2 byte integer')
    ;
});

test('throws exception for insufficient data for 4 byte integer', function () {
    $reader = new BinaryIntegerReader();

    expect(fn () => $reader->readFixed("\x00\x00", 4))
        ->toThrow(InvalidArgumentException::class, 'Insufficient data for 4 byte integer')
    ;
});

test('throws exception for insufficient data for 8 byte integer', function () {
    $reader = new BinaryIntegerReader();

    expect(fn () => $reader->readFixed("\x00\x00\x00", 8))
        ->toThrow(InvalidArgumentException::class, 'Insufficient data for 8 byte integer')
    ;
});

// Additional edge cases
test('handles 5 byte integer by padding to 8 bytes')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\x01\x00\x00\x00\x00", 5);
    })
    ->toBe(1)
;

test('handles 6 byte integer by padding to 8 bytes')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\xFF\xFF\xFF\xFF\xFF\xFF", 6);
    })
    ->toBe(281474976710655) // 2^48 - 1
;

test('handles 7 byte integer by padding to 8 bytes')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\xFF\xFF\xFF\xFF\xFF\xFF\xFF", 7);
    })
    ->toBe(72057594037927935) // 2^56 - 1
;

// Test truncation for 8-byte integers with more than 8 bytes
test('truncates 8 byte integer when given more than 8 bytes')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\x01\x00\x00\x00\x00\x00\x00\x00\xFF\xFF", 8);
    })
    ->toBe(1)
;

// Test specific unpack formats
test('reads 1 byte using unpack C format')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\x80", 1);
    })
    ->toBe(128)
;

test('reads 2 bytes using unpack v format (little endian)')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\x00\x01", 2);
    })
    ->toBe(256)
;

test('reads 4 bytes using unpack V format (little endian)')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\x00\x00\x00\x01", 4);
    })
    ->toBe(16777216)
;

test('reads 8 bytes using custom hex conversion')
    ->expect(function () {
        $reader = new BinaryIntegerReader();

        return $reader->readFixed("\x00\x00\x00\x00\x00\x00\x00\x01", 8);
    })
    ->toBe(72057594037927936); // 2^56
