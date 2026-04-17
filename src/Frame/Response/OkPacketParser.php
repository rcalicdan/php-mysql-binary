<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

final readonly class OkPacketParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $payload->readFixedInteger(1); // Consume the 0x00 header

        return $this->parseWithFirstByte($payload, $sequenceNumber);
    }

    public function parseWithFirstByte(PayloadReader $payload, int $sequenceNumber): Frame
    {
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
