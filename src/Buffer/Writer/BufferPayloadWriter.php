<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Writer;

use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadWriter;

class BufferPayloadWriter implements PayloadWriter
{
    private string $buffer = '';

    private BinaryWriter $binaryWriter;

    public function __construct(?BinaryWriter $binaryWriter = null)
    {
        $this->binaryWriter = $binaryWriter ?? new BinaryWriter();
    }

    public function writeUInt8(int $value): self
    {
        $this->buffer .= $this->binaryWriter->writeUInt8($value);

        return $this;
    }

    public function writeUInt16(int $value): self
    {
        $this->buffer .= $this->binaryWriter->writeUInt16($value);

        return $this;
    }

    public function writeUInt32(int $value): self
    {
        $this->buffer .= $this->binaryWriter->writeUInt32($value);

        return $this;
    }

    public function writeUInt64(int $value): self
    {
        $this->buffer .= $this->binaryWriter->writeUInt64($value);

        return $this;
    }

    public function writeFloat(float $value): self
    {
        $this->buffer .= $this->binaryWriter->writeFloat($value);

        return $this;
    }

    public function writeDouble(float $value): self
    {
        $this->buffer .= $this->binaryWriter->writeDouble($value);

        return $this;
    }

    public function writeLengthEncodedInteger(int $value): self
    {
        if ($value < 251) {
            $this->buffer .= $this->binaryWriter->writeUInt8($value);

            return $this;
        }

        if ($value < 65536) {
            $this->buffer .= "\xFC" . $this->binaryWriter->writeUInt16($value);

            return $this;
        }

        if ($value < 16777216) {
            $this->buffer .= "\xFD" . $this->binaryWriter->writeUInt24($value);

            return $this;
        }

        $this->buffer .= "\xFE" . $this->binaryWriter->writeUInt64($value);

        return $this;
    }

    public function writeString(string $value): self
    {
        $this->buffer .= $value;

        return $this;
    }

    public function writeNullTerminatedString(string $value): self
    {
        $this->buffer .= $value . "\x00";

        return $this;
    }

    public function writeLengthEncodedString(string $value): self
    {
        $this->writeLengthEncodedInteger(strlen($value));
        $this->buffer .= $value;

        return $this;
    }

    public function writeZeros(int $count): self
    {
        if ($count > 0) {
            $this->buffer .= str_repeat("\x00", $count);
        }

        return $this;
    }

    public function toString(): string
    {
        return $this->buffer;
    }
}
