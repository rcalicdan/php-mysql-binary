<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Writer;

/**
 * Handles low-level binary packing for MySQL types.
 */
class BinaryWriter
{
    /**
     * Writes an unsigned 1-byte integer.
     */
    public function writeUInt8(int $value): string
    {
        return pack('C', $value);
    }

    /**
     * Writes an unsigned 2-byte integer (Little Endian).
     */
    public function writeUInt16(int $value): string
    {
        return pack('v', $value);
    }

    /**
     * Writes an unsigned 3-byte integer (Little Endian).
     * Used for Packet Length headers and LENENC 0xFD values.
     */
    public function writeUInt24(int $value): string
    {
        return substr(pack('V', $value), 0, 3);
    }

    /**
     * Writes an unsigned 4-byte integer (Little Endian).
     */
    public function writeUInt32(int $value): string
    {
        return pack('V', $value);
    }

    /**
     * Writes an unsigned 8-byte integer (Little Endian).
     */
    public function writeUInt64(int $value): string
    {
        return pack('P', $value);
    }

    /**
     * Writes a float (4 bytes, Little Endian).
     */
    public function writeFloat(float $value): string
    {
        // 'g' code ensures little-endian float regardless of machine architecture
        return pack('g', $value);
    }

    /**
     * Writes a double (8 bytes, Little Endian).
     */
    public function writeDouble(float $value): string
    {
        // 'e' code ensures little-endian double regardless of machine architecture
        return pack('e', $value);
    }
}