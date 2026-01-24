<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Buffer\Writer;

class BufferPayloadWriterFactory
{
    private BinaryWriter $binaryWriter;

    public function __construct(?BinaryWriter $binaryWriter = null)
    {
        $this->binaryWriter = $binaryWriter ?? new BinaryWriter();
    }

    /**
     * Creates a new, empty BufferPayloadWriter.
     */
    public function create(): BufferPayloadWriter
    {
        return new BufferPayloadWriter($this->binaryWriter);
    }
}