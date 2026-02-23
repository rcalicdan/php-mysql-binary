# MySQL Binary Protocol

A pure PHP implementation of the MySQL binary protocol for low-level packet serialization and deserialization. This library provides the building blocks for implementing MySQL clients, proxies, and protocol analyzers.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## Overview

This library handles the **protocol layer** of MySQL communication - parsing and building binary packets according to the MySQL wire protocol specification. It does NOT handle network I/O, connection management, or provide a high-level database client API.

**What this library does:**
- Parse MySQL protocol packets (handshake, authentication, commands, responses)
- Build MySQL protocol packets for sending to servers
- Handle both text and binary result set protocols
- Support MySQL 4.1+ protocol features including prepared statements
- Provide authentication scrambling for `mysql_native_password` and `caching_sha2_password`

**What this library does NOT do:**
- Network I/O (sockets, streams)
- Connection pooling or management
- Transaction state tracking

## Requirements

- PHP 8.3 or higher
- No external dependencies

## Installation
```bash
composer require rcalicdan/mysql-binary-protocol
```

## Quick Start

### 1. Parsing a Handshake Packet
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeParser;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;

// Assume you received this data from a MySQL server socket
$rawPacketData = /* binary data from socket */;

// Create a payload reader from the raw data
$readerFactory = new BufferPayloadReaderFactory();
$payloadReader = $readerFactory->createFromString($rawPacketData);

// Parse the handshake
$parser = new HandshakeParser();
$handshake = $parser->parse($payloadReader, strlen($rawPacketData), 0);

echo "Server version: {$handshake->serverVersion}\n";
echo "Connection ID: {$handshake->connectionId}\n";
echo "Auth plugin: {$handshake->authPlugin}\n";
```

### 2. Building a Handshake Response
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeResponse41;
use Rcalicdan\MySQLBinaryProtocol\Auth\AuthScrambler;
use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\CharsetIdentifiers;

// Scramble the password using the server's auth data
$password = 'mypassword';
$authResponse = AuthScrambler::scrambleNativePassword(
    $password,
    $handshake->authData
);

// Build capabilities (what features the client supports)
$capabilities = CapabilityFlags::CLIENT_PROTOCOL_41
    | CapabilityFlags::CLIENT_SECURE_CONNECTION
    | CapabilityFlags::CLIENT_PLUGIN_AUTH
    | CapabilityFlags::CLIENT_CONNECT_WITH_DB;

// Build the handshake response
$responseBuilder = new HandshakeResponse41();
$responsePayload = $responseBuilder->build(
    capabilities: $capabilities,
    charset: CharsetIdentifiers::UTF8MB4,
    username: 'myuser',
    authResponse: $authResponse,
    database: 'mydb',
    authPluginName: $handshake->authPlugin
);

// Wrap it in a packet and send to server
$packetWriter = new UncompressedPacketWriter();
$packet = $packetWriter->write($responsePayload, sequenceId: 1);

// Send $packet to the MySQL server via socket
```

### 3. Executing a Query
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Command\CommandBuilder;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

$commandBuilder = new CommandBuilder();
$packetWriter = new UncompressedPacketWriter();

// Build a COM_QUERY packet
$queryPayload = $commandBuilder->buildQuery('SELECT * FROM users WHERE id = 1');
$packet = $packetWriter->write($queryPayload, sequenceId: 0);

// Send $packet to server
```

### 4. Parsing Response Packets
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacketParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacketParser;

// After receiving response from server
$responseData = /* binary data from socket */;
$reader = $readerFactory->createFromString($responseData);

// Check first byte to determine packet type
$firstByte = ord($responseData[0]);

if ($firstByte === 0x00) {
    // OK Packet
    $parser = new OkPacketParser();
    $ok = $parser->parse($reader, strlen($responseData), 1);
    echo "Affected rows: {$ok->affectedRows}\n";
    echo "Last insert ID: {$ok->lastInsertId}\n";
} elseif ($firstByte === 0xFF) {
    // Error Packet
    $parser = new ErrPacketParser();
    $error = $parser->parse($reader, strlen($responseData), 1);
    echo "Error {$error->errorCode}: {$error->errorMessage}\n";
}
```

### 5. Using Prepared Statements
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\StmtPrepareOkPacketParser;

// Prepare a statement
$preparePayload = $commandBuilder->buildStmtPrepare(
    'INSERT INTO users (name, email) VALUES (?, ?)'
);
$packet = $packetWriter->write($preparePayload, sequenceId: 0);
// Send to server...

// Parse the response to get statement ID
$response = /* receive from server */;
$parser = new StmtPrepareOkPacketParser();
$prepareOk = $parser->parse($reader, strlen($response), 1);
$statementId = $prepareOk->statementId;

// Execute the prepared statement
$executePayload = $commandBuilder->buildStmtExecute(
    statementId: $statementId,
    params: ['John Doe', 'john@example.com']
);
$packet = $packetWriter->write($executePayload, sequenceId: 0);
// Send to server...
```

### 6. Parsing Result Sets
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinitionParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRowParser;

// After executing a SELECT query, parse column definitions
$columnParser = new ColumnDefinitionParser();
$columns = [];

for ($i = 0; $i < $columnCount; $i++) {
    $columnData = /* receive column packet from server */;
    $reader = $readerFactory->createFromString($columnData);
    $columns[] = $columnParser->parse($reader, strlen($columnData), $i + 2);
}

// Then parse row data
$rowParser = new TextRowParser($columnCount);
while ($hasMoreRows) {
    $rowData = /* receive row packet from server */;
    $reader = $readerFactory->createFromString($rowData);
    $row = $rowParser->parse($reader, strlen($rowData), $sequenceId);
    
    foreach ($row->values as $i => $value) {
        echo "{$columns[$i]->name}: {$value}\n";
    }
}
```

## Architecture

The library is organized into several layers:

### Buffer Layer
- **`ReadBuffer`**: Efficient streaming buffer for reading binary data
- **`BinaryIntegerReader`**: Reads integers of various sizes (1-8 bytes)
- **`BufferPayloadReader`**: Implements `PayloadReader` for reading MySQL data types
- **`BinaryWriter`**: Writes integers and floats in little-endian format
- **`BufferPayloadWriter`**: Implements `PayloadWriter` for writing MySQL data types

### Packet Layer
- **`UncompressedPacketReader`**: Reads MySQL packets with proper framing
- **`UncompressedPacketWriter`**: Writes MySQL packets with headers
- **`PayloadReader` Interface**: Abstraction for reading various MySQL data types
- **`PayloadWriter` Interface**: Abstraction for writing MySQL data types

> **Note:** Compressed packet support (`CompressedPacketReader` and `CompressedPacketWriter`) is not yet implemented and will be added in a future release.

### Frame Layer
Parsers and builders for specific MySQL protocol frames:

#### Handshake
- **`HandshakeParser`**: Parses server handshake (greeting) packets
- **`HandshakeV10`**: Represents a handshake frame
- **`HandshakeResponse41`**: Builds client authentication response

#### Commands
- **`CommandBuilder`**: Builds command packets (COM_QUERY, COM_PING, etc.)
- **`ParameterBuilder`**: Builds bound parameters for prepared statements

#### Responses
- **`OkPacketParser`**: Parses OK packets
- **`ErrPacketParser`**: Parses error packets
- **`StmtPrepareOkPacketParser`**: Parses prepared statement responses

#### Result Sets
- **`ColumnDefinitionParser`**: Parses column metadata
- **`TextRowParser`**: Parses text protocol result rows
- **`BinaryRowParser`**: Parses binary protocol result rows (from prepared statements)

### Authentication
- **`AuthScrambler::scrambleNativePassword()`**: MySQL 4.1+ native password hashing
- **`AuthScrambler::scrambleCachingSha2Password()`**: MySQL 8.0+ SHA-256 password hashing

### Constants
- **`CapabilityFlags`**: Client/server capability flags
- **`Command`**: Command type constants
- **`MysqlType`**: MySQL data type identifiers
- **`StatusFlags`**: Server status flags
- **`CharsetIdentifiers`**: Character set identifiers

## Advanced Usage

### Streaming Packet Reading
```php
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\ReadBuffer;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;

$buffer = new ReadBuffer();
$integerReader = new BinaryIntegerReader();
$readerFactory = new BufferPayloadReaderFactory();
$packetReader = new UncompressedPacketReader(
    $integerReader,
    $buffer,
    $readerFactory
);

// Read data from socket in chunks
while ($chunk = socket_read($socket, 8192)) {
    $packetReader->append($chunk);
    
    // Process all complete packets
    while ($packetReader->readPayload(function($payload, $length, $sequence) {
        // Handle the packet
        $firstByte = $payload->readFixedInteger(1);
        // ... parse based on packet type
    })) {
        // Packet was successfully read
    }
}
```

### Custom Packet Processing
```php
use Rcalicdan\MySQLBinaryProtocol\Frame\FrameParser;
use Rcalicdan\MySQLBinaryProtocol\Packet\PayloadReader;

class MyCustomPacketParser implements FrameParser
{
    public function parse(PayloadReader $reader, int $length, int $sequenceNumber): Frame
    {
        // Read custom packet structure
        $field1 = $reader->readFixedInteger(4);
        $field2 = $reader->readNullTerminatedString();
        $field3 = $reader->readLengthEncodedStringOrNull();
        
        return new MyCustomFrame($field1, $field2, $field3);
    }
}
```

## Testing

The library includes comprehensive test coverage using Pest:
```bash
composer test
```

## Protocol Reference

This library implements the MySQL Client/Server Protocol as documented in:
- [MySQL Internals Manual - Client/Server Protocol](https://dev.mysql.com/doc/internals/en/client-server-protocol.html)
- [MySQL Protocol Documentation](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basics.html)

### Supported Features

-  Protocol Version 10 (MySQL 3.21+)
-  CLIENT_PROTOCOL_41 capability
-  CLIENT_SECURE_CONNECTION (auth data)
-  CLIENT_PLUGIN_AUTH (pluggable authentication)
-  Text protocol result sets
-  Binary protocol result sets (prepared statements)
-  COM_QUERY, COM_PING, COM_QUIT, COM_INIT_DB
-  COM_STMT_PREPARE, COM_STMT_EXECUTE, COM_STMT_CLOSE
-  mysql_native_password authentication
-  caching_sha2_password authentication
-  Length-encoded integers and strings
-  NULL bitmap handling
-  Compressed packets (CLIENT_COMPRESS)

### Not Yet Supported
-  Authentication plugin switching
-  Multi-statement queries
-  Local INFILE
-  Stored procedure OUT parameters

## Use Cases

This library is designed for:

1. **Custom MySQL Clients**: Build custom MySQL clients
2. **Protocol Analyzers**: Analyze MySQL network traffic
3. **Proxy Servers**: Create MySQL proxies for load balancing, query logging, etc.
4. **Testing Tools**: Simulate MySQL client/server interactions
5. **Educational**: Learn how the MySQL protocol works

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This library is open-sourced software licensed under the [MIT license](LICENSE).

## Related Projects

- [krowinski/php-mysql-replication](https://github.com/krowinski/php-mysql-replication) - MySQL binlog replication in PHP
- [EcomDev/php-mysql-binary-protocol](https://github.com/EcomDev/php-mysql-binary-protocol) - This library is a fork of EcomDev/php-mysql-binary-protocol with additional features and improvements.

## Support

For bugs and feature requests, please use the [GitHub issue tracker](https://github.com/rcalicdan/mysql-binary-protocol/issues).