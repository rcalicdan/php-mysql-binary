<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

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
