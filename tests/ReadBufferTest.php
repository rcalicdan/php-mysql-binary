<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MySQLBinaryProtocol;


use PHPUnit\Framework\TestCase;

class ReadBufferTest extends TestCase
{
    /** @var ReadBuffer */
    private $readBuffer;

    protected function setUp(): void
    {
        $this->readBuffer = new ReadBuffer();
    }


    /** @test */
    /**

     * @test

     */

    public function testReadsBufferByLength()
    {
        $this->readBuffer->append('Some string');

        $this->assertEquals('Some', $this->readBuffer->read(4));
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReadsBufferByMovingPositionForward()
    {
        $this->readBuffer->append('TDD is awesome');

        $this->readBuffer->read(3);

        $this->assertEquals(' is awesome', $this->readBuffer->read(11));
    }

    /** @test */
    /**

     * @test

     */

    public function testThrowsIncompleteBufferExceptionWhenNotBufferIsSmallerThenReadSize()
    {
        $this->readBuffer->append('TDD is');

        $this->expectException(IncompleteBufferException::class);

        $this->readBuffer->read(11);
    }
    
    /** @test */
    /**

     * @test

     */

    public function testThrowIncompleteBufferExceptionWhenNotEnoughDataIsLeftToRead()
    {
        $this->readBuffer->append('TDD is great');

        $this->readBuffer->read(7);

        $this->expectException(IncompleteBufferException::class);

        $this->readBuffer->read(7);
    }
    
    /** @test */
    /**

     * @test

     */

    public function testAllowsToReadAllAddedPiecesToBuffer()
    {
        $this->readBuffer->append('TDD is');

        $this->readBuffer->read(4);

        $this->readBuffer->append(' great');

        $this->assertEquals('is great', $this->readBuffer->read(8));
    }

    /** @test */
    /**

     * @test

     */

    public function testIsReadableWhenAskedBytesAreBelowBufferLength()
    {
        $this->readBuffer->append('Some data');

        $this->assertEquals(true, $this->readBuffer->isReadable(4));
    }

    /** @test */
    /**

     * @test

     */

    public function testIsNotReadableWhenBytesAreLongerThenBufferLength()
    {
        $this->readBuffer->append('Some');

        $this->assertEquals(false, $this->readBuffer->isReadable(5));
    }
    
    /** @test */
    /**

     * @test

     */

    public function testIsNotReadableWhenAskedLengthIsLowerThenRemainingBytesToRead()
    {
        $this->readBuffer->append('Some data');
        $this->readBuffer->read(5);

        $this->assertEquals(false, $this->readBuffer->isReadable(5));
    }

    /** @test */
    /**

     * @test

     */

    public function testIsReadableWhenExactAmountOfBytesAvailableToRead()
    {
        $this->readBuffer->append('Data in buffer');

        $this->readBuffer->read(7);

        $this->assertEquals(true, $this->readBuffer->isReadable(7));
    }

    /** @test */
    /**

     * @test

     */

    public function testAllowsToReadDataAgainIfPreviousSessionWasNotReadCompletely()
    {
        $this->readBuffer->append('Data in buffer');
        $this->readBuffer->read(4);
        $this->readBuffer->read(4);

        try {
            $this->readBuffer->read(7);
        } catch (IncompleteBufferException $exception) { }

        $this->assertEquals('Data in ', $this->readBuffer->read(8));
    }

    /** @test */
    /**

     * @test

     */

    public function testAllowsToMoveReadBufferPointerAfterRead()
    {
        $this->readBuffer->append('Data in buffer');

        $this->readBuffer->read(5);
        $this->readBuffer->flush();

        try {
            $this->readBuffer->read(10);
        } catch (IncompleteBufferException $e) {

        }

        $this->assertEquals('in buffer', $this->readBuffer->read(9));
    }
    
    /** @test */
    /**

     * @test

     */

    public function testClearsBufferWhenReadLimitIsReached()
    {
        $limitedReadBuffer = new ReadBuffer(20);

        $limitedReadBuffer->append('Some data to read 2 remainder of buffer');
        $limitedReadBuffer->read(10);
        $limitedReadBuffer->read(10);
        $limitedReadBuffer->flush();

        $expectedReadBuffer = new ReadBuffer(20);
        $expectedReadBuffer->append('remainder of buffer');

        $this->assertEquals($expectedReadBuffer, $limitedReadBuffer);
    }

    /** @test */
    /**

     * @test

     */

    public function testClearsBufferLimitIsReachedALongTimeAgo()
    {
        $limitedReadBuffer = new ReadBuffer(20);

        $limitedReadBuffer->append('Some data to read 2 very long string to read remainder of buffer');
        $limitedReadBuffer->read(10);
        $limitedReadBuffer->flush();
        $limitedReadBuffer->read(20);
        $limitedReadBuffer->read(15);
        $limitedReadBuffer->flush();

        $expectedReadBuffer = new ReadBuffer(20);
        $expectedReadBuffer->append('remainder of buffer');

        $this->assertEquals($expectedReadBuffer, $limitedReadBuffer);
    }

    /** @test */
    /**

     * @test

     */

    public function testFlushReturnsNumberOfReadBytes()
    {
        $this->readBuffer->append('Some data');
        $this->readBuffer->read(4);
        $this->readBuffer->read(2);
        $this->assertEquals(6, $this->readBuffer->flush());
    }
    
    /** @test */
    /**

     * @test

     */

    public function testFlushReturnsZeroWhenNoBytesRead()
    {
        $this->readBuffer->append('Some data');

        $this->assertEquals(0, $this->readBuffer->flush());
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReturnsLengthOfReadInOrderToReadDataUpToThisCharacter()
    {
        $this->readBuffer->append('some:data');

        $this->assertEquals(5, $this->readBuffer->scan(':'));
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReturnsNegativeIndexWhenNoMatchFoundForScan()
    {
        $this->readBuffer->append('some data without character');

        $this->assertEquals(-1, $this->readBuffer->scan(':'));
    }

    /** @test */
    /**

     * @test

     */

    public function testReturnsLengthOfReadEvenIfCharacterForSearchIsAFirstOneInBuffer()
    {
        $this->readBuffer->append(':some other data');

        $this->assertEquals(1, $this->readBuffer->scan(':'));
    }
    
    /** @test */
    /**

     * @test

     */

    public function testReturnsLengthOfRequiredReadForTheNextCharacterOccurrence()
    {
        $this->readBuffer->append('some:other:data');
        $this->readBuffer->read(5);

        $this->assertEquals(6, $this->readBuffer->scan(':'));
    }

    /** @test */
    /**

     * @test

     */

    public function testDefaultReadPositionInBufferIsZero()
    {
        $this->assertEquals(0, $this->readBuffer->currentPosition());
    }
    
    /** @test */
    /**

     * @test

     */

    public function testCurrentPositionIsMovedWithNumberOfReadBytes()
    {
        $this->readBuffer->append('Some very long string data');

        $this->readBuffer->read(4);
        $this->readBuffer->read(6);

        $this->assertEquals(10, $this->readBuffer->currentPosition());
    }
    
    /** @test */
    /**

     * @test

     */

    public function testCurrentPositionIsRelativeToFlushedReadData()
    {
        $this->readBuffer->append('Some very long string data');

        $this->readBuffer->read(10);

        $this->readBuffer->flush();

        $this->readBuffer->read(3);

        $this->assertEquals(3, $this->readBuffer->currentPosition());

    }
}
