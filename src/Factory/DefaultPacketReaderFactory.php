<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Factory;

use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Packet\CompressedPacketReader;
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

    public function createCompressed(): CompressedPacketReader
    {
        $binaryReader = new BinaryIntegerReader();
        $payloadReaderFactory = new BufferPayloadReaderFactory($binaryReader);

        $innerReader = new UncompressedPacketReader(
            $binaryReader,
            new ReadBuffer(),
            $payloadReaderFactory
        );

        return new CompressedPacketReader(
            $binaryReader,
            new ReadBuffer(),
            $innerReader
        );
    }
}
