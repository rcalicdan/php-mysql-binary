<?php

use Rcalicdan\MySQLBinaryProtocol\Constants\CharsetIdentifiers;
use Rcalicdan\MySQLBinaryProtocol\Constants\StatusFlags;
use Rcalicdan\MySQLBinaryProtocol\Factory\DefaultPacketReaderFactory;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeParser;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeV10;
use Rcalicdan\MySQLBinaryProtocol\Frame\Handshake\HandshakeV10Builder;

beforeEach(function () {
    $this->frameBuilder = new HandshakeV10Builder();
    $this->packetReader = (new DefaultPacketReaderFactory())->createWithDefaultSettings();
    $this->frames = [];
    
    $this->parser = new HandshakeParser(
        $this->frameBuilder,
        function (HandshakeV10 $handshake) {
            $this->frames[] = $handshake;
        }
    );
});

test('parses MySQL 8 handshake init message', function () {
    $this->packetReader->append(
        "\x4a\x00\x00\x00\x0a8.0.16\x00\x0d\x00\x00\x00\x10\x4a\x12\x05"
        . "\x71\x5d\x78\x63\x00\xff\xff\xff\x02\x00\xff\xc3\x15\x00\x00\x00"
        . "\x00\x00\x00\x00\x00\x00\x00\x6e\x48\x49\x48\x56\x78\x42\x33\x76"
        . "\x39\x3d\x5c\x00\x63\x61\x63\x68\x69\x6e\x67\x5f\x73\x68\x61\x32"
        . "\x5f\x70\x61\x73\x73\x77\x6f\x72\x64\x00"
    );

    $this->packetReader->readPayload($this->parser);

    $expected = $this->frameBuilder
        ->withServerInfo('8.0.16', 13)
        ->withStatus(StatusFlags::SERVER_STATUS_AUTOCOMMIT)
        ->withCharset(CharsetIdentifiers::UTF8MB4)
        ->withCapabilities(0xc3ffffff)
        ->withAuthData(
            "\x10\x4a\x12\x05\x71\x5d\x78\x63\x6e\x48\x49\x48\x56\x78\x42\x33\x76\x39\x3d\x5c\x00"
        )
        ->withAuthPlugin("caching_sha2_password")
        ->build();

    expect(current($this->frames))->toEqual($expected);
});