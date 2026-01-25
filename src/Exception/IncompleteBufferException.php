<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Exception;

use LengthException;

/**
 * Exception thrown when a buffer operation cannot be completed due to insufficient data.
 *
 * This exception is typically thrown when attempting to read more data
 * than is currently available in the buffer.
 */
class IncompleteBufferException extends LengthException
{
}
