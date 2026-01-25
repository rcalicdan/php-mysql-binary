<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\Command;
use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Command\CommandBuilder;

beforeEach(function () {
    $this->builder = new CommandBuilder();
});

test('builds query packet', function () {
    $sql = 'SELECT * FROM users';
    $packet = $this->builder->buildQuery($sql);

    expect(ord($packet[0]))->toBe(Command::QUERY)
        ->and(substr($packet, 1))->toBe($sql)
    ;
});

test('builds ping packet', function () {
    $packet = $this->builder->buildPing();

    expect(strlen($packet))->toBe(1)
        ->and(ord($packet[0]))->toBe(Command::PING)
    ;
});

test('builds quit packet', function () {
    $packet = $this->builder->buildQuit();

    expect(strlen($packet))->toBe(1)
        ->and(ord($packet[0]))->toBe(Command::QUIT)
    ;
});

test('builds init db packet', function () {
    $db = 'my_database';
    $packet = $this->builder->buildInitDb($db);

    expect(ord($packet[0]))->toBe(Command::INIT_DB)
        ->and(substr($packet, 1))->toBe($db)
    ;
});

test('builds statement prepare packet', function () {
    $sql = 'SELECT ? FROM tbl WHERE id = ?';
    $packet = $this->builder->buildStmtPrepare($sql);

    expect($packet)->toBe(chr(Command::STMT_PREPARE) . $sql);
});

test('builds statement close packet', function () {
    $statementId = 10;
    $packet = $this->builder->buildStmtClose($statementId);

    $expected = chr(Command::STMT_CLOSE) . pack('V', $statementId);
    expect($packet)->toBe($expected);
});

test('builds statement execute packet without parameters', function () {
    $statementId = 1;
    $packet = $this->builder->buildStmtExecute($statementId, []);

    $expected = chr(Command::STMT_EXECUTE) . pack('V', 1) . "\x00" . pack('V', 1);
    expect($packet)->toBe($expected);
});

test('builds statement execute packet with parameters', function () {
    $statementId = 1;
    $params = ['test', 100];
    $packet = $this->builder->buildStmtExecute($statementId, $params);

    $header = chr(Command::STMT_EXECUTE) . pack('V', 1) . "\x00" . pack('V', 1);

    $nullBitmap = "\x00";
    $newParamsFlag = "\x01";
    $types = pack('v', MysqlType::VAR_STRING) . pack('v', MysqlType::LONGLONG);
    $values = "\x04test" . pack('P', 100);

    $expected = $header . $nullBitmap . $newParamsFlag . $types . $values;
    expect($packet)->toBe($expected);
});
