<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Attributes;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Transformers\CarbonTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CarbonDate::class)]
final class CarbonDateTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attribute = new CarbonDate();

        $this->assertNull($attribute->format);
        $this->assertNull($attribute->timezone);
        $this->assertNull($attribute->locale);
        $this->assertFalse($attribute->immutable);
        $this->assertTrue($attribute->parseRelative);
        $this->assertNull($attribute->serializeFormat);
        $this->assertNull($attribute->serializeTimezone);
        $this->assertNull($attribute->min);
        $this->assertNull($attribute->max);
    }

    public function testConstructorWithAllParameters(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');

        $attribute = new CarbonDate(
            format: 'Y-m-d H:i:s',
            timezone: 'America/New_York',
            locale: 'es',
            immutable: true,
            parseRelative: false,
            serializeFormat: 'Y-m-d',
            serializeTimezone: 'Europe/Madrid',
            min: $min,
            max: $max,
        );

        $this->assertEquals('Y-m-d H:i:s', $attribute->format);
        $this->assertEquals('America/New_York', $attribute->timezone);
        $this->assertEquals('es', $attribute->locale);
        $this->assertTrue($attribute->immutable);
        $this->assertFalse($attribute->parseRelative);
        $this->assertEquals('Y-m-d', $attribute->serializeFormat);
        $this->assertEquals('Europe/Madrid', $attribute->serializeTimezone);
        $this->assertSame($min, $attribute->min);
        $this->assertSame($max, $attribute->max);
    }

    public function testConstructorWithStringDates(): void
    {
        $attribute = new CarbonDate(
            min: '2023-01-01',
            max: '2023-12-31',
        );

        $this->assertEquals('2023-01-01', $attribute->min);
        $this->assertEquals('2023-12-31', $attribute->max);
    }

    public function testConstructorWithDateTimeInterface(): void
    {
        $min = new DateTimeImmutable('2023-01-01');
        $max = CarbonImmutable::parse('2023-12-31');

        $attribute = new CarbonDate(min: $min, max: $max);

        $this->assertSame($min, $attribute->min);
        $this->assertSame($max, $attribute->max);
    }

    public function testCreateTransformerWithDefaults(): void
    {
        $attribute = new CarbonDate();
        $transformer = $attribute->createTransformer();

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
        $this->assertNull($transformer->getFormat());
        $this->assertNull($transformer->getTimezone());
        $this->assertNull($transformer->getLocale());
        $this->assertFalse($transformer->isImmutable());
        $this->assertTrue($transformer->isParseRelativeEnabled());
        $this->assertNull($transformer->getSerializeFormat());
        $this->assertNull($transformer->getSerializeTimezone());
        $this->assertNull($transformer->getMin());
        $this->assertNull($transformer->getMax());
    }

    public function testCreateTransformerWithCustomValues(): void
    {
        $min = Carbon::parse('2023-01-01');
        $max = Carbon::parse('2023-12-31');

        $attribute = new CarbonDate(
            format: 'Y-m-d H:i:s',
            timezone: 'America/New_York',
            locale: 'es',
            immutable: true,
            parseRelative: false,
            serializeFormat: 'Y-m-d',
            serializeTimezone: 'Europe/Madrid',
            min: $min,
            max: $max,
        );

        $transformer = $attribute->createTransformer();

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
        $this->assertEquals('Y-m-d H:i:s', $transformer->getFormat());
        $this->assertEquals('America/New_York', $transformer->getTimezone());
        $this->assertEquals('es', $transformer->getLocale());
        $this->assertTrue($transformer->isImmutable());
        $this->assertFalse($transformer->isParseRelativeEnabled());
        $this->assertEquals('Y-m-d', $transformer->getSerializeFormat());
        $this->assertEquals('Europe/Madrid', $transformer->getSerializeTimezone());
        $this->assertSame($min, $transformer->getMin());
        $this->assertSame($max, $transformer->getMax());
    }

    public function testCreateTransformerPreservesDateObjects(): void
    {
        $min = CarbonImmutable::parse('2023-01-01');
        $max = new DateTimeImmutable('2023-12-31');

        $attribute = new CarbonDate(min: $min, max: $max);
        $transformer = $attribute->createTransformer();

        $this->assertSame($min, $transformer->getMin());
        $this->assertSame($max, $transformer->getMax());
    }

    public function testCreateTransformerPreservesStringDates(): void
    {
        $attribute = new CarbonDate(min: '2023-01-01', max: '2023-12-31');
        $transformer = $attribute->createTransformer();

        $this->assertEquals('2023-01-01', $transformer->getMin());
        $this->assertEquals('2023-12-31', $transformer->getMax());
    }

    public function testImmutableProperty(): void
    {
        $mutableAttribute = new CarbonDate(immutable: false);
        $immutableAttribute = new CarbonDate(immutable: true);

        $this->assertFalse($mutableAttribute->immutable);
        $this->assertTrue($immutableAttribute->immutable);

        $mutableTransformer = $mutableAttribute->createTransformer();
        $immutableTransformer = $immutableAttribute->createTransformer();

        $this->assertFalse($mutableTransformer->isImmutable());
        $this->assertTrue($immutableTransformer->isImmutable());
    }

    public function testParseRelativeProperty(): void
    {
        $relativeEnabledAttribute = new CarbonDate(parseRelative: true);
        $relativeDisabledAttribute = new CarbonDate(parseRelative: false);

        $this->assertTrue($relativeEnabledAttribute->parseRelative);
        $this->assertFalse($relativeDisabledAttribute->parseRelative);

        $relativeEnabledTransformer = $relativeEnabledAttribute->createTransformer();
        $relativeDisabledTransformer = $relativeDisabledAttribute->createTransformer();

        $this->assertTrue($relativeEnabledTransformer->isParseRelativeEnabled());
        $this->assertFalse($relativeDisabledTransformer->isParseRelativeEnabled());
    }

    public function testAllParametersAreOptional(): void
    {
        // Should not throw any errors with no parameters
        $attribute = new CarbonDate();
        $transformer = $attribute->createTransformer();

        $this->assertInstanceOf(CarbonTransformer::class, $transformer);
    }
}
