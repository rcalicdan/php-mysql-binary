<?php

use Rcalicdan\MySQLBinaryProtocol\Constants\MysqlType;
use Rcalicdan\MySQLBinaryProtocol\Frame\Command\ParameterBuilder;

beforeEach(function () {
    $this->builder = new ParameterBuilder();
});

test('builds empty params for empty array', function () {
    $result = $this->builder->build([]);

    expect($result->nullBitmap)->toBe('')
        ->and($result->types)->toBe('')
        ->and($result->values)->toBe('');
});

test('builds params for basic types', function () {
    $params = ['hello', 123, 1.5];
    $result = $this->builder->build($params);

    expect($result->nullBitmap)->toBe("\x00");

    $expectedTypes = pack('v', MysqlType::VAR_STRING)
                   . pack('v', MysqlType::LONGLONG)
                   . pack('v', MysqlType::DOUBLE);
    expect($result->types)->toBe($expectedTypes);

    $expectedValues = "\x05hello" 
                    . pack('P', 123)
                    . pack('e', 1.5);
    expect($result->values)->toBe($expectedValues);
});

test('builds null bitmap correctly for a single null', function () {
    $params = [null];
    $result = $this->builder->build($params);

    expect($result->nullBitmap)->toBe("\x01");

    expect($result->types)->toBe(pack('v', MysqlType::NULL));

    expect($result->values)->toBe('');
});

test('builds null bitmap correctly with mixed values', function () {
    $params = ['a', null, 'c'];
    $result = $this->builder->build($params);

    expect($result->nullBitmap)->toBe("\x02");
});

test('builds null bitmap correctly for more than 8 parameters', function () {
    $params = [
        'p0', null, 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 
        null, 'p9'                                
    ];
    $result = $this->builder->build($params);

    expect($result->nullBitmap)->toBe("\x02\x01");
});