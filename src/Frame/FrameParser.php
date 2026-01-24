<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame;

use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Interface for parsing MySQL protocol frames from payload data.
 * 
 * Frame parsers are responsible for extracting structured data
 * from binary protocol payloads and creating appropriate frame objects.
 */
interface FrameParser
{
    /**
     * Parses a frame from the provided payload data.
     *
     * @param PayloadReader $payload The payload reader containing frame data
     * @param int $length The length of the frame data
     * @param int $sequenceNumber The packet sequence number
     * 
     * Note: The $length and $sequenceNumber parameters are provided for protocol
     * completeness and may be used for validation, logging, or debugging purposes.
     * Most frame parsers can rely on PayloadReader's boundary management and do
     * not need to directly use these parameters.
     * 
     * @return Frame The parsed frame object
     */
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame;
}
