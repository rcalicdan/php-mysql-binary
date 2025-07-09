<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MySQLBinaryProtocol;

use PHPUnit\Framework\TestCase;

class PacketReaderTest extends TestCase
{
    /**
     * @var PacketReader
     */
    private $reader;

    protected function setUp(): void
    {
        $this->reader = (new DefaultPacketReaderFactory())->createWithDefaultSettings();
    }

    /** @test */
    /**

     * @test

     */

    public function testAllowsToReadSingleByteIntegerFromPayload()
    {
        $this->reader->append("\x01\x00\x00\x00\xF1");

        $data = [];
        $this->reader->readPayload(function (PayloadReader $fragment) use (&$data) {
            $data[] = $fragment->readFixedInteger(1);
        });

        $this->assertEquals([241], $data);
    }

    /** @test */
    /**

     * @test

     */

    public function testAllowsToReadMultiplePacketsOfIntegers()
    {
        $this->reader->append("\x01\x00\x00\x00\xF1");
        $this->reader->append("\x01\x00\x00\x00\xF2");
        $this->reader->append("\x01\x00\x00\x00\xF3");
        $this->reader->append("\x01\x00\x00\x00\xF4");

        $data = [];
        $readOneByte = function (PayloadReader $fragment) use (&$data) {
            $data[] = $fragment->readFixedInteger(1);
        };

        $this->reader->readPayload($readOneByte);
        $this->reader->readPayload($readOneByte);
        $this->reader->readPayload($readOneByte);
        $this->reader->readPayload($readOneByte);

        $this->assertEquals([241, 242, 243, 244], $data);
    }

    /** @test */
    /**

     * @test

     */

    public function testReportsFragmentIsRead()
    {
        $this->reader->append("\x01\x00\x00\x00\x01");

        $this->assertEquals(
            true,
            $this->reader->readPayload(function (PayloadReader $reader) {
                $reader->readFixedInteger(1);
            })
        );
    }

    /** @test */
    /**

     * @test

     */

    public function testReportsFragmentIsNotRead()
    {
        $this->reader->append("\x02\x00\x00\x00\x01");

        $this->assertEquals(
            false,
            $this->reader->readPayload(function (PayloadReader $reader) {
                $reader->readFixedInteger(1);
                $reader->readFixedInteger(1);
            })
        );
    }

    /** @test */
    /**

     * @test

     */

    public function testAllowsReadingVariousFixedIntegers()
    {
        $this->reader->append("\x0D\x00\x00\x00\x00\x02\x02\x00\x00\x00\x00\x00\x00\x00\x00\xF0\x00");

        $data = $this->readPayload(function (PayloadReader $fragment) {
            return [
                $fragment->readFixedInteger(2), // 512
                $fragment->readFixedInteger(3), // 2
                $fragment->readFixedInteger(8), // 67553994410557440
            ];
        });

        $this->assertEquals(
            [
                512,
                2,
                67553994410557440
            ],
            $data
        );
    }
    
    /** @test */
    /**

     * @test

     */

    public function testAllowsReadingDifferentLengthEncodedIntegers()
    {
        $this->reader->append(
            "\x12\x00\x00\x00\xf9\xfa\xfc\xfb\00\xfd\xff\xff\xf0\xfe\xff\xff\xff\xff\xff\xff\xff\xf0"
        );

        $data = $this->readPayload(function (PayloadReader $fragment) {
            return [
                $fragment->readLengthEncodedIntegerOrNull(), // 249
                $fragment->readLengthEncodedIntegerOrNull(), // 250
                $fragment->readLengthEncodedIntegerOrNull(), // 251
                $fragment->readLengthEncodedIntegerOrNull(), // 15794175
                $fragment->readLengthEncodedIntegerOrNull(), // 17365880163140632575
            ];
        });

        $this->assertEquals(
            [
                249,
                250,
                251,
                15794175,
                17365880163140632575
            ],
            $data
        );
    }

    /** @test */
    /**

     * @test

     */

    public function testThrowsInvalidBinaryDataExceptionWhenLengthEncodedIntegerDoesNotMatchExpectedFormat()
    {

        $this->reader->append(
            "\x09\x00\x00\x00\xff\xff\xff\xff\xff\xff\xff\xff\xf0"
        );

        $this->expectException(InvalidBinaryDataException::class);

        $this->reader->readPayload(function (PayloadReader $fragment) {
            $fragment->readLengthEncodedIntegerOrNull();
        });
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReadsNullForLengthEncodedInteger()
    {
        $this->reader->append(
            "\x01\x00\x00\x00\xfb"
        );

        $this->assertEquals(
            null,
            $this->readPayload(function (PayloadReader $fragment) {
                return $fragment->readLengthEncodedIntegerOrNull();
            })
        );
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReadsFixedLengthString()
    {
        $this->reader->append("\x18\x00\x00\x00helloworld!awesomestring");

        $data = $this->readPayload(function (PayloadReader $fragment) {
            return [
                $fragment->readFixedString(5),
                $fragment->readFixedString(6),
                $fragment->readFixedString(13)
            ];
        });

        $this->assertEquals(
            [
                'hello',
                'world!',
                'awesomestring'
            ],
            $data
        );
    }

    /** @test */
    /**

     * @test

     */

    public function testReadsLengthEncodedString()
    {
        $veryLongString = str_repeat('a', 0xff);


        $this->reader->append("\x0c\x01\x00\x00\xfc\xff\x00$veryLongString\x05hello\xfb\x0202");

        $data = $this->readPayload(function (PayloadReader $fragment) {
            return [
                $fragment->readLengthEncodedStringOrNull(),
                $fragment->readLengthEncodedStringOrNull(),
                $fragment->readLengthEncodedStringOrNull(),
                $fragment->readLengthEncodedStringOrNull()
            ];
        });


        $this->assertEquals(
            [
                $veryLongString,
                'hello',
                null,
                '02'
            ],
            $data
        );
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReadsMultipleNullTerminatedStrings()
    {
        $this->reader->append("\x31\x00\x00\x00first_string\x00second_string\x00third_string\x00");
        $data = $this->readPayload(function (PayloadReader $fragmentReader) {
            return [
                $fragmentReader->readNullTerminatedString(),
                $fragmentReader->readNullTerminatedString(),
                $fragmentReader->readNullTerminatedString()
            ];
        });

        $this->assertEquals(
            [
                'first_string',
                'second_string',
                'third_string'
            ],
            $data
        );

    }
    
    /** @test */
    /**

     * @test

     */

    public function testStopsReadingPayloadWhenNullCharacterIsNotFoundForAString()
    {
        $this->reader->append("\x31\x00\x00\x00first_string");
        $data = $this->readPayload(function (PayloadReader $fragmentReader) {
            return $fragmentReader->readNullTerminatedString();
        });

        $this->assertEquals(null, $data);
    }

    /** @test */
    /**

     * @test

     */

    public function testReportIncompletePayloadReadWhenNullTerminatedStringIsNotCompletelyRead()
    {
        $this->reader->append("\x31\x00\x00\x00first_string");

        $this->assertEquals(false, $this->reader->readPayload(function (PayloadReader $fragmentReader) {
            $fragmentReader->readNullTerminatedString();
        }));
    }

    /** @test */
    /**

     * @test

     */

    public function testReadsStringThatRepresentsCompletePacket()
    {
        $this->reader->append("\x0a\x00\x00\x00This is 10\x00\x00\x00\x00");

        $this->assertEquals('This is 10', $this->readPayload(function (PayloadReader $payloadReader) {
            return $payloadReader->readRestOfPacketString();
        }));
    }

    /** @test */
    /**

     * @test

     */

    public function testReadsStringThatRepresentsRemainderOfThePacket()
    {
        $this->reader->append("\x0C\x00\x00\x00\x01\x02This is 10\x00\x00\x00\x00");

        $this->assertEquals('This is 10', $this->readPayload(function (PayloadReader $payloadReader) {
            $payloadReader->readFixedInteger(1);
            $payloadReader->readFixedInteger(1);
            return $payloadReader->readRestOfPacketString();
        }));
    }

    /** @test */
    /**

     * @test

     */

    public function testReadMultiplePacketsPacketStringsAddedAsSingleNetworkPacketInSinglePayload()
    {
        $this->reader->append("\x03\x00\x00\x00one\x03\x00\x00\x00two\x05\x00\x00\x00three\x04\x00\x00\x00four");

        $this->assertEquals(
            [
                'one',
                'two',
                'three',
                'four'
            ],
            $this->readPayload(function (PayloadReader $payloadReader) {
                return [
                    $payloadReader->readRestOfPacketString(),
                    $payloadReader->readRestOfPacketString(),
                    $payloadReader->readRestOfPacketString(),
                    $payloadReader->readRestOfPacketString()
                ];
            })
        );
    }

    /** @test */
    /**

     * @test

     */

    public function testReadMultiplePacketsPacketStringsAddedAsSingleNetworkPacketInMultiplePayloads()
    {
        $this->reader->append("\x03\x00\x00\x00one\x03\x00\x00\x00two\x05\x00\x00\x00three\x04\x00\x00\x00four");


        $readString = function (PayloadReader $payloadReader) {
            return $payloadReader->readRestOfPacketString();
        };

        $this->assertEquals(
            [
                'one',
                'two',
                'three',
                'four'
            ],
            [
                $this->readPayload($readString),
                $this->readPayload($readString),
                $this->readPayload($readString),
                $this->readPayload($readString)
            ]
        );
    }

    /** @test */
    /**

     * @test

     */

    public function testProvidesSequenceNumberAndPacketLengthDuringReadingOfPayload()
    {
        $this->reader
            ->append("\x08\x00\x00\x00one\x00two\x00\x06\x00\x00\x01three\x00\x05\x00\x00\x05four\x00");


        $readString = function (PayloadReader $payloadReader, int $length, int $sequence) {
            $payloadReader->readNullTerminatedString();

            return [$length, $sequence];
        };

        $this->assertEquals(
            [
                [8, 0],
                [8, 0],
                [6, 1],
                [5, 5]
            ],
            [
                $this->readPayload($readString),
                $this->readPayload($readString),
                $this->readPayload($readString),
                $this->readPayload($readString),
            ]
        );
    }

    private function readPayload(callable $fragmentCallable)
    {
        $this->reader->readPayload(function (...$args) use ($fragmentCallable, &$data) {
            $data = $fragmentCallable(...$args);
        });

        return $data;
    }
}
