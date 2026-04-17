# MySQL Binary Protocol

A pure PHP implementation of the MySQL binary protocol for low-level packet serialization and deserialization. This library provides the building blocks for implementing MySQL clients, proxies, and protocol analyzers.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## Overview

This library handles the **protocol layer** of MySQL communication for parsing and building binary packets according to the MySQL wire protocol specification. It does **not** handle network I/O, connection management, or provide a high-level database client API.

**What this library does:**
- Parse MySQL protocol packets (handshake, authentication, commands, responses)
- Build MySQL protocol packets for sending to servers
- Handle both text and binary result set protocols
- Support MySQL 4.1+ protocol features including prepared statements
- Support multi-step authentication flows (`mysql_native_password`, `caching_sha2_password`)
- Build SSL request packets for encrypted connections

**What this library does NOT do:**
- Network I/O (sockets, streams)
- Connection pooling or management
- Transaction state tracking

## Requirements

- PHP 8.3 or higher
- No external dependencies (OpenSSL extension required only for RSA-encrypted `caching_sha2_password`)

## Installation

```bash
composer require rcalicdan/mysql-binary-protocol
```

## Quick Start

### 1. Parsing a Server Handshake

After connecting to MySQL, the server sends an initial handshake packet:

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeParser;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;

$readerFactory = new BufferPayloadReaderFactory();

// $rawData received from socket
$reader = $readerFactory->createFromString($rawData);
$parser = new HandshakeParser();
$handshake = $parser->parse($reader, strlen($rawData), 0);

echo "Server: {$handshake->serverVersion}\n";
echo "Connection ID: {$handshake->connectionId}\n";
echo "Auth plugin: {$handshake->authPlugin}\n";   // e.g. caching_sha2_password
echo "Capabilities: {$handshake->capabilities}\n";
```

### 2. Requesting an SSL Connection (Optional)

If you want to upgrade to SSL before authenticating, send an `SslRequest` packet immediately after parsing the handshake, before sending `HandshakeResponse41`:

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\SslRequest;
use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\CharsetIdentifiers;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

$capabilities = CapabilityFlags::CLIENT_PROTOCOL_41
    | CapabilityFlags::CLIENT_SECURE_CONNECTION
    | CapabilityFlags::CLIENT_PLUGIN_AUTH
    | CapabilityFlags::CLIENT_SSL;

$sslPayload = (new SslRequest())->build($capabilities, CharsetIdentifiers::UTF8MB4);

$writer = new UncompressedPacketWriter();
$packet = $writer->write($sslPayload, sequenceId: 1);

// Send $packet to server, then perform TLS handshake on the socket,
// then continue with HandshakeResponse41 on the encrypted connection
```

### 3. Building a Handshake Response

```php
use Rcalicdan\MySQLBinaryProtocol\Auth\AuthScrambler;
use Rcalicdan\MySQLBinaryProtocol\Constants\CapabilityFlags;
use Rcalicdan\MySQLBinaryProtocol\Constants\CharsetIdentifiers;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeResponse41;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

$capabilities = CapabilityFlags::CLIENT_PROTOCOL_41
    | CapabilityFlags::CLIENT_SECURE_CONNECTION
    | CapabilityFlags::CLIENT_PLUGIN_AUTH
    | CapabilityFlags::CLIENT_CONNECT_WITH_DB;

$authResponse = AuthScrambler::scrambleCachingSha2Password(
    'mypassword',
    $handshake->authData
);

$payload = (new HandshakeResponse41())->build(
    capabilities: $capabilities,
    charset: CharsetIdentifiers::UTF8MB4,
    username: 'myuser',
    authResponse: $authResponse,
    database: 'mydb',
    authPluginName: $handshake->authPlugin
);

$packet = (new UncompressedPacketWriter())->write($payload, sequenceId: 1);
// Send $packet to server
```

### 4. Handling Authentication Responses

After sending `HandshakeResponse41`, the server may respond with an OK, an error, or one of the multi-step authentication packets. Use `AuthResponseParser` to dispatch all cases:

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthResponseParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthMoreData;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\AuthSwitchRequest;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;

$reader = $readerFactory->createFromString($rawResponse);

/** @var OkPacket|ErrPacket|AuthMoreData|AuthSwitchRequest $frame */
$frame = (new AuthResponseParser())->parse($reader, strlen($rawResponse), $sequenceNumber);

if ($frame instanceof OkPacket) {
    // Authentication successful
} elseif ($frame instanceof ErrPacket) {
    throw new RuntimeException("Auth error {$frame->errorCode}: {$frame->errorMessage}");
} elseif ($frame instanceof AuthSwitchRequest) {
    // Server wants to switch to a different auth plugin
    // Re-scramble using $frame->pluginName and $frame->authData, then send response
} elseif ($frame instanceof AuthMoreData) {
    // Multi-step authentication (caching_sha2_password specific)
    if ($frame->isFastAuthSuccess()) {
        // Password matched the cache — wait for final OK packet
    } elseif ($frame->isFullAuthRequired()) {
        // Cache miss — server needs full authentication
        // For plaintext connections: request the server's RSA public key
        // For SSL connections: send the password as plaintext
    } else {
        // $frame->data contains the server's RSA public key PEM
        $encrypted = AuthScrambler::scrambleSha256Rsa(
            'mypassword',
            $handshake->authData,
            $frame->data
        );
        // Send $encrypted to server
    }
}
```

### 5. Executing a Query

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Command\CommandBuilder;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

$writer = new UncompressedPacketWriter();
$builder = new CommandBuilder();

$packet = $writer->write($builder->buildQuery('SELECT * FROM users'), sequenceId: 0);
// Send $packet to server
```

Other available commands:

```php
$builder->buildPing();
$builder->buildQuit();
$builder->buildInitDb('my_database');
$builder->buildStmtPrepare('SELECT * FROM users WHERE id = ?');
$builder->buildStmtClose($statementId);
$builder->buildStmtExecute($statementId, [42]);
```

### 6. Parsing Command Responses

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ResponseParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\OkPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ErrPacket;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\ResultSetHeader;

$reader = $readerFactory->createFromString($rawResponse);

/** @var OkPacket|ErrPacket|ResultSetHeader $frame */
$frame = (new ResponseParser())->parseResponse($reader, strlen($rawResponse), $sequenceNumber);

if ($frame instanceof OkPacket) {
    echo "Affected rows: {$frame->affectedRows}\n";
    echo "Last insert ID: {$frame->lastInsertId}\n";
    echo "Has more results: " . ($frame->hasMoreResults() ? 'yes' : 'no') . "\n";
} elseif ($frame instanceof ErrPacket) {
    echo "Error [{$frame->sqlState}] {$frame->errorCode}: {$frame->errorMessage}\n";
} elseif ($frame instanceof ResultSetHeader) {
    echo "Column count: {$frame->columnCount}\n";
    // Continue reading column definitions and rows
}
```

### 7. Parsing Result Sets (Text Protocol)

Used after a `COM_QUERY` that returns rows:

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinitionParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRowParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\EofPacket;

$columnParser = new ColumnDefinitionParser();
$columns = [];

// $resultSetHeader->columnCount columns follow
for ($i = 0; $i < $resultSetHeader->columnCount; $i++) {
    $reader = $readerFactory->createFromString(/* next packet from socket */);
    /** @var \Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition $col */
    $col = $columnParser->parse($reader, strlen($raw), $i + 2);
    $columns[] = $col;
    echo "{$col->name} (type={$col->type}, charset={$col->charset})\n";
}

// EOF packet separates column definitions from rows (unless CLIENT_DEPRECATE_EOF)
// Read and discard the EOF packet here if applicable

$rowParser = new TextRowParser(count($columns));

while (true) {
    $raw = /* next packet from socket */;
    $reader = $readerFactory->createFromString($raw);
    $frame = $rowParser->parse($reader, strlen($raw), $sequenceNumber);

    if ($frame instanceof EofPacket) {
        break;
    }

    /** @var \Rcalicdan\MySQLBinaryProtocol\Frame\Result\TextRow $frame */
    foreach ($frame->values as $i => $value) {
        echo "{$columns[$i]->name}: {$value}\n";
    }
}
```

### 8. Using Prepared Statements (Binary Protocol)

```php
use Rcalicdan\MySQLBinaryProtocol\Frame\Response\StmtPrepareOkPacketParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRowParser;

// Prepare
$preparePacket = $writer->write($builder->buildStmtPrepare(
    'SELECT id, name, balance FROM accounts WHERE id = ?'
), sequenceId: 0);
// Send $preparePacket, receive response...

/** @var \Rcalicdan\MySQLBinaryProtocol\Frame\Response\StmtPrepareOkPacket $prepareOk */
$prepareOk = (new StmtPrepareOkPacketParser())->parse($reader, strlen($raw), 1);

$statementId = $prepareOk->statementId;
echo "Params: {$prepareOk->numParams}, Columns: {$prepareOk->numColumns}\n";

// Execute — parameter types are inferred automatically
// int -> LONGLONG, float -> DOUBLE, string/other -> VAR_STRING, null -> NULL
$executePacket = $writer->write(
    $builder->buildStmtExecute($statementId, [42]),
    sequenceId: 0
);
// Send $executePacket, receive response...

// Parse binary rows
$binaryRowParser = new BinaryRowParser($columns); // $columns from column definition packets

/** @var \Rcalicdan\MySQLBinaryProtocol\Frame\Result\BinaryRow $row */
$row = $binaryRowParser->parse($reader, strlen($raw), $sequenceNumber);

foreach ($row->values as $i => $value) {
    echo "{$columns[$i]->name}: " . ($value ?? 'NULL') . "\n";
}
```

### 9. Streaming Packet Reading

For real socket-based implementations that receive data in chunks:

```php
use Rcalicdan\MySQLBinaryProtocol\Factory\DefaultPacketReaderFactory;

$packetReader = (new DefaultPacketReaderFactory())->createWithDefaultSettings();

while ($chunk = socket_read($socket, 8192)) {
    $packetReader->append($chunk);

    while ($packetReader->hasPacket()) {
        $packetReader->readPayload(function ($payload, $length, $sequenceNumber) use ($readerFactory) {
            // $payload is a PayloadReader — inspect the first byte to dispatch
            $firstByte = $payload->readFixedInteger(1);
            // ... parse based on packet type
        });
    }
}
```

For compressed connections (requires `zlib` extension):

```php
$packetReader = (new DefaultPacketReaderFactory())->createCompressed();
```

---

## Architecture

### Buffer Layer

Handles raw binary I/O:

- `ReadBuffer` — Streaming buffer with rollback support for incomplete packets
- `BinaryIntegerReader` — Reads 1–8 byte little-endian integers
- `BufferPayloadReader` — Implements `PayloadReader`; reads MySQL protocol data types (length-encoded integers/strings, null-terminated strings, etc.)
- `BufferPayloadReaderFactory` — Creates `BufferPayloadReader` from a raw string or a shared `ReadBuffer`
- `BinaryWriter` — Writes integers and floats in little-endian format
- `BufferPayloadWriter` — Implements `PayloadWriter`; builds MySQL protocol payloads with method chaining

### Packet Layer

Handles MySQL packet framing (3-byte length + 1-byte sequence):

- `UncompressedPacketReader` — Parses the MySQL packet framing, handles partial reads and multi-packet payloads
- `UncompressedPacketWriter` — Wraps a payload in the standard 4-byte packet header
- `CompressedPacketReader` — Handles compressed packet framing (requires `zlib`)
- `CompressedPacketWriter` — Writes compressed packets (requires `zlib`)
- `PacketFramer` — Splits payloads larger than 16MB across multiple packets
- `DefaultPacketReaderFactory` / `DefaultPacketWriterFactory` — Convenience factories for standard or compressed setups

### Frame Layer

Parsers and builders for specific MySQL protocol frames:

#### Handshake

| Class | Role |
|---|---|
| `HandshakeParser` | Parses the server's initial greeting packet (Protocol v10, including legacy pre-4.1) |
| `HandshakeV10` | Frame: server version, connection ID, auth data, capabilities, charset, auth plugin |
| `HandshakeResponse41` | Builds the client's authentication response packet |
| `SslRequest` | Builds the SSL upgrade request (truncated handshake response with `CLIENT_SSL`) |
| `AuthResponseParser` | Dispatches all server packets that can follow `HandshakeResponse41` |
| `AuthMoreData` | Frame: multi-step auth data (fast auth success, full auth required, RSA public key) |
| `AuthSwitchRequest` | Frame: server requesting a switch to a different auth plugin |

#### Commands

| Class | Role |
|---|---|
| `CommandBuilder` | Builds command payloads (`COM_QUERY`, `COM_PING`, `COM_QUIT`, `COM_INIT_DB`, `COM_STMT_PREPARE`, `COM_STMT_EXECUTE`, `COM_STMT_CLOSE`) |
| `ParameterBuilder` | Builds the null bitmap, type info, and values for `COM_STMT_EXECUTE` |
| `BoundParams` | Value object holding the serialized parameter segments |

#### Responses

| Class | Role |
|---|---|
| `ResponseParser` | Routes command responses: OK, ERR, or result set header |
| `OkPacketParser` | Parses OK packets (affected rows, last insert ID, status flags, warnings) |
| `ErrPacketParser` | Parses error packets (error code, SQL state, message) |
| `StmtPrepareOkPacketParser` | Parses `COM_STMT_PREPARE` OK responses (statement ID, column/param counts) |
| `StmtPrepareResponseParser` | Routes prepare responses (OK or ERR) |
| `ColumnDefinitionOrEofParser` | Parses column metadata or EOF during result set reading |
| `RowOrEofParser` | Parses text rows or EOF for `COM_QUERY` result sets |
| `DynamicRowOrEofParser` | Auto-detects text vs. binary row format (for stored procedure results) |

#### Result Sets

| Class | Role |
|---|---|
| `ColumnDefinitionParser` | Parses column metadata (name, type, charset, flags, etc.) |
| `TextRowParser` | Parses text protocol rows (all values as nullable strings) |
| `BinaryRowParser` | Parses binary protocol rows (typed values, null bitmap handling) |

### Authentication

```php
use Rcalicdan\MySQLBinaryProtocol\Auth\AuthScrambler;

// MySQL 5.7 and legacy connections
$response = AuthScrambler::scrambleNativePassword($password, $serverNonce);

// MySQL 8.0+ (SHA-256, used for initial caching_sha2_password challenge)
$response = AuthScrambler::scrambleCachingSha2Password($password, $serverNonce);

// MySQL 8.0+ full auth over plaintext connection (requires openssl extension)
$response = AuthScrambler::scrambleSha256Rsa($password, $serverNonce, $rsaPublicKeyPem);
```

### Constants

| Class | Contains |
|---|---|
| `CapabilityFlags` | `CLIENT_PROTOCOL_41`, `CLIENT_SSL`, `CLIENT_PLUGIN_AUTH`, etc. |
| `AuthPacketType` | `OK`, `ERR`, `AUTH_SWITCH_REQUEST`, `AUTH_MORE_DATA`, `FAST_AUTH_SUCCESS`, `FULL_AUTH_REQUIRED` |
| `Command` | `QUERY`, `PING`, `QUIT`, `STMT_PREPARE`, `STMT_EXECUTE`, etc. |
| `MysqlType` | `TINY`, `LONG`, `LONGLONG`, `VARCHAR`, `BLOB`, `DATETIME`, etc. |
| `CharsetIdentifiers` | `UTF8` (33), `LATIN1` (8), `UTF8MB4` (255) |
| `CharsetMap` | Maps charset names to their MySQL collation IDs |
| `StatusFlags` | `SERVER_STATUS_AUTOCOMMIT`, `SERVER_MORE_RESULTS_EXISTS`, etc. |
| `ColumnFlags` | `NOT_NULL_FLAG`, `PRI_KEY_FLAG`, `UNSIGNED_FLAG`, `BLOB_FLAG`, etc. |
| `PacketType` | `OK`, `ERR`, `EOF`, `LOCAL_INFILE` |
| `DataTypeBounds` | Sign-bit and range constants for signed integer conversion |

---

## Authentication Flows

### mysql_native_password (MySQL 5.7 and earlier)

```
Client                          Server
  |                               |
  |  <-- HandshakeV10 ----------  |  (auth_plugin = mysql_native_password)
  |                               |
  |  --> HandshakeResponse41 -->  |  (SHA1-scrambled password)
  |                               |
  |  <-- OkPacket / ErrPacket --  |
```

### caching_sha2_password (MySQL 8.0+, fast path)

```
Client                          Server
  |                               |
  |  <-- HandshakeV10 ----------  |  (auth_plugin = caching_sha2_password)
  |                               |
  |  --> HandshakeResponse41 -->  |  (SHA256-scrambled password)
  |                               |
  |  <-- AuthMoreData (0x03) ---  |  FAST_AUTH_SUCCESS — password matched cache
  |                               |
  |  <-- OkPacket --------------  |
```

### caching_sha2_password (MySQL 8.0+, full auth over SSL)

```
Client                          Server
  |                               |
  |  <-- HandshakeV10 ----------  |
  |  --> SslRequest + TLS ------> |  (upgrade to SSL first)
  |  --> HandshakeResponse41 -->  |
  |                               |
  |  <-- AuthMoreData (0x04) ---  |  FULL_AUTH_REQUIRED — cache miss
  |                               |
  |  --> raw password + \0 ---->  |  (safe to send over SSL)
  |                               |
  |  <-- OkPacket --------------  |
```

### caching_sha2_password (MySQL 8.0+, full auth over plaintext)

```
Client                          Server
  |                               |
  |  <-- HandshakeV10 ----------  |
  |  --> HandshakeResponse41 -->  |
  |                               |
  |  <-- AuthMoreData (0x04) ---  |  FULL_AUTH_REQUIRED
  |                               |
  |  --> request public key ---->  |  (send \x02)
  |                               |
  |  <-- AuthMoreData (RSA key) - |  PEM-encoded public key
  |                               |
  |  --> RSA-encrypted password > |  (openssl PKCS1 OAEP)
  |                               |
  |  <-- OkPacket --------------  |
```

### Plugin switching

```
Client                          Server
  |                               |
  |  <-- HandshakeV10 ----------  |  (auth_plugin = X)
  |  --> HandshakeResponse41 -->  |
  |                               |
  |  <-- AuthSwitchRequest -----  |  (switch to plugin Y, new auth data)
  |                               |
  |  --> re-scrambled response ->  |  (using $frame->authData as new nonce)
  |                               |
  |  <-- OkPacket / ErrPacket --  |
```

---

## Testing

```bash
composer test
```

Run a specific test suite:

```bash
composer test tests/Frame/Handshake
composer test tests/Frame/Response
```

Static analysis:

```bash
composer analyze
```

---

## Protocol Reference

This library implements the MySQL Client/Server Protocol as documented in:
- [MySQL Internals Manual — Client/Server Protocol](https://dev.mysql.com/doc/internals/en/client-server-protocol.html)
- [MySQL Protocol Documentation](https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_basics.html)

### Supported Features

- Protocol Version 10 (MySQL 3.21+)
- `CLIENT_PROTOCOL_41` capability
- `CLIENT_SECURE_CONNECTION` (auth data)
- `CLIENT_PLUGIN_AUTH` (pluggable authentication)
- `CLIENT_SSL` (SSL/TLS upgrade via `SslRequest`)
- Authentication plugin switching (`AuthSwitchRequest`)
- Multi-step authentication (`AuthMoreData` — fast auth, full auth, RSA key exchange)
- `mysql_native_password` (SHA1-based)
- `caching_sha2_password` (SHA256-based, including RSA key exchange)
- Text protocol result sets
- Binary protocol result sets (prepared statements)
- Dynamic row format detection (stored procedure result sets)
- `COM_QUERY`, `COM_PING`, `COM_QUIT`, `COM_INIT_DB`
- `COM_STMT_PREPARE`, `COM_STMT_EXECUTE`, `COM_STMT_CLOSE`
- Length-encoded integers and strings
- NULL bitmap handling
- Compressed packets (`CLIENT_COMPRESS`) via `CompressedPacketReader` / `CompressedPacketWriter`
- Large payload fragmentation via `PacketFramer`

### Not Yet Supported

- `LOCAL INFILE` protocol
- `CLIENT_CONNECT_ATTRS` (connection attribute encoding)
- `COM_CHANGE_USER`

---

## Use Cases

1. **Custom MySQL Clients** — Build lightweight async or fiber-based MySQL clients
2. **Protocol Analyzers** — Parse and inspect MySQL network traffic
3. **Proxy Servers** — Create MySQL proxies for load balancing or query logging
4. **Testing Tools** — Simulate MySQL client/server interactions in unit tests
5. **Educational** — Learn how the MySQL binary protocol works at the packet level

---

## Contributing

Contributions are welcome. Please submit a pull request or open an issue on GitHub.

## License

This library is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

This library is a modernised, PHP 8.3+ implementation based on the original work by [Ivan Chepurnyi / EcomDev](https://github.com/EcomDev/php-mysql-binary-protocol).

## Related Projects

- [krowinski/php-mysql-replication](https://github.com/krowinski/php-mysql-replication) — MySQL binlog replication in PHP
- [EcomDev/php-mysql-binary-protocol](https://github.com/EcomDev/php-mysql-binary-protocol) — Original implementation this library is based on

## Support

For bugs and feature requests, please use the [GitHub issue tracker](https://github.com/rcalicdan/mysql-binary-protocol/issues).