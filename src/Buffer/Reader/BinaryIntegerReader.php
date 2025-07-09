<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Reader;

use InvalidArgumentException;
use function unpack;

/**
 * Reads binary integers of various sizes from binary data.
 * 
 * This class handles reading unsigned integers from 1 to 8 bytes
 * using little-endian byte ordering as required by the MySQL protocol.
 */
class BinaryIntegerReader
{
    /**
     * Reads a fixed-size unsigned integer from binary data.
     *
     * @param string $binary The binary data to read from
     * @param int $size Number of bytes to read (1-8)
     * @return int|float The unsigned integer value
     * @throws InvalidArgumentException If size is invalid or insufficient data
     */
    public function readFixed(string $binary, int $size): int|float
    {
        if ($size > 8) {
            throw new InvalidArgumentException('Cannot read integers above 8 bytes');
        }

        return match ($size) {
            1 => $this->readUnsigned1ByteInteger($binary),
            2 => $this->readUnsigned2ByteInteger($binary),
            3 => $this->readUnsigned3ByteInteger($binary),
            4 => $this->readUnsigned4ByteInteger($binary),
            8 => $this->readUnsigned8ByteInteger($binary),
            default => $this->readVariableSizeInteger($binary, $size)
        };
    }

    /**
     * Reads an unsigned 1-byte integer.
     *
     * @param string $binary Binary data
     * @return int The integer value
     * @throws InvalidArgumentException If insufficient data
     */
    private function readUnsigned1ByteInteger(string $binary): int
    {
        if (strlen($binary) < 1) {
            throw new InvalidArgumentException('Insufficient data for 1 byte integer');
        }

        $result = unpack('C', $binary);
        return $result[1];
    }

    /**
     * Reads an unsigned 2-byte integer.
     *
     * @param string $binary Binary data
     * @return int The integer value
     * @throws InvalidArgumentException If insufficient data
     */
    private function readUnsigned2ByteInteger(string $binary): int
    {
        if (strlen($binary) < 2) {
            throw new InvalidArgumentException('Insufficient data for 2 byte integer');
        }

        $result = unpack('v', $binary);
        return $result[1];
    }

    /**
     * Reads an unsigned 3-byte integer by padding to 4 bytes.
     *
     * @param string $binary Binary data
     * @return int The integer value
     * @throws InvalidArgumentException If insufficient data
     */
    private function readUnsigned3ByteInteger(string $binary): int
    {
        if (strlen($binary) < 3) {
            throw new InvalidArgumentException('Insufficient data for 3 byte integer');
        }

        return $this->readUnsigned4ByteInteger($binary . "\x00");
    }

    /**
     * Reads an unsigned 4-byte integer.
     *
     * @param string $binary Binary data
     * @return int The integer value
     * @throws InvalidArgumentException If insufficient data
     */
    private function readUnsigned4ByteInteger(string $binary): int
    {
        if (strlen($binary) < 4) {
            throw new InvalidArgumentException('Insufficient data for 4 byte integer');
        }

        $result = unpack('V', $binary);
        return $result[1];
    }

    /**
     * Reads an unsigned 8-byte integer.
     *
     * @param string $binary Binary data
     * @return int|float The integer value
     * @throws InvalidArgumentException If insufficient data
     */
    private function readUnsigned8ByteInteger(string $binary): int|float
    {
        if (strlen($binary) < 8) {
            throw new InvalidArgumentException('Insufficient data for 8 byte integer');
        }

        if (strlen($binary) > 8) {
            $binary = substr($binary, 0, 8);
        }

        return hexdec(bin2hex(strrev($binary)));
    }

    /**
     * Reads a variable-size integer by padding to 8 bytes.
     *
     * @param string $binary Binary data
     * @param int $size Number of bytes to read
     * @return int|float The integer value
     * @throws InvalidArgumentException If insufficient data
     */
    private function readVariableSizeInteger(string $binary, int $size): int|float
    {
        if (strlen($binary) < $size) {
            throw new InvalidArgumentException("Insufficient data for {$size} byte integer");
        }

        $paddedBinary = str_pad($binary, 8, "\x00");
        return hexdec(bin2hex(strrev($paddedBinary)));
    }
}