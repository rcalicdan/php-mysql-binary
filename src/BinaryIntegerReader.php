<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol;

use function unpack;

/**
 * @internal
 */
class BinaryIntegerReader
{
    public function readFixed(string $binary, int $size): int|float
    {
        if ($size > 8) {
            throw new \InvalidArgumentException('Cannot read integers above 8 bytes');
        }

        if ($size === 1) {
            return $this->readUnsigned1ByteInteger($binary);
        }

        if ($size === 2) {
            return $this->readUnsigned2ByteInteger($binary);
        }

        if ($size === 3) {
            // For 3-byte integers, pad to 4 bytes and read as 4-byte
            if (strlen($binary) < 3) {
                throw new \InvalidArgumentException('Insufficient data for 3 byte integer');
            }
            return $this->readUnsigned4ByteInteger($binary . "\x00");
        }

        if ($size === 4) {
            return $this->readUnsigned4ByteInteger($binary);
        }

        if ($size === 8) {
            return $this->readUnsigned8ByteInteger($binary);
        }

        // For 5, 6, 7 byte integers, pad to 8 bytes
        if (strlen($binary) < $size) {
            throw new \InvalidArgumentException("Insufficient data for {$size} byte integer");
        }
        
        $paddedBinary = str_pad($binary, 8, "\x00");
        return \hexdec(\bin2hex(\strrev($paddedBinary)));
    }

    private function readUnsigned8ByteInteger(string $binary): int|float
    {
        if (strlen($binary) < 8) {
            throw new \InvalidArgumentException('Insufficient data for 8 byte integer');
        }

        // Truncate to 8 bytes if longer
        if (strlen($binary) > 8) {
            $binary = substr($binary, 0, 8);
        }

        return \hexdec(\bin2hex(\strrev($binary)));
    }

    private function readUnsigned4ByteInteger(string $binary): int
    {
        if (strlen($binary) < 4) {
            throw new \InvalidArgumentException('Insufficient data for 4 byte integer');
        }

        $result = unpack('V', $binary);
        return $result[1];
    }

    private function readUnsigned2ByteInteger(string $binary): int
    {
        if (strlen($binary) < 2) {
            throw new \InvalidArgumentException('Insufficient data for 2 byte integer');
        }

        $result = unpack('v', $binary);
        return $result[1];
    }

    private function readUnsigned1ByteInteger(string $binary): int
    {
        if (strlen($binary) < 1) {
            throw new \InvalidArgumentException('Insufficient data for 1 byte integer');
        }

        $result = unpack('C', $binary);
        return $result[1];
    }
}