<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Reader;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;

class BufferPayloadReaderFactory
{
    private BinaryIntegerReader $binaryIntegerReader;

    public function __construct(?BinaryIntegerReader $binaryIntegerReader = null)
    {
        $this->binaryIntegerReader = $binaryIntegerReader ?? new BinaryIntegerReader();
    }

    public function createFromBuffer(ReadBuffer $buffer, array &$unreadPacketLength): BufferPayloadReader
    {
        return new BufferPayloadReader($buffer, $unreadPacketLength, $this->binaryIntegerReader);
    }

    public function createFromString(string $data): BufferPayloadReader
    {
        $buffer = new ReadBuffer();
        $buffer->append($data);
        $unreadPacketLength = [strlen($data)];
        return new BufferPayloadReader($buffer, $unreadPacketLength, $this->binaryIntegerReader);
    }
}
