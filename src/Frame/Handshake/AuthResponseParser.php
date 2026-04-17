<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Handshake;

use Rcalicdan\MySQLBinaryProtocol\Constants\AuthPacketType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacketParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacketParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parses packets received from the server after the client sends the HandshakeResponse41.
 */
class AuthResponseParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $firstByte = $payload->readFixedInteger(1);

        if ($firstByte === AuthPacketType::OK) {
            return (new OkPacketParser())->parseWithFirstByte($payload, $sequenceNumber);
        }

        if ($firstByte === AuthPacketType::ERR) {
            return (new ErrPacketParser())->parseWithFirstByte($payload, $sequenceNumber);
        }

        if ($firstByte === AuthPacketType::AUTH_SWITCH_REQUEST) {
            $pluginName = $payload->readNullTerminatedString();
            $authData = $payload->readRestOfPacketString();

            return new AuthSwitchRequest($pluginName, $authData, $sequenceNumber);
        }

        if ($firstByte === AuthPacketType::AUTH_MORE_DATA) {
            $data = $payload->readRestOfPacketString();

            return new AuthMoreData($data, $sequenceNumber);
        }

        throw new \RuntimeException(\sprintf('Unexpected packet type during authentication phase: 0x%02X', $firstByte));
    }
}
