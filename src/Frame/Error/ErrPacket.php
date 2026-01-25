<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Error;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL ERR Packet.
 *
 * Sent by the server to signal that an error occurred.
 */
final readonly class ErrPacket implements Frame
{
    public function __construct(
        public int $errorCode,
        public string $sqlStateMarker,
        public string $sqlState,
        public string $errorMessage,
        public int $sequenceNumber
    ) {
    }
}
