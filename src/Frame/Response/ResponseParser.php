<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for MySQL command response packets.
 *
 * This is a routing parser that determines response type from the first byte
 * and parses accordingly. For standalone parsing of specific packet types,
 * use OkPacketParser or ErrPacketParser directly.
 */
class ResponseParser implements ResponseParserInterface
{
    /**
     * {@inheritdoc}
     */
    public function parseResponse(PayloadReader $payload, int $length, int $sequence): Frame
    {
        $firstByte = $payload->readFixedInteger(1);

        return match ($firstByte) {
            0x00 => $this->parseOkPacket($payload, $sequence),
            0xFF => $this->parseErrPacket($payload, $sequence),
            default => $this->parseResultSetHeader($payload, $length, $sequence, (int)$firstByte),
        };
    }

    /**
     * Parses an OK packet (first byte 0x00 already consumed).
     */
    private function parseOkPacket(PayloadReader $payload, int $sequence): OkPacket
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
            $sequence
        );
    }

    /**
     * Parses an ERR packet (first byte 0xFF already consumed).
     */
    private function parseErrPacket(PayloadReader $payload, int $sequence): ErrPacket
    {
        $errorCode = $payload->readFixedInteger(2);
        $sqlStateMarker = $payload->readFixedString(1);
        $sqlState = $payload->readFixedString(5);
        $errorMessage = $payload->readRestOfPacketString();

        return new ErrPacket(
            (int)$errorCode,
            $sqlStateMarker,
            $sqlState,
            $errorMessage,
            $sequence
        );
    }

    /**
     * Parses result set header (first byte already consumed as column count).
     */
    private function parseResultSetHeader(
        PayloadReader $payload,
        int $length,
        int $sequence,
        int $firstByte
    ): ResultSetHeader {
        $columnCount = $this->decodeLengthEncodedInteger($payload, $length, $firstByte);

        return new ResultSetHeader($columnCount, $sequence);
    }

    private function decodeLengthEncodedInteger(
        PayloadReader $payload,
        int $packetLength,
        int $firstByte
    ): int {
        if ($firstByte < 251) {
            return $firstByte;
        }

        if ($firstByte === 0xfb) {
            throw new \RuntimeException(
                'Unexpected NULL (0xFB) in result set header'
            );
        }

        if ($firstByte === 0xfc) {
            return (int)$payload->readFixedInteger(2);
        }

        if ($firstByte === 0xfd) {
            return (int)$payload->readFixedInteger(3);
        }

        if ($firstByte === 0xfe) {
            if ($packetLength < 9) {
                throw new \RuntimeException(
                    'Unexpected EOF packet when expecting result set header'
                );
            }

            return (int)$payload->readFixedInteger(8);
        }

        throw new \RuntimeException(
            \sprintf('Invalid length-encoded integer marker: 0x%02X', $firstByte)
        );
    }
}
