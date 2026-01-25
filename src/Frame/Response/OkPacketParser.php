<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for the OK Packet.
 *
 * Expects the payload to start with 0x00.
 */
final readonly class OkPacketParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $payload->readFixedInteger(1);

        $affectedRows = $payload->readLengthEncodedIntegerOrNull() ?? 0;

        $lastInsertId = $payload->readLengthEncodedIntegerOrNull() ?? 0;

        $statusFlags = $payload->readFixedInteger(2);

        $warnings = $payload->readFixedInteger(2);

        $info = $payload->readRestOfPacketString();

        return new OkPacket(
            (int)$affectedRows,
            (int)$lastInsertId,
            (int)$statusFlags,
            (int)$warnings,
            $info,
            $sequenceNumber
        );
    }
}
