<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Reader;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class BufferPayloadReader implements PayloadReader
{
    private const array LENGTH_MARKERS = [
        0xfc => 2,
        0xfd => 3,
        0xfe => 8,
    ];
    private const NULL_MARKER = 0xfb;
    private ReadBuffer $buffer;
    private BinaryIntegerReader $integerReader;

    /**
     *  @var array<int, int>
     */
    private array $unreadPacketLength;

    /**
     * @param array<int, int> $unreadPacketLength
     */
    public function __construct(ReadBuffer $buffer, array &$unreadPacketLength, BinaryIntegerReader $integerReader)
    {
        $this->buffer = $buffer;
        $this->integerReader = $integerReader;
        $this->unreadPacketLength = &$unreadPacketLength;
    }

    public function readFixedInteger(int $bytes): int|float
    {
        return $this->integerReader->readFixed(
            $this->buffer->read($bytes),
            $bytes
        );
    }

    public function readLengthEncodedIntegerOrNull(): float|int|null
    {
        $firstByte = $this->readFixedInteger(1);

        return $this->readLengthEncodedIntegerFromByte((int) $firstByte);
    }

    public function readLengthEncodedIntegerFromByte(int $firstByte): int|float|null
    {
        if ($firstByte < 251) {
            return $firstByte;
        }

        if ($firstByte === self::NULL_MARKER) {
            return null;
        }

        if (! isset(self::LENGTH_MARKERS[$firstByte])) {
            throw new InvalidBinaryDataException(\sprintf('Invalid length-encoded integer marker: 0x%02X', $firstByte));
        }

        return $this->readFixedInteger(self::LENGTH_MARKERS[$firstByte]);
    }

    public function readFixedString(int $length): string
    {
        return $this->buffer->read($length);
    }

    public function readLengthEncodedStringOrNull(): ?string
    {
        $length = $this->readLengthEncodedIntegerOrNull();
        if ($length === null) {
            return null;
        }

        return $this->buffer->read((int) $length);
    }

    public function readLengthEncodedStringFromByte(int $firstByte): ?string
    {
        $length = $this->readLengthEncodedIntegerFromByte($firstByte);

        if ($length === null) {
            return null;
        }

        return $this->buffer->read((int) $length);
    }

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

    public function readRestOfPacketString(): string
    {
        return $this->buffer->read(
            $this->remainingPacketLengthToRead()
        );
    }

    private function remainingPacketLengthToRead(): int
    {
        $currentBufferPosition = $this->buffer->currentPosition();
        $currentPacketIndex = 0;
        if (empty($this->unreadPacketLength)) {
            return 0;
        }
        while (isset($this->unreadPacketLength[$currentPacketIndex]) && $this->unreadPacketLength[$currentPacketIndex] <= $currentBufferPosition) {
            $currentBufferPosition -= $this->unreadPacketLength[$currentPacketIndex];
            $currentPacketIndex++;
        }
        if (! isset($this->unreadPacketLength[$currentPacketIndex])) {
            return 0;
        }

        return $this->unreadPacketLength[$currentPacketIndex] - $currentBufferPosition;
    }
}
