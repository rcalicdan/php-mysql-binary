<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Result;

use Rcalicdan\MySQLBinaryProtocol\Constants\ColumnFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\DataTypeBounds;
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
    ) {
    }

    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame
    {
        $payload->readFixedInteger(1);

        return $this->parseRemainingRow($payload);
    }

    /**
     * Parses the binary row data assuming the 0x00 packet header has already been consumed.
     */
    public function parseRemainingRow(PayloadReader $payload): BinaryRow
    {
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

        if (! isset($nullBitmap[$byteIndex])) {
            return false;
        }

        $byte = \ord($nullBitmap[$byteIndex]);
        $bit = 1 << ($bitPosition % 8);

        return ($byte & $bit) !== 0;
    }

    private function parseColumnValue(PayloadReader $reader, ColumnDefinition $column): mixed
    {
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

        // Handle sign conversion for standard integer types
        if (
            \in_array($column->type, [MysqlType::TINY, MysqlType::SHORT, MysqlType::INT24, MysqlType::LONG], true)
            && ($column->flags & ColumnFlags::UNSIGNED_FLAG) === 0
        ) {
            $val = (int) $val;
            $val = match ($column->type) {
                MysqlType::TINY  => $val >= DataTypeBounds::TINYINT_SIGN_BIT    ? $val - DataTypeBounds::TINYINT_RANGE    : $val,
                MysqlType::SHORT => $val >= DataTypeBounds::SMALLINT_SIGN_BIT   ? $val - DataTypeBounds::SMALLINT_RANGE   : $val,
                MysqlType::INT24 => $val >= DataTypeBounds::MEDIUMINT_SIGN_BIT  ? $val - DataTypeBounds::MEDIUMINT_RANGE  : $val,
                MysqlType::LONG  => $val >= DataTypeBounds::INT_SIGN_BIT        ? $val - DataTypeBounds::INT_RANGE        : $val,
            };
        }

        return $val;
    }

    /**
     * Reads an 8-byte integer.
     * Prioritizes native 64-bit integers on 64-bit PHP to return 'int' type.
     * Falls back to string (BCMath/sprintf) only for huge unsigned values or 32-bit systems.
     */
    private function readLongLong(PayloadReader $reader, ColumnDefinition $column): int|string
    {
        $bytes = $reader->readFixedString(8);
        $parts = unpack('V2', $bytes);

        if ($parts === false) {
            $parts = [1 => 0, 2 => 0];
        }

        $lower = (int) $parts[1];
        $upper = (int) $parts[2];

        if ($lower < 0) {
            $lower += 4294967296;
        }
        if ($upper < 0) {
            $upper += 4294967296;
        }

        $lowerStr = (string) $lower;
        $upperStr = (string) $upper;

        $isUnsigned = ($column->flags & ColumnFlags::UNSIGNED_FLAG) !== 0;

        if (PHP_INT_SIZE === 8) {
            if ($isUnsigned) {
                if ($upper < 0x80000000) {
                    return ($upper << 32) | $lower;
                }

                if (\extension_loaded('bcmath')) {
                    return bcadd(bcmul($upperStr, '4294967296'), $lowerStr);
                }

                return \sprintf('%.0f', ($upper * 4294967296) + $lower);
            } else {
                return ($upper << 32) | $lower;
            }
        }

        $isNegative = ($upper & 0x80000000) !== 0;

        if ($isUnsigned) {
            if (\extension_loaded('bcmath')) {
                return bcadd(bcmul($upperStr, '4294967296'), $lowerStr);
            }

            return \sprintf('%.0f', ($upper * 4294967296) + $lower);
        }

        if ($isNegative) {
            if (\extension_loaded('bcmath')) {
                $unsignedVal = bcadd(bcmul($upperStr, '4294967296'), $lowerStr);

                return bcsub($unsignedVal, '18446744073709551616');
            }

            return \sprintf('%.0f', (($upper * 4294967296) + $lower) - 18446744073709551616);
        }

        if (\extension_loaded('bcmath')) {
            return bcadd(bcmul($upperStr, '4294967296'), $lowerStr);
        }

        return \sprintf('%.0f', ($upper * 4294967296) + $lower);
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

    private function parseDateTime(PayloadReader $reader, int $columnType): string
    {
        $length = $reader->readFixedInteger(1);

        if ($length === 0) {
            return $columnType === MysqlType::DATE ? '0000-00-00' : '0000-00-00 00:00:00';
        }

        $year = $reader->readFixedInteger(2);
        $month = $reader->readFixedInteger(1);
        $day = $reader->readFixedInteger(1);

        $dateStr = \sprintf('%04d-%02d-%02d', $year, $month, $day);

        if ($columnType === MysqlType::DATE) {
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

    private function parseTime(PayloadReader $reader): string
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