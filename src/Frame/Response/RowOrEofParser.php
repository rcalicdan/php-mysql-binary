<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRowParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for EOF or Row packets.
 *
 * Determines if a packet is an EOF packet (0xFE with length < 9)
 * or a row packet, and parses accordingly.
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

        $parser = new TextRowParser($this->columnCount);

        return $parser->parse($payload, $length, $sequenceNumber);
    }
}
