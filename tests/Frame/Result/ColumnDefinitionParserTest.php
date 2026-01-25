<?php

declare(strict_types=1);

use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinition;
use Rcalicdan\MySQLBinaryProtocol\Frame\Result\ColumnDefinitionParser;

test('parses column definition correctly', function () {
    $payload = buildColumnPayload('username', 'users', MysqlType::VAR_STRING);
    $reader = createReader($payload);

    $parser = new ColumnDefinitionParser();
    /** @var ColumnDefinition $col */
    $col = $parser->parse($reader, strlen($payload), 1);

    expect($col)->toBeInstanceOf(ColumnDefinition::class)
        ->and($col->name)->toBe('username')
        ->and($col->table)->toBe('users')
        ->and($col->schema)->toBe('test_db')
        ->and($col->type)->toBe(MysqlType::VAR_STRING)
    ;
});
