<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Constants\PacketType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRowParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parses rows for COM_STMT_EXECUTE results, handling dynamic format detection.
 *
 * While Prepared Statements typically return Binary Rows, Stored Procedures invoked
 * via prepared statements return internal result sets in Text Row format.
 * This parser inspects the first byte of the first row to determine the format
 * (Text vs Binary) and persists that decision for the rest of the result set.
 */
class DynamicRowOrEofParser implements FrameParser
{
    private BinaryRowParser $binaryParser;

    /**
     * @var bool|null True if Text Protocol, False if Binary Protocol, Null if not yet detected.
     */
    private ?bool $isTextFormat = null;

    /**
     * @param ColumnDefinition[] $columns
     */
    public function __construct(
        private array $columns,
        ?bool $forceTextFormat = null
    ) {
        $this->binaryParser = new BinaryRowParser($columns);
        $this->isTextFormat = $forceTextFormat;
    }

    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $firstByte = (int) $payload->readFixedInteger(1);

        if ($firstByte === PacketType::EOF && $length < PacketType::EOF_MAX_LENGTH) {
            $warnings = $payload->readFixedInteger(2);
            $statusFlags = $payload->readFixedInteger(2);

            return new EofPacket((int) $warnings, (int) $statusFlags, $sequenceNumber);
        }

        // Check for ERR Packet — 0xFF already consumed, use parseWithFirstByte to avoid double-read
        if ($firstByte === PacketType::ERR) {
            return (new ErrPacketParser())->parseWithFirstByte($payload, $sequenceNumber);
        }

        if ($this->isTextFormat === null) {
            // Binary Protocol rows ALWAYS start with 0x00 (PacketType::OK).
            // Text Protocol rows start with a length-encoded integer (0x00-0xFA, 0xFC-0xFE).
            // While 0x00 is a collision (valid in both), in the context of COM_STMT_EXECUTE,
            // 0x00 is overwhelmingly likely to be the standard Binary Row header.
            // Non-0x00 bytes definitively indicate a Text Row (from a Stored Procedure).
            $this->isTextFormat = ($firstByte !== PacketType::OK);
        }

        if ($this->isTextFormat) {
            return $this->parseTextRow($payload, $firstByte);
        }

        // Binary Row: The 0x00 header was already read into $firstByte
        return $this->binaryParser->parseRemainingRow($payload);
    }

    /**
     * Parses a Text Row, using the pre-read first byte as the length of the first column.
     */
    private function parseTextRow(PayloadReader $payload, int $firstByte): TextRow
    {
        $values = [];

        $values[] = $payload->readLengthEncodedStringFromByte($firstByte);

        $count = \count($this->columns);
        for ($i = 1; $i < $count; $i++) {
            $values[] = $payload->readLengthEncodedStringOrNull();
        }

        return new TextRow($values);
    }
}