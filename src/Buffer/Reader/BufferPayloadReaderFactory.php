<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Reader;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;

/**
 * Factory for creating BufferPayloadReader instances.
 * 
 * This factory provides convenient methods for creating payload readers
 * from various data sources while managing the required dependencies.
 */
class BufferPayloadReaderFactory
{
    private BinaryIntegerReader $binaryIntegerReader;

    /**
     * Creates a new factory instance.
     *
     * @param BinaryIntegerReader|null $binaryIntegerReader Optional custom integer reader
     */
    public function __construct(?BinaryIntegerReader $binaryIntegerReader = null)
    {
        $this->binaryIntegerReader = $binaryIntegerReader ?? new BinaryIntegerReader();
    }

    /**
     * Creates a payload reader from a ReadBuffer instance.
     *
     * @param ReadBuffer $buffer The buffer to read from
     * @param array $unreadPacketLength Array of unread packet lengths
     * @return BufferPayloadReader The configured payload reader
     */
    public function createFromBuffer(ReadBuffer $buffer, array $unreadPacketLength): BufferPayloadReader
    {
        return new BufferPayloadReader($buffer, $unreadPacketLength, $this->binaryIntegerReader);
    }

    /**
     * Creates a payload reader from a string of binary data.
     *
     * @param string $data The binary data to read from
     * @return BufferPayloadReader The configured payload reader
     */
    public function createFromString(string $data): BufferPayloadReader
    {
        $buffer = new ReadBuffer();
        $buffer->append($data);
        return $this->createFromBuffer($buffer, [strlen($data)]);
    }
}