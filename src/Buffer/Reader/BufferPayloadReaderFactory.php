<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Reader;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;

/**
 * Factory for creating BufferPayloadReader instances.
 *
 * This factory class is responsible for creating BufferPayloadReader instances
 * with a given binary integer reader. If no binary integer reader is provided,
 * a default instance is used.
 */
class BufferPayloadReaderFactory
{
    private BinaryIntegerReader $binaryIntegerReader;

    public function __construct(?BinaryIntegerReader $binaryIntegerReader = null)
    {
        $this->binaryIntegerReader = $binaryIntegerReader ?? new BinaryIntegerReader();
    }

    /**
     * @param array<int, int> $unreadPacketLength
     */
    public function createFromBuffer(ReadBuffer $buffer, array &$unreadPacketLength): BufferPayloadReader
    {
        return new BufferPayloadReader($buffer, $unreadPacketLength, $this->binaryIntegerReader);
    }

    public function createFromString(string $data): BufferPayloadReader
    {
        $buffer = new ReadBuffer();
        $buffer->append($data);
        $unreadPacketLength = [\strlen($data)];

        return new BufferPayloadReader($buffer, $unreadPacketLength, $this->binaryIntegerReader);
    }
}
