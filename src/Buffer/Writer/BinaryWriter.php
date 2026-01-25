<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Writer;

/**
 * Handles low-level binary packing for MySQL types.
 */
class BinaryWriter
{
    public function writeUInt8(int $value): string
    {
        return pack('C', $value);
    }

    public function writeUInt16(int $value): string
    {
        return pack('v', $value);
    }

    public function writeUInt24(int $value): string
    {
        return substr(pack('V', $value), 0, 3);
    }

    public function writeUInt32(int $value): string
    {
        return pack('V', $value);
    }

    public function writeUInt64(int $value): string
    {
        return pack('P', $value);
    }

    public function writeFloat(float $value): string
    {
        return pack('g', $value);
    }

    public function writeDouble(float $value): string
    {
        return pack('e', $value);
    }
}
