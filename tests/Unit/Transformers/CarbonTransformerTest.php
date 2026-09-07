<?php

declare(strict_types=1);

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Transformers\CarbonTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CarbonTransformer::class)]
final class CarbonTransformerTest extends TestCase
{
    protected function tearDown(): void
    {
        GraniteConfig::reset();
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
            'invalid string' => ['not-a-date-string'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function relativeStringProvider(): array
    {
        return [
            'now' => ['now'],
            'today' => ['today'],
            'tomorrow' => ['tomorrow'],
            'yesterday' => ['yesterday'],
            'next week' => ['next week'],
            'last month' => ['last month'],
            '+1 day' => ['+1 day'],
            '-2 hours' => ['-2 hours'],
            'this year' => ['this year'],
            '2 weeks ago' => ['2 weeks ago'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function absoluteIsoStringProvider(): array
    {
        return [
            'positive offset' => ['2024-01-15T10:30:45+02:30', '2024-01-15T10:30:45+02:30'],
            'negative offset' => ['2024-01-15T10:30:45-05:00', '2024-01-15T10:30:45-05:00'],
            'UTC designator' => ['2024-01-15T10:30:45Z', '2024-01-15T10:30:45+00:00'],
        ];
    }

    /** @return array<string, array{string, string|null, string|null}> */
    public static function globalRangeTimezoneProvider(): array
    {
        return [
            'minimum in a positive timezone' => ['Pacific/Kiritimati', '2024-01-15 00:00:00', null],
            'maximum in a negative timezone' => ['Pacific/Honolulu', null, '2024-01-15 00:00:00'],
        ];
    }

    public function testTransformString(): void
    {
        $transformer = new CarbonTransformer();
        $result = $transformer->transform('2023-01-01 12:00:00');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testTransformStringToImmutable(): void
    {
        $transformer = new CarbonTransformer(immutable: true);
        $result = $transformer->transform('2023-01-01 12:00:00');

        $this->assertInstanceOf(CarbonImmutable::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testTransformWithFormat(): void
    {
        $transformer = new CarbonTransformer(format: 'd/m/Y H:i');
        $result = $transformer->transform('01/01/2023 12:00');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testTransformWithTimezone(): void
    {
        $transformer = new CarbonTransformer(timezone: 'America/New_York');
        $result = $transformer->transform('2023-01-01 12:00:00');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('America/New_York', $result->getTimezone()->getName());
    }

    public function testTransformWithLocale(): void
    {
        $transformer = new CarbonTransformer(locale: 'es');
        $result = $transformer->transform('2023-01-01 12:00:00');

        $this->assertInstanceOf(Carbon::class, $result);
        // Carbon's locale() returns a new instance with locale set
        /** @var Carbon $result */
        $this->assertEquals('es', $result->locale);
    }

    public function testTransformRelativeString(): void
    {
        $transformer = new CarbonTransformer();
        $result = $transformer->transform('tomorrow');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertTrue($result->isAfter(Carbon::now()));
    }

    public function testTransformRelativeStringDisabled(): void
    {
        $transformer = new CarbonTransformer(parseRelative: false);
        $result = $transformer->transform('tomorrow');

        $this->assertNull($result);
    }

    public function testTransformRelativeStringUsesConfiguredBaseDate(): void
    {
        $transformer = new CarbonTransformer(
            timezone: 'UTC',
            relativeBaseDate: '2024-01-01 12:00:00',
        );

        $result = $transformer->transform('tomorrow');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertSame('2024-01-02 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testTransformWithRange(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');
        $transformer = new CarbonTransformer(min: $min, max: $max);

        // Valid date within range
        $result = $transformer->transform('2023-06-15');
        $this->assertInstanceOf(Carbon::class, $result);

        // Date before min
        $result = $transformer->transform('2022-12-31');
        $this->assertNull($result);

        // Date after max
        $result = $transformer->transform('2024-01-01');
        $this->assertNull($result);
    }

    #[DataProvider('globalRangeTimezoneProvider')]
    public function test_string_range_boundaries_use_the_same_effective_global_timezone_as_the_value(
        string $timezone,
        ?string $min,
        ?string $max,
    ): void {
        GraniteConfig::getInstance()->carbonTimezone($timezone);
        $transformer = new CarbonTransformer(min: $min, max: $max);

        $result = $transformer->transform('2024-01-15 00:00:00');

        $this->assertNotNull($result);
        $this->assertSame($timezone, $result->getTimezone()->getName());
    }

    public function testTransformNull(): void
    {
        $transformer = new CarbonTransformer();
        $result = $transformer->transform(null);

        $this->assertNull($result);
    }

    public function testTransformExistingCarbon(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $transformer = new CarbonTransformer();
        $result = $transformer->transform($carbon);

        $this->assertSame($carbon, $result);
    }

    public function testTransformDateTime(): void
    {
        $dateTime = new DateTimeImmutable('2023-01-01 12:00:00');
        $transformer = new CarbonTransformer();
        $result = $transformer->transform($dateTime);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2023-01-01 12:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testTransformTimestamp(): void
    {
        $timestamp = 1672574400; // 2023-01-01 12:00:00 UTC
        $transformer = new CarbonTransformer();
        $result = $transformer->transform($timestamp);

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals($timestamp, $result->getTimestamp());
    }

    public function testSerialize(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $transformer = new CarbonTransformer();
        $result = $transformer->serialize($carbon);

        $this->assertIsString($result);
        $this->assertStringContainsString('2023-01-01T12:00:00', $result);
    }

    public function testSerializeWithFormat(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $transformer = new CarbonTransformer(serializeFormat: 'Y-m-d H:i:s');
        $result = $transformer->serialize($carbon);

        $this->assertEquals('2023-01-01 12:00:00', $result);
    }

    public function testSerializeWithTimezone(): void
    {
        $carbon = Carbon::parse('2023-01-01 12:00:00', 'UTC');
        $transformer = new CarbonTransformer(serializeTimezone: 'America/New_York');
        $result = $transformer->serialize($carbon);

        $this->assertStringContainsString('07:00:00', $result); // UTC-5 offset
    }

    public function testSerializeNull(): void
    {
        $transformer = new CarbonTransformer();
        $result = $transformer->serialize(null);

        $this->assertNull($result);
    }

    public function testGettersReturnCorrectValues(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');

        $transformer = new CarbonTransformer(
            format: 'Y-m-d',
            timezone: 'America/New_York',
            locale: 'es',
            immutable: true,
            parseRelative: false,
            serializeFormat: 'Y-m-d H:i:s',
            serializeTimezone: 'Europe/Madrid',
            min: $min,
            max: $max,
        );

        $this->assertEquals('Y-m-d', $transformer->getFormat());
        $this->assertEquals('America/New_York', $transformer->getTimezone());
        $this->assertEquals('es', $transformer->getLocale());
        $this->assertTrue($transformer->isImmutable());
        $this->assertFalse($transformer->isParseRelativeEnabled());
        $this->assertEquals('Y-m-d H:i:s', $transformer->getSerializeFormat());
        $this->assertEquals('Europe/Madrid', $transformer->getSerializeTimezone());
        $this->assertSame($min, $transformer->getMin());
        $this->assertSame($max, $transformer->getMax());
    }

    public function testTransformUsesGlobalConfig(): void
    {
        $config = GraniteConfig::getInstance();
        $config->carbonTimezone('Europe/Madrid');
        $config->carbonParseFormat('d/m/Y');

        $transformer = new CarbonTransformer();
        $result = $transformer->transform('01/01/2023');

        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('Europe/Madrid', $result->getTimezone()->getName());
    }

    public function testSerializeUsesGlobalConfig(): void
    {
        $config = GraniteConfig::getInstance();
        $config->carbonSerializeFormat('Y-m-d');

        $carbon = Carbon::parse('2023-01-01 12:00:00');
        $transformer = new CarbonTransformer();
        $result = $transformer->serialize($carbon);

        $this->assertEquals('2023-01-01', $result);
    }

    #[DataProvider('invalidInputProvider')]
    public function testTransformHandlesInvalidInputGracefully(mixed $input): void
    {
        $transformer = new CarbonTransformer();
        $result = $transformer->transform($input);

        $this->assertNull($result);
    }

    #[DataProvider('relativeStringProvider')]
    public function testRelativeStringsAreRejectedWhenParsingIsDisabled(string $input): void
    {
        $transformer = new CarbonTransformer(parseRelative: false);

        $this->assertNull($transformer->transform($input));
    }

    #[DataProvider('absoluteIsoStringProvider')]
    public function testAbsoluteIsoStringsWithOffsetsAreAcceptedWhenRelativeParsingIsDisabled(
        string $input,
        string $expected,
    ): void {
        $transformer = new CarbonTransformer(parseRelative: false);

        $this->assertSame($expected, $transformer->transform($input)?->format('Y-m-d\TH:i:sP'));
    }
}
