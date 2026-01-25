<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class StmtPrepareOkPacketParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $payload->readFixedInteger(1);

        $statementId = $payload->readFixedInteger(4);

        $numColumns = $payload->readFixedInteger(2);

        $numParams = $payload->readFixedInteger(2);

        $payload->readFixedInteger(1);

        $warningCount = $payload->readFixedInteger(2);

        return new StmtPrepareOkPacket(
            (int)$statementId,
            (int)$numColumns,
            (int)$numParams,
            (int)$warningCount,
            $sequenceNumber
        );
    }
}
