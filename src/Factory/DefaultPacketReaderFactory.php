<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Factory;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketReader;

/**
 * Factory for creating packet readers with default configuration.
 * 
 * This factory provides a convenient way to create fully configured
 * packet readers with sensible defaults for most use cases.
 */
class DefaultPacketReaderFactory
{
    /**
     * Creates an UncompressedPacketReader with default settings.
     *
     * @return UncompressedPacketReader A configured packet reader instance
     */
    public function createWithDefaultSettings(): UncompressedPacketReader
    {
        return new UncompressedPacketReader(
            new BinaryIntegerReader(),
            new ReadBuffer(),
            new BufferPayloadReaderFactory()
        );
    }
}