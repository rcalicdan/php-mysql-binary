<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parses the response frame for a prepared statement preparation request.
 *
 * This parser implements the FrameParser interface and is responsible for
 * parsing the server's response when a prepared statement is prepared using
 * the STMT_PREPARE command in the MySQL binary protocol.
 *
 * The parser handles the extraction and interpretation of metadata from the
 * preparation response, including the statement ID, number of columns,
 * parameters, and warnings.
 */
class StmtPrepareResponseParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $firstByte = $payload->readFixedInteger(1);

        if ($firstByte === 0xFF) {
            $errorCode = $payload->readFixedInteger(2);
            $sqlStateMarker = $payload->readFixedString(1);
            $sqlState = $payload->readFixedString(5);
            $errorMessage = $payload->readRestOfPacketString();

            return new ErrPacket(
                (int)$errorCode,
                $sqlStateMarker,
                $sqlState,
                $errorMessage,
                $sequenceNumber
            );
        }

        if ($firstByte === 0x00) {
            $statementId = $payload->readFixedInteger(4);
            $numColumns = $payload->readFixedInteger(2);
            $numParams = $payload->readFixedInteger(2);
            $payload->readFixedInteger(1); // Reserved filler
            $warningCount = $payload->readFixedInteger(2);

            return new StmtPrepareOkPacket(
                (int)$statementId,
                (int)$numColumns,
                (int)$numParams,
                (int)$warningCount,
                $sequenceNumber
            );
        }

        throw new \RuntimeException(\sprintf('Unexpected packet type in prepare response: 0x%02X', $firstByte));
    }
}
