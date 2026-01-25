<?php

declare(strict_types=1);

namespace Rcalicdan\MySQLBinaryProtocol\Frame\Response;

use Rcalicdan\MySQLBinaryProtocol\Frame\Frame;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

/**
 * Parser for MySQL command response packets.
 *
 * Unlike FrameParser which handles single, well-defined frame types,
 * ResponseParserInterface handles the initial response packet from a command
 * which can be one of three different types based on the first byte:
 *
 * - OK Packet (0x00): Command executed successfully without result set
 * - ERR Packet (0xFF): Command failed with an error
 * - Result Set Header: Query returned rows (column count as length-encoded integer)
 *
 * This parser only handles the FIRST packet of a response. For result sets,
 * subsequent packets (column definitions, rows, EOF) must be parsed separately.
 */
interface ResponseParserInterface
{
    /**
     * Parses the first response packet from a MySQL command.
     *
     * This method reads the first byte to determine the packet type and
     * delegates to the appropriate parser.
     *
     * @param PayloadReader $payload The payload reader containing packet data
     * @param int $length The total packet length in bytes
     * @param int $sequenceNumber The packet sequence number
     *
     * @return Frame Returns one of:
     *               - OkPacket: Command succeeded
     *               - ErrPacket: Command failed
     *               - ResultSetHeader: Query returned result set
     *
     * @throws \RuntimeException If the packet format is invalid
     */
    public function parseResponse(PayloadReader $payload, int $length, int $sequenceNumber): Frame;
}
