<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer;

use Rcalicdan\MySQLBinaryProtocol\Exception\IncompleteBufferException;
use function strlen;
use function substr;
use function strpos;

/**
 * A buffer for reading binary data with support for partial reads and flushing.
 * 
 * This class provides a memory-efficient way to handle streaming binary data
 * by maintaining internal offsets and automatically managing buffer cleanup.
 */
class ReadBuffer
{
    private const ONE_MEGABYTE = 1024 * 1024;

    private string $buffer = '';
    private int $currentBufferOffset = 0;
    private int $readBufferOffset = 0;
    private int $bufferSize;

    /**
     * Creates a new read buffer with the specified maximum size.
     *
     * @param int $bufferSize Maximum buffer size in bytes
     */
    public function __construct(int $bufferSize = self::ONE_MEGABYTE)
    {
        $this->bufferSize = $bufferSize;
    }

    /**
     * Appends data to the end of the buffer.
     *
     * @param string $data Binary data to append
     */
    public function append(string $data): void
    {
        $this->buffer .= $data;
    }

    /**
     * Reads a specified number of bytes from the buffer.
     *
     * @param int $length Number of bytes to read
     * @return string The read data
     * @throws IncompleteBufferException If insufficient data is available
     */
    public function read(int $length): string
    {
        if (!$this->isReadable($length)) {
            $this->currentBufferOffset = $this->readBufferOffset;
            throw new IncompleteBufferException();
        }

        $data = substr($this->buffer, $this->currentBufferOffset, $length);
        $this->currentBufferOffset += $length;
        return $data;
    }

    /**
     * Checks if the specified number of bytes can be read from the buffer.
     *
     * @param int $length Number of bytes to check
     * @return bool True if readable, false otherwise
     */
    public function isReadable(int $length): bool
    {
        return strlen($this->buffer) - $this->currentBufferOffset >= $length;
    }

    /**
     * Flushes the buffer by updating the read offset and cleaning up consumed data.
     *
     * @return int Number of bytes that were read since the last flush
     */
    public function flush(): int
    {
        $bytesRead = $this->currentPosition();
        $this->readBufferOffset = $this->currentBufferOffset;

        if ($this->readBufferOffset >= $this->bufferSize) {
            $this->buffer = substr($this->buffer, $this->readBufferOffset);
            $this->readBufferOffset = 0;
            $this->currentBufferOffset = 0;
        }

        return $bytesRead;
    }

    /**
     * Scans for a pattern in the buffer starting from the current position.
     *
     * @param string $pattern The pattern to search for
     * @return int Position relative to current offset, or -1 if not found
     */
    public function scan(string $pattern): int
    {
        $position = strpos($this->buffer, $pattern, $this->currentBufferOffset);
        return $position === false ? -1 : ($position - $this->currentBufferOffset) + 1;
    }

    /**
     * Gets the current position in the buffer relative to the read offset.
     *
     * @return int Current position
     */
    public function currentPosition(): int
    {
        return $this->currentBufferOffset - $this->readBufferOffset;
    }
}