<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parses a Statement Prepare OK packet response from the MySQL binary protocol.
 *
 * This parser is responsible for handling and extracting data from OK packets
 * that are sent in response to a PREPARE statement. It implements the FrameParser
 * interface to provide a standardized way of parsing MySQL protocol frames.
 */
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
