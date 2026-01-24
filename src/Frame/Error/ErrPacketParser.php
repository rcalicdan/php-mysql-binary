<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Error;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for the ERR Packet.
 * 
 * Expects the payload to start with 0xFF.
 */
class ErrPacketParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $payload->readFixedInteger(1); 

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
}