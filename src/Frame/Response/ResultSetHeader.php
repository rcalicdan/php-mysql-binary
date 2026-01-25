<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a MySQL Result Set Header packet.
 *
 * This is the first packet of a result set response and contains
 * the number of columns that will follow. After this packet, the
 * server will send:
 *
 * 1. N × Column Definition packets (where N = columnCount)
 * 2. EOF packet (if not using CLIENT_DEPRECATE_EOF)
 * 3. M × Row Data packets (where M = number of rows)
 * 4. EOF packet to signal end of rows
 *
 * The column count is sent as a length-encoded integer.
 */
final readonly class ResultSetHeader implements Frame
{
    /**
     * @param int $columnCount Number of columns in the result set
     * @param int $sequenceNumber Packet sequence number
     */
    public function __construct(
        public int $columnCount,
        public int $sequenceNumber
    ) {
    }
}
