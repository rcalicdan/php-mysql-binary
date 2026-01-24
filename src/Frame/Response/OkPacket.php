<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL OK Packet.
 * 
 * Sent by the server to signal successful completion of a command.
 */
final readonly class OkPacket implements Frame
{
    public function __construct(
        public int $affectedRows,
        public int $lastInsertId,
        public int $statusFlags,
        public int $warnings,
        public string $info,
        public int $sequenceNumber 
    ) {}
}