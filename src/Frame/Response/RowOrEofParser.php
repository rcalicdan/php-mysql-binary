<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

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

        $firstValue = $this->readLengthEncodedStringFromByte($payload, (int)$firstByte);
        $values[] = $firstValue;

        for ($i = 1; $i < $this->columnCount; $i++) {
            $values[] = $payload->readLengthEncodedStringOrNull();
        }

        return new TextRow($values);
    }

    private function readLengthEncodedStringFromByte(PayloadReader $payload, int $firstByte): ?string
    {
        if ($firstByte === 0xFB) {
            return null;
        }

        if ($firstByte < 0xFB) {
            return $payload->readFixedString($firstByte);
        }

        if ($firstByte === 0xFC) {
            $length = $payload->readFixedInteger(2);

            return $payload->readFixedString((int)$length);
        }

        if ($firstByte === 0xFD) {
            $length = $payload->readFixedInteger(3);

            return $payload->readFixedString((int)$length);
        }

        if ($firstByte === 0xFE) {
            $length = $payload->readFixedInteger(8);

            return $payload->readFixedString((int)$length);
        }

        throw new \RuntimeException(
            \sprintf('Invalid length-encoded string marker: 0x%02X', $firstByte)
        );
    }
}
