<?php

use Rcalicdan\MySQLBinaryProtocol\Factory\DefaultPacketWriterFactory;
use Rcalicdan\MySQLBinaryProtocol\Packet\UncompressedPacketWriter;

test('creates uncompressed packet writer with default settings', function () {
    $factory = new DefaultPacketWriterFactory();
    $writer = $factory->createWithDefaultSettings();

    expect($writer)->toBeInstanceOf(UncompressedPacketWriter::class);
});