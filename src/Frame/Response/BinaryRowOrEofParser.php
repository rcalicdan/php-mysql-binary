<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Constants\ColumnFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\DataTypeBounds;
use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Constants\PacketType;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRow;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class BinaryRowOrEofParser implements FrameParser
{
    /**
     * @param ColumnDefinition[] $columns The column definitions for this result set.
     */
    public function __construct(private array $columns)
    {
    }

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

            return new ErrPacket(
                (int)$errorCode,
                $sqlStateMarker,
                $sqlState,
                $errorMessage,
                $sequenceNumber
            );
        }

        // BinaryRows always begin with a 0x00 header byte (which is also the OK packet marker)
        if ($firstByte !== PacketType::OK) {
            throw new InvalidBinaryDataException('Expected 0x00 for BinaryRow, got ' . $firstByte);
        }

        return $this->parseRemainingRow($payload);
    }

    /**
     * Parses a BinaryRow assuming the 0x00 packet header has already been read.
     * Useful when switching states via MetadataOmittedRowMarker.
     */
    public function parseRemainingRow(PayloadReader $payload): BinaryRow
    {
        $columnCount = \count($this->columns);
        // The null bitmap for binary rows is offset by 2 bits
        $nullBitmapBytes = (int) floor(($columnCount + 7 + 2) / 8);
        $nullBitmap = $payload->readFixedString($nullBitmapBytes);

        $values = [];
        foreach ($this->columns as $i => $column) {
            if ($this->isColumnNull($nullBitmap, $i)) {
                $values[] = null;
                continue;
            }

            $values[] = $this->parseColumnValue($payload, $column);
        }

        return new BinaryRow($values);
    }

    private function isColumnNull(string $nullBitmap, int $columnIndex): bool
    {
        $bitPosition = $columnIndex + 2;
        $byteIndex = (int) floor($bitPosition / 8);

        if (! isset($nullBitmap[$byteIndex])) {
            return false;
        }

        $byte = \ord($nullBitmap[$byteIndex]);
        $bit = 1 << ($bitPosition % 8);

        return ($byte & $bit) !== 0;
    }

    private function parseColumnValue(PayloadReader $reader, ColumnDefinition $column): mixed
    {
        if ($column->type === MysqlType::NEWDECIMAL) {
            return $reader->readLengthEncodedStringOrNull();
        }

        if ($column->type === MysqlType::LONGLONG) {
            return $this->readLongLong($reader, $column);
        }

        $val = match ($column->type) {
            MysqlType::TINY => $reader->readFixedInteger(1),
            MysqlType::SHORT, MysqlType::YEAR => $reader->readFixedInteger(2),
            MysqlType::LONG, MysqlType::INT24 => $reader->readFixedInteger(4),
            MysqlType::FLOAT => $this->unpackFloat($reader->readFixedString(4)),
            MysqlType::DOUBLE => $this->unpackDouble($reader->readFixedString(8)),
            MysqlType::TIMESTAMP, MysqlType::DATETIME, MysqlType::DATE => $this->parseDateTime($reader, $column->type),
            MysqlType::TIME => $this->parseTime($reader),
            default => $reader->readLengthEncodedStringOrNull(),
        };

        if (
            \in_array($column->type, [MysqlType::TINY, MysqlType::SHORT, MysqlType::INT24, MysqlType::LONG], true)
            && ($column->flags & ColumnFlags::UNSIGNED_FLAG) === 0
        ) {
            $val = is_numeric($val) ? (int)$val : 0;
            $val = match ($column->type) {
                MysqlType::TINY => $val >= DataTypeBounds::TINYINT_SIGN_BIT ? $val - DataTypeBounds::TINYINT_RANGE : $val,
                MysqlType::SHORT => $val >= DataTypeBounds::SMALLINT_SIGN_BIT ? $val - DataTypeBounds::SMALLINT_RANGE : $val,
                MysqlType::INT24 => $val >= DataTypeBounds::MEDIUMINT_SIGN_BIT ? $val - DataTypeBounds::MEDIUMINT_RANGE : $val,
                MysqlType::LONG => $val >= DataTypeBounds::INT_SIGN_BIT ? $val - DataTypeBounds::INT_RANGE : $val,
            };
        }

        return $val;
    }

    private function readLongLong(PayloadReader $reader, ColumnDefinition $column): int|string
    {
        $bytes = $reader->readFixedString(8);

        if (($column->flags & ColumnFlags::UNSIGNED_FLAG) !== 0) {
            $val = hexdec(bin2hex(strrev($bytes)));
            if (\is_float($val)) {
                return number_format($val, 0, '', '');
            }
            return $val;
        }

        $parts = unpack('V2', $bytes);
        if ($parts === false) {
            $parts = [1 => 0, 2 => 0];
        }

        $upper = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : 0;
        $lower = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;

        return ($upper << 32) | $lower;
    }

    private function unpackFloat(string $data): float
    {
        $result = unpack('g', $data);
        if ($result === false) {
            throw new InvalidBinaryDataException('Failed to unpack float value');
        }
        return $result[1];
    }

    private function unpackDouble(string $data): float
    {
        $result = unpack('e', $data);
        if ($result === false) {
            throw new InvalidBinaryDataException('Failed to unpack double value');
        }
        return $result[1];
    }

    private function parseDateTime(PayloadReader $reader, int $type): ?string
    {
        $length = $reader->readFixedInteger(1);

        if ($length === 0) {
            if ($type === MysqlType::DATE) {
                return '0000-00-00';
            }
            return '0000-00-00 00:00:00';
        }

        $year = $reader->readFixedInteger(2);
        $month = $reader->readFixedInteger(1);
        $day = $reader->readFixedInteger(1);

        $dateStr = \sprintf('%04d-%02d-%02d', $year, $month, $day);

        if ($type === MysqlType::DATE) {
            return $dateStr;
        }

        if ($length >= 7) {
            $hour = $reader->readFixedInteger(1);
            $minute = $reader->readFixedInteger(1);
            $second = $reader->readFixedInteger(1);

            $dateStr .= \sprintf(' %02d:%02d:%02d', $hour, $minute, $second);

            if ($length === 11) {
                $microsecond = $reader->readFixedInteger(4);
                $dateStr .= \sprintf('.%06d', $microsecond);
            }
        } else {
            $dateStr .= ' 00:00:00';
        }

        return $dateStr;
    }

    private function parseTime(PayloadReader $reader): ?string
    {
        $length = $reader->readFixedInteger(1);
        if ($length === 0) {
            return '00:00:00';
        }

        $isNegative = $reader->readFixedInteger(1);
        $days = $reader->readFixedInteger(4);
        $hours = $reader->readFixedInteger(1);
        $minutes = $reader->readFixedInteger(1);
        $seconds = $reader->readFixedInteger(1);

        $totalHours = ($days * 24) + $hours;
        $timeStr = \sprintf('%s%02d:%02d:%02d', $isNegative ? '-' : '', $totalHours, $minutes, $seconds);

        if ($length === 12) {
            $microseconds = $reader->readFixedInteger(4);
            $timeStr .= \sprintf('.%06d', $microseconds);
        }

        return $timeStr;
    }
}