<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Exception\InvalidBinaryDataException;
use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class BinaryRowParser implements FrameParser
{
    /**
     * @param ColumnDefinition[] $columns The column definitions for this result set.
     */
    public function __construct(
        private array $columns
    ) {}

    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $payload->readFixedInteger(1);

        $columnCount = \count($this->columns);
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

        if (!isset($nullBitmap[$byteIndex])) {
            return false; 
        }

        $byte = \ord($nullBitmap[$byteIndex]);
        $bit = 1 << ($bitPosition % 8);

        return ($byte & $bit) !== 0;
    }

    private function parseColumnValue(PayloadReader $reader, ColumnDefinition $column): mixed
    {
        return match ($column->type) {
            MysqlType::TINY => $reader->readFixedInteger(1),
            MysqlType::SHORT, MysqlType::YEAR => $reader->readFixedInteger(2),
            MysqlType::LONG, MysqlType::INT24 => $reader->readFixedInteger(4),
            MysqlType::LONGLONG => $reader->readFixedInteger(8),
            MysqlType::FLOAT => unpack('f', $reader->readFixedString(4))[1],
            MysqlType::DOUBLE => unpack('d', $reader->readFixedString(8))[1],
            MysqlType::TIMESTAMP, MysqlType::DATETIME, MysqlType::DATE => $this->parseDateTime($reader),
            MysqlType::TIME => $this->parseTime($reader),
            default => $reader->readLengthEncodedStringOrNull(),
        };
    }

    private function parseDateTime(PayloadReader $reader): ?string
    {
        $length = $reader->readFixedInteger(1);

        if ($length === 0) return null;

        $year = $reader->readFixedInteger(2);
        $month = $reader->readFixedInteger(1);
        $day = $reader->readFixedInteger(1);

        if ($length === 4) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $hour = $reader->readFixedInteger(1);
        $minute = $reader->readFixedInteger(1);
        $second = $reader->readFixedInteger(1);

        if ($length === 7) {
            return \sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
        }
        
        if ($length === 11) {
             $microsecond = $reader->readFixedInteger(4);
             return \sprintf('%04d-%02d-%02d %02d:%02d:%02d.%06d', $year, $month, $day, $hour, $minute, $second, $microsecond);
        }

        throw new InvalidBinaryDataException("Invalid DATETIME length: {$length}");
    }
    
    private function parseTime(PayloadReader $reader): ?string
    {
        $length = $reader->readFixedInteger(1);
        if ($length === 0) return null; 

        $isNegative = $reader->readFixedInteger(1);
        $days = $reader->readFixedInteger(4);
        $hours = $reader->readFixedInteger(1);
        $minutes = $reader->readFixedInteger(1);
        $seconds = $reader->readFixedInteger(1);

        $totalHours = ($days * 24) + $hours;
        $timeStr = \sprintf('%s%02d:%02d:%02d', $isNegative ? '-' : '', $totalHours, $minutes, $seconds);
        
        if ($length === 12) {
            $microseconds = $reader->readFixedInteger(4);
            $timeStr .= sprintf('.%06d', $microseconds);
        }
        
        return $timeStr;
    }
}