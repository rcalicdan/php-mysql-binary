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
     * @return Frame The parsed frame object
     */
    public function parse(PayloadReader $payload, int $length, int $sequenceNumber): Frame;
}