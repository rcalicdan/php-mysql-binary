<?php

use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BinaryIntegerReader;
use Rcalicdan\MySQLBinaryProtocol\Buffer\Reader\BufferPayloadReaderFactory;

uses(PHPUnit\Framework\TestCase::class)->in('.');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});


function binaryReader(): BinaryIntegerReader
{
    return new BinaryIntegerReader();
}

function createReader(string $data) {
    return (new BufferPayloadReaderFactory())->createFromString($data);
}
