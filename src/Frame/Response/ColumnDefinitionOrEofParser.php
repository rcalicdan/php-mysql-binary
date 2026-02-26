<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Constants\LengthEncodedType;
use Rcalicdan\MySQLBinaryProtocol\Constants\PacketType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for handling column definition or EOF (End of File) frames in MySQL binary protocol.
 * 
 * This class implements the FrameParser interface and is responsible for parsing incoming
 * frames to determine whether they represent column definition metadata or an EOF packet
 * that signals the end of a result set or statement execution.
 * 
 * The parser distinguishes between column definition frames and EOF frames based on the
 * frame header and content, providing appropriate parsing logic for each frame type.
 */
class ColumnDefinitionOrEofParser implements FrameParser
{
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $firstByte = $payload->readFixedInteger(1);

        if ($firstByte === PacketType::EOF && $length < PacketType::EOF_MAX_LENGTH) {
            $warnings = $payload->readFixedInteger(2);
            $statusFlags = $payload->readFixedInteger(2);

            return new EofPacket((int)$warnings, (int)$statusFlags, $sequenceNumber);
        }

        if ($firstByte === PacketType::ERR) {
            $errorCode = $payload->readFixedInteger(2);
            $sqlStateMarker = $payload->readFixedString(1);
            $sqlState = $payload->readFixedString(5);
            $errorMessage = $payload->readRestOfPacketString();

            return new ErrPacket((int)$errorCode, $sqlStateMarker, $sqlState, $errorMessage, $sequenceNumber);
        }

        // In COM_STMT_EXECUTE, if metadata is omitted, the server skips straight to BinaryRows.
        // BinaryRows always start with 0x00. A normal ColumnDefinition starts with the catalog length.
        if ($firstByte === PacketType::OK) {
            return new MetadataOmittedRowMarker();
        }

        $catalog = $this->readLengthEncodedStringFromByte($payload, (int)$firstByte) ?? '';
        $schema = $payload->readLengthEncodedStringOrNull() ?? '';
        $table = $payload->readLengthEncodedStringOrNull() ?? '';
        $orgTable = $payload->readLengthEncodedStringOrNull() ?? '';
        $name = $payload->readLengthEncodedStringOrNull() ?? '';
        $orgName = $payload->readLengthEncodedStringOrNull() ?? '';

        $payload->readLengthEncodedIntegerOrNull(); // Length of fixed-length fields

        $charset = $payload->readFixedInteger(2);
        $columnLength = $payload->readFixedInteger(4);
        $type = $payload->readFixedInteger(1);
        $flags = $payload->readFixedInteger(2);
        $decimals = $payload->readFixedInteger(1);

        $payload->readFixedInteger(2); // Filler

        return new ColumnDefinition(
            $catalog,
            $schema,
            $table,
            $orgTable,
            $name,
            $orgName,
            (int) $charset,
            (int) $columnLength,
            (int) $type,
            (int) $flags,
            (int) $decimals
        );
    }

    private function readLengthEncodedStringFromByte(PayloadReader $payload, int $firstByte): ?string
    {
        if ($firstByte === LengthEncodedType::NULL_MARKER) {
            return null;
        }

        if ($firstByte < LengthEncodedType::NULL_MARKER) {
            return $payload->readFixedString($firstByte);
        }

        if ($firstByte === LengthEncodedType::INT16_LENGTH) {
            return $payload->readFixedString((int)$payload->readFixedInteger(2));
        }

        if ($firstByte === LengthEncodedType::INT24_LENGTH) {
            return $payload->readFixedString((int)$payload->readFixedInteger(3));
        }

        if ($firstByte === LengthEncodedType::INT64_LENGTH) {
            return $payload->readFixedString((int)$payload->readFixedInteger(8));
        }

        throw new \RuntimeException(\sprintf('Invalid length-encoded string marker: 0x%02X', $firstByte));
    }
}
