<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * RowOrEofParser
 *
 * Parser for handling MySQL protocol row data or EOF (End of File) frames.
 * 
 * This class is responsible for parsing and interpreting binary protocol frames
 * that represent either a data row from a result set or an EOF packet that signals
 * the end of data transmission from the MySQL server.
 * 
 * Implements the FrameParser interface to provide consistent frame parsing behavior
 * within the MySQL binary protocol handling system.
 */
class RowOrEofParser implements FrameParser
{
    public function __construct(
        private int $columnCount
    ) {
    }

    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $firstByte = $payload->readFixedInteger(1);

        if ($firstByte === 0xFE && $length < 9) {
            $warnings = $payload->readFixedInteger(2);
            $statusFlags = $payload->readFixedInteger(2);

            return new EofPacket((int)$warnings, (int)$statusFlags, $sequenceNumber);
        }

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

        $values = [];

        $firstValue = $payload->readLengthEncodedStringFromByte((int)$firstByte);
        $values[] = $firstValue;

        for ($i = 1; $i < $this->columnCount; $i++) {
            $values[] = $payload->readLengthEncodedStringOrNull();
        }

        return new TextRow($values);
    }
}
