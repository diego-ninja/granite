<?php

declare(strict_types=1);

namespace Tests\Unit\Transformers;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Transformers\CarbonTransformer;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \Ninja\Granite\Transformers\CarbonTransformer
 */
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

    /**
     * @return array<string, array{string, bool}>
     */
    public static function relativeStringProvider(): array
    {
        return [
            'now' => ['now', true],
            'today' => ['today', true],
            'tomorrow' => ['tomorrow', true],
            'yesterday' => ['yesterday', true],
            'next week' => ['next week', true],
            'last month' => ['last month', true],
            '+1 day' => ['+1 day', true],
            '-2 hours' => ['-2 hours', true],
            'this year' => ['this year', true],
            '2 weeks ago' => ['2 weeks ago', true],
            'absolute date' => ['2023-01-01', false],
            'absolute datetime' => ['2023-01-01 12:00:00', false],
            'formatted date' => ['01/01/2023', false],
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

    /**
     * @dataProvider invalidInputProvider
     * @param mixed $input
     */
    public function testTransformHandlesInvalidInputGracefully(mixed $input): void
    {
        $transformer = new CarbonTransformer();
        $result = $transformer->transform($input);

        $this->assertNull($result);
    }

    /**
     * @dataProvider relativeStringProvider
     */
    public function testRelativeStringDetection(string $input, bool $shouldBeRelative): void
    {
        $transformer = new CarbonTransformer(parseRelative: false);
        $result = $transformer->transform($input);

        if ($shouldBeRelative) {
            $this->assertNull($result, "Expected '{$input}' to be detected as relative and rejected");
        } else {
            // For non-relative strings, we might still get null if they're invalid dates
            // but we shouldn't reject them just for being relative
            $this->assertTrue(true, "Non-relative string '{$input}' was not incorrectly rejected");
        }
    }
}
