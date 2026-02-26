<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Represents a prepared statement OK response packet in the MySQL binary protocol.
 *
 * This packet is sent by the server in response to a PREPARE command and contains
 * metadata about the prepared statement including the statement ID, column definitions,
 * and parameter definitions.
 */
final readonly class StmtPrepareOkPacket implements Frame
{
    public function __construct(
        public int $statementId,
        public int $numColumns,
        public int $numParams,
        public int $warningCount,
        public int $sequenceNumber
    ) {
    }
}
