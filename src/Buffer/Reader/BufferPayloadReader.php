<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Reader;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Reads various data types from a MySQL binary protocol payload buffer.
 * 
 * This class implements the PayloadReader interface to provide methods
 * for reading different MySQL protocol data types including length-encoded
 * integers and strings, fixed-size data, and null-terminated strings.
 */
class BufferPayloadReader implements PayloadReader
{
    private const LENGTH_MARKERS = [
        0xfc => 2,
        0xfd => 3,
        0xfe => 8
    ];

    private const NULL_MARKER = 0xfb;

    private ReadBuffer $buffer;
    private BinaryIntegerReader $integerReader;
    private array $unreadPacketLength;

    /**
     * Creates a new buffer payload reader.
     *
     * @param ReadBuffer $buffer The buffer to read from
     * @param array $unreadPacketLength Array of unread packet lengths
     * @param BinaryIntegerReader $integerReader Integer reader instance
     */
    public function __construct(ReadBuffer $buffer, array $unreadPacketLength, BinaryIntegerReader $integerReader)
    {
        $this->buffer = $buffer;
        $this->integerReader = $integerReader;
        $this->unreadPacketLength = $unreadPacketLength;
    }

    /**
     * Reads a fixed-size integer from the buffer.
     *
     * @param int $bytes Number of bytes to read
     * @return int|float The integer value
     */
    public function readFixedInteger(int $bytes): int|float
    {
        return $this->integerReader->readFixed(
            $this->buffer->read($bytes),
            $bytes
        );
    }

    /**
     * Reads a length-encoded integer or null value.
     *
     * @return float|int|null The decoded value or null
     * @throws InvalidBinaryDataException If the data is malformed
     */
    public function readLengthEncodedIntegerOrNull(): float|int|null
    {
        $firstByte = $this->readFixedInteger(1);

        if ($firstByte < 251) {
            return $firstByte;
        }

        if ($firstByte === self::NULL_MARKER) {
            return null;
        }

        if (isset(self::LENGTH_MARKERS[$firstByte])) {
            return $this->readFixedInteger(self::LENGTH_MARKERS[$firstByte]);
        }

        throw new InvalidBinaryDataException();
    }

    /**
     * Reads a fixed-length string from the buffer.
     *
     * @param int $length Number of bytes to read
     * @return string The string data
     */
    public function readFixedString(int $length): string
    {
        return $this->buffer->read($length);
    }

    /**
     * Reads a length-encoded string or null value.
     *
     * @return string|null The decoded string or null
     */
    public function readLengthEncodedStringOrNull(): ?string
    {
        $length = $this->readLengthEncodedIntegerOrNull();

        if ($length === null) {
            return null;
        }

        return $this->buffer->read($length);
    }

    /**
     * Reads a null-terminated string from the buffer.
     *
     * @return string The string data without the null terminator
     * @throws IncompleteBufferException If no null terminator is found
     */
    public function readNullTerminatedString(): string
    {
        $nullPosition = $this->buffer->scan("\x00");

        if ($nullPosition === -1) {
            throw new IncompleteBufferException();
        }

        $string = $this->buffer->read($nullPosition - 1);
        $this->buffer->read(1);

        return $string;
    }

    /**
     * Reads the remaining data in the current packet.
     *
     * @return string The remaining packet data
     */
    public function readRestOfPacketString(): string
    {
        return $this->buffer->read(
            $this->remainingPacketLengthToRead()
        );
    }

    /**
     * Calculates the remaining length to read in the current packet.
     *
     * @return int Number of bytes remaining in the current packet
     */
    private function remainingPacketLengthToRead(): int
    {
        $currentBufferPosition = $this->buffer->currentPosition();
        $currentPacketIndex = 0;

        while ($this->unreadPacketLength[$currentPacketIndex] <= $currentBufferPosition) {
            $currentBufferPosition -= $this->unreadPacketLength[$currentPacketIndex];
            $currentPacketIndex++;
        }

        return $this->unreadPacketLength[$currentPacketIndex] - $currentBufferPosition;
    }
}