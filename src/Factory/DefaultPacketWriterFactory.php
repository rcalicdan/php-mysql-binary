<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Factory;

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BinaryWriter;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

class DefaultPacketWriterFactory
{
    public function createWithDefaultSettings(): UncompressedPacketWriter
    {
        return new UncompressedPacketWriter(
            new BinaryWriter()
        );
    }
}