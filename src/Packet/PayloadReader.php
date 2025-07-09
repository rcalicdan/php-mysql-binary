<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

/**
 * Interface for reading various data types from MySQL protocol payloads.
 * 
 * This interface defines methods for reading different MySQL protocol
 * data types including integers, strings, and length-encoded values.
 */
interface PayloadReader
{
    /**
     * Reads a fixed-size integer from the payload.
     *
     * @param int $bytes Number of bytes to read
     * @return int|float The integer value
     */
    public function readFixedInteger(int $bytes): int|float;

    /**
     * Reads a length-encoded integer or null value.
     *
     * @return mixed The decoded integer value or null
     */
    public function readLengthEncodedIntegerOrNull(): mixed;

    /**
     * Reads a fixed-length string from the payload.
     *
     * @param int $length Number of bytes to read
     * @return string The string data
     */
    public function readFixedString(int $length): string;

    /**
     * Reads a length-encoded string or null value.
     *
     * @return string|null The decoded string or null
     */
    public function readLengthEncodedStringOrNull(): ?string;

    /**
     * Reads a null-terminated string from the payload.
     *
     * @return string The string data without the null terminator
     */
    public function readNullTerminatedString(): string;

    /**
     * Reads all remaining data in the current packet as a string.
     *
     * @return string The remaining packet data
     */
    public function readRestOfPacketString(): string;
}