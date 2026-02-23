<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;

/**
 * Marker frame used when the server omits ColumnDefinitions and proceeds
 * straight to BinaryRows (a prepared statement optimization).
 */
final readonly class MetadataOmittedRowMarker implements Frame
{
}
