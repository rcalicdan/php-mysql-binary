<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Packet;

use InvalidArgumentException;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BinaryWriter;

class UncompressedPacketWriter implements PacketWriter
{
    private const int MAX_PACKET_SIZE = 16777215;

    private BinaryWriter $binaryWriter;

    public function __construct(?BinaryWriter $binaryWriter = null)
    {
        $this->binaryWriter = $binaryWriter ?? new BinaryWriter();
    }

    public function write(string $payload, int $sequenceId): string
    {
        $length = \strlen($payload);

        if ($length > self::MAX_PACKET_SIZE) {
            throw new InvalidArgumentException(
                \sprintf('Payload size %d exceeds maximum packet size of %d', $length, self::MAX_PACKET_SIZE)
            );
        }

        $header = $this->binaryWriter->writeUInt24($length) .
                  $this->binaryWriter->writeUInt8($sequenceId);

        return $header . $payload;
    }
}
