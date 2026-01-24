<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

/**
 * Interface for writing various data types to MySQL protocol payloads.
 */
interface PayloadWriter
{
    /**
     * Writes an unsigned 8-bit integer (1 byte) to the payload.
     *
     * @param int $value The value to write (0-255)
     * @return self Returns the instance for method chaining
     */
    public function writeUInt8(int $value): self;

    /**
     * Writes an unsigned 16-bit integer (2 bytes) to the payload in little-endian format.
     *
     * @param int $value The value to write (0-65535)
     * @return self Returns the instance for method chaining
     */
    public function writeUInt16(int $value): self;

    /**
     * Writes an unsigned 32-bit integer (4 bytes) to the payload in little-endian format.
     *
     * @param int $value The value to write (0-4294967295)
     * @return self Returns the instance for method chaining
     */
    public function writeUInt32(int $value): self;

    /**
     * Writes an unsigned 64-bit integer (8 bytes) to the payload in little-endian format.
     *
     * @param int $value The value to write (0-18446744073709551615)
     * @return self Returns the instance for method chaining
     */
    public function writeUInt64(int $value): self;
    
    /**
     * Writes a MySQL Length-Encoded Integer (LENENC).
     * This is used to prefix string lengths and null bitmaps.
     *
     * The encoding format is:
     * - If value < 251: 1 byte
     * - If value >= 251 and < 2^16: 0xFC + 2 bytes
     * - If value >= 2^16 and < 2^24: 0xFD + 3 bytes
     * - If value >= 2^24: 0xFE + 8 bytes
     *
     * @param int $value The integer value to encode
     * @return self Returns the instance for method chaining
     */
    public function writeLengthEncodedInteger(int $value): self;

    /**
     * Writes a raw string (fixed size).
     *
     * @param string $value The string to write without any encoding or termination
     * @return self Returns the instance for method chaining
     */
    public function writeString(string $value): self;

    /**
     * Writes a string terminated by a null byte (0x00).
     *
     * @param string $value The string to write (null terminator will be appended)
     * @return self Returns the instance for method chaining
     */
    public function writeNullTerminatedString(string $value): self;

    /**
     * Writes a string prefixed by its length (as a LENENC integer).
     *
     * @param string $value The string to write (will be prefixed with its byte length)
     * @return self Returns the instance for method chaining
     */
    public function writeLengthEncodedString(string $value): self;

    /**
     * Writes a specific number of null (0x00) bytes.
     * Useful for reserved fillers in protocol packets.
     *
     * @param int $count The number of null bytes to write
     * @return self Returns the instance for method chaining
     */
    public function writeZeros(int $count): self;

    /**
     * Returns the constructed binary string.
     *
     * @return string The complete binary payload as a string
     */
    public function toString(): string;
}