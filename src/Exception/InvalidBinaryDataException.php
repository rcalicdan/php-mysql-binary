<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Exception;

use RuntimeException;

/**
 * Exception thrown when invalid or malformed binary data is encountered.
 *
 * This exception indicates that the binary data does not conform to
 * the expected MySQL protocol format.
 */
class InvalidBinaryDataException extends RuntimeException
{
}
