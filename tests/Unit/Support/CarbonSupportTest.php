<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Support\CarbonSupport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CarbonSupport::class)]
final class CarbonSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonSupport::resetCache();
    }

    protected function tearDown(): void
    {
        CarbonSupport::resetCache();
        parent::tearDown();
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'boolean' => [true],
            'array' => [[]],
            'object' => [new stdClass()],
            'invalid string' => ['not-a-date'],
            // Note: extreme negative timestamps can actually create valid Carbon instances
        ];
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue(CarbonSupport::isAvailable());
    }

    public function testIsImmutableAvailable(): void
    {
        $this->assertTrue(CarbonSupport::isImmutableAvailable());
    }

    public function testIsCarbon(): void
    {
        $this->assertTrue(CarbonSupport::isCarbon('Carbon\Carbon'));
        $this->assertFalse(CarbonSupport::isCarbon('Carbon\CarbonImmutable'));
        $this->assertFalse(CarbonSupport::isCarbon('DateTime'));
    }

    public function testIsCarbonImmutable(): void
    {
        $this->assertTrue(CarbonSupport::isCarbonImmutable('Carbon\CarbonImmutable'));
        $this->assertFalse(CarbonSupport::isCarbonImmutable('Carbon\Carbon'));
        $this->assertFalse(CarbonSupport::isCarbonImmutable('DateTimeImmutable'));
    }

    public function testIsCarbonClass(): void
    {
        $this->assertTrue(CarbonSupport::isCarbonClass('Carbon\Carbon'));
        $this->assertTrue(CarbonSupport::isCarbonClass('Carbon\CarbonImmutable'));
        $this->assertFalse(CarbonSupport::isCarbonClass('DateTime'));
        $this->assertFalse(CarbonSupport::isCarbonClass('DateTimeImmutable'));
    }

    public function testIsCarbonInstance(): void
    {
        $carbon = Carbon::now();
        $carbonImmutable = CarbonImmutable::now();
        $dateTime = new DateTimeImmutable();

        $this->assertTrue(CarbonSupport::isCarbonInstance($carbon));
        $this->assertTrue(CarbonSupport::isCarbonInstance($carbonImmutable));
        $this->assertFalse(CarbonSupport::isCarbonInstance($dateTime));
        $this->assertFalse(CarbonSupport::isCarbonInstance('2023-01-01'));
        $this->assertFalse(CarbonSupport::isCarbonInstance(null));
    }

    public function testCreateFromString(): void
    {
        $result = CarbonSupport::create('2023-01-01 12:00:00');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCreateImmutableFromString(): void
    {
        $result = CarbonSupport::create('2023-01-01 12:00:00', null, null, true);

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCreateWithFormat(): void
    {
        $result = CarbonSupport::create('01/01/2023', 'd/m/Y');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2023-01-01', $result->format('Y-m-d'));
    }

    public function testCreateWithTimezone(): void
    {
        $result = CarbonSupport::create('2023-01-01 12:00:00', null, 'America/New_York');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('America/New_York', $result->getTimezone()->getName());
    }

    public function testCreateFromTimestamp(): void
    {
        $timestamp = 1672574400; // 2023-01-01 12:00:00 UTC
        $result = CarbonSupport::create($timestamp);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals($timestamp, $result->getTimestamp());
    }

    public function testCreateFromCarbonInstance(): void
    {
        $original = Carbon::parse('2023-01-01 12:00:00');
        $result = CarbonSupport::create($original);

        $this->assertSame($original, $result);
    }

    public function testCreateFromCarbonInstanceConvertToImmutable(): void
    {
        $original = Carbon::parse('2023-01-01 12:00:00');
        $result = CarbonSupport::create($original, null, null, true);

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertEquals($original->format('Y-m-d H:i:s'), $result->format('Y-m-d H:i:s'));
    }

    public function testCreateFromDateTimeInterface(): void
    {
        $dateTime = new DateTimeImmutable('2023-01-01 12:00:00');
        $result = CarbonSupport::create($dateTime);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testCreateReturnsNullForInvalidInput(): void
    {
        $this->assertNull(CarbonSupport::create('invalid-date'));
        $this->assertNull(CarbonSupport::create([]));
        $this->assertNull(CarbonSupport::create(new stdClass()));
    }

    public function testCreateReturnsNullForNull(): void
    {
        $this->assertNull(CarbonSupport::create(null));
    }

    public function testCreateCarbon(): void
    {
        $result = CarbonSupport::createCarbon('2023-01-01 12:00:00');

        $this->assertInstanceOf(Carbon::class, $result);
    }

    public function testCreateImmutable(): void
    {
        $result = CarbonSupport::createImmutable('2023-01-01 12:00:00');

        $this->assertInstanceOf(CarbonImmutable::class, $result);
    }

    public function testGetPreferredCarbonClass(): void
    {
        $this->assertEquals('Carbon\Carbon', CarbonSupport::getPreferredCarbonClass());
        $this->assertEquals('Carbon\CarbonImmutable', CarbonSupport::getPreferredCarbonClass(true));
    }

    public function testSerialize(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $result = CarbonSupport::serialize($carbon);

        $this->assertIsString($result);
        $this->assertStringContainsString('2023-01-01T12:00:00', $result);
    }

    public function testSerializeWithFormat(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $result = CarbonSupport::serialize($carbon, 'Y-m-d H:i:s');

        $this->assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testSerializeWithTimezone(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00', 'UTC');
        $result = CarbonSupport::serialize($carbon, 'Y-m-d H:i:s', 'America/New_York');

        $this->assertStringContainsString('07:00:00', $result); // UTC-5 offset
    }

    /**
     * @dataProvider invalidInputProvider
     * @param mixed $input
     */
    public function testCreateHandlesInvalidInputGracefully(mixed $input): void
    {
        $result = CarbonSupport::create($input);
        $this->assertNull($result);
    }
}
