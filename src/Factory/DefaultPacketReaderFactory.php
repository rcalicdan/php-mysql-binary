<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Factory;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketReader;

class DefaultPacketReaderFactory
{
    public function createWithDefaultSettings(): UncompressedPacketReader
    {
        return new UncompressedPacketReader(
            new BinaryIntegerReader(),
            new ReadBuffer(),
            new BufferPayloadReaderFactory()
        );
    }
}
