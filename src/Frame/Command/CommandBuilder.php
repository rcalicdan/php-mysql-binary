<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Command;

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriterFactory;
use Rcalicdan\MySQLBinaryProtocol\Constants\Command;

/**
 * Builder for building MySQL command packets.
 *
 * This builder class is responsible for building MySQL command packets.
 * It takes a command and optional parameters and builds the packet accordingly.
 */
class CommandBuilder
{
    private BufferPayloadWriterFactory $writerFactory;
    private ParameterBuilder $parameterBuilder;

    public function __construct(
        ?BufferPayloadWriterFactory $writerFactory = null,
        ?ParameterBuilder $parameterBuilder = null
    ) {
        $this->writerFactory = $writerFactory ?? new BufferPayloadWriterFactory();
        $this->parameterBuilder = $parameterBuilder ?? new ParameterBuilder($this->writerFactory);
    }

    /**
     * Builds a COM_QUERY packet (Standard SQL execution).
     */
    public function buildQuery(string $sql): string
    {
        return $this->writerFactory->create()
            ->writeUInt8(Command::QUERY)
            ->writeString($sql)
            ->toString()
        ;
    }

    /**
     * Builds a COM_PING packet (Keep-alive).
     */
    public function buildPing(): string
    {
        return $this->writerFactory->create()
            ->writeUInt8(Command::PING)
            ->toString()
        ;
    }

    /**
     * Builds a COM_QUIT packet (Graceful disconnect).
     */
    public function buildQuit(): string
    {
        return $this->writerFactory->create()
            ->writeUInt8(Command::QUIT)
            ->toString()
        ;
    }

    /**
     * Builds a COM_INIT_DB packet (USE database_name).
     */
    public function buildInitDb(string $databaseName): string
    {
        return $this->writerFactory->create()
            ->writeUInt8(Command::INIT_DB)
            ->writeString($databaseName)
            ->toString()
        ;
    }

    /**
     * Builds a COM_STMT_PREPARE packet.
     */
    public function buildStmtPrepare(string $sql): string
    {
        return $this->writerFactory->create()
            ->writeUInt8(Command::STMT_PREPARE)
            ->writeString($sql)
            ->toString()
        ;
    }

    /**
     * Builds a COM_STMT_CLOSE packet.
     */
    public function buildStmtClose(int $statementId): string
    {
        return $this->writerFactory->create()
            ->writeUInt8(Command::STMT_CLOSE)
            ->writeUInt32($statementId)
            ->toString()
        ;
    }

    /**
     * Builds a COM_STMT_EXECUTE packet.
     *
     * @param array<int, mixed> $params
     */
    public function buildStmtExecute(int $statementId, array $params, int $flags = 0): string
    {
        $writer = $this->writerFactory->create();

        $writer->writeUInt8(Command::STMT_EXECUTE)
            ->writeUInt32($statementId)
            ->writeUInt8($flags)
            ->writeUInt32(1) // Iteration count
        ;

        if (! empty($params)) {
            $boundParams = $this->parameterBuilder->build($params);
            $writer->writeString($boundParams->nullBitmap)
                ->writeUInt8(1) // new-params-bound-flag
                ->writeString($boundParams->types)
                ->writeString($boundParams->values)
            ;
        }

        return $writer->toString();
    }
}
