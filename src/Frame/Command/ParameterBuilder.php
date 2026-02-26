<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Command;

use Rcalicdan\MySQLBinaryProtocol\Buffer\Writer\BufferPayloadWriterFactory;
use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;

/**
 * Builder for building bound parameters for COM_STMT_EXECUTE packets.
 *
 * This builder class is responsible for building bound parameters for COM_STMT_EXECUTE packets.
 * It takes an array of parameters and builds the null bitmap, types, and values for the packet.
 */
class ParameterBuilder
{
    private BufferPayloadWriterFactory $writerFactory;

    public function __construct(?BufferPayloadWriterFactory $writerFactory = null)
    {
        $this->writerFactory = $writerFactory ?? new BufferPayloadWriterFactory();
    }

    /**
     * @param array<int, mixed> $params
     */
    public function build(array $params): BoundParams
    {
        if (empty($params)) {
            return new BoundParams('', '', '');
        }

        $numParams = \count($params);
        $nullBitmapBytes = (int) floor(($numParams + 7) / 8);
        $nullBitmap = array_fill(0, $nullBitmapBytes, 0);

        $types = '';
        $valuesWriter = $this->writerFactory->create();

        foreach ($params as $i => $param) {
            $type = $this->getParameterType($param);
            $types .= pack('v', $type);

            if ($type === MysqlType::NULL) {
                $byteIndex = (int) floor($i / 8);
                $bitIndex = $i % 8;
                $nullBitmap[$byteIndex] |= (1 << $bitIndex);

                continue;
            }

            if ($type === MysqlType::LONGLONG) {
                $valuesWriter->writeUInt64($param);
            } elseif ($type === MysqlType::DOUBLE) {
                $valuesWriter->writeDouble($param);
            } elseif ($type === MysqlType::VAR_STRING) {
                $valuesWriter->writeLengthEncodedString((string) $param);
            }
        }

        return new BoundParams(
            implode('', array_map(fn (int $byte): string => \chr($byte & 0xFF), $nullBitmap)),
            $types,
            $valuesWriter->toString()
        );
    }

    private function getParameterType(mixed $param): int
    {
        if ($param === null) {
            return MysqlType::NULL;
        }

        if (\is_int($param)) {
            return MysqlType::LONGLONG;
        }

        if (\is_float($param)) {
            return MysqlType::DOUBLE;
        }

        return MysqlType::VAR_STRING;
    }
}
