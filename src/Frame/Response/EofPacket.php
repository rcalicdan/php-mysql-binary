<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL EOF Packet.
 *
 * Sent to signal the end of a result set or column definitions.
 */
final readonly class EofPacket implements Frame
{
    public function __construct(
        public int $warnings,
        public int $statusFlags,
        public int $sequenceNumber
    ) {
    }
}
