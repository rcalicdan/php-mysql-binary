<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Constants\StatusFlags;
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

    /**
     * Checks if the SERVER_MORE_RESULTS_EXISTS flag is set.
     */
    public function hasMoreResults(): bool
    {
        return ($this->statusFlags & StatusFlags::SERVER_MORE_RESULTS_EXISTS) !== 0;
    }
}
