<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Ninja\Granite\Serialization\CarbonTransformerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(CarbonTransformerFactory::class)]
final class CarbonTransformerFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        GraniteConfig::reset();
        CarbonTransformerFactory::clearCache();

        parent::tearDown();
    }

    public function test_creates_transformer_for_unannotated_carbon_property(): void
    {
        $transformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'mutable'),
        );

        $this->assertNotNull($transformer);
        $this->assertInstanceOf(Carbon::class, $transformer->transform('2024-01-02 03:04:05'));
    }

    public function test_creates_immutable_transformer_for_unannotated_carbon_immutable_property(): void
    {
        $transformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'immutable'),
        );

        $this->assertNotNull($transformer);
        $this->assertTrue($transformer->isImmutable());
        $this->assertInstanceOf(CarbonImmutable::class, $transformer->transform('2024-01-02 03:04:05'));
    }

    public function test_creates_transformer_when_global_configuration_prefers_carbon(): void
    {
        GraniteConfig::getInstance()
            ->preferCarbon()
            ->preferCarbonImmutable();

        $transformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'dateTime'),
        );

        $this->assertNotNull($transformer);
        $this->assertTrue($transformer->isImmutable());
        $this->assertInstanceOf(CarbonImmutable::class, $transformer->transform('2024-01-02 03:04:05'));
    }

    public function test_global_prefer_carbon_only_applies_to_datetime_properties(): void
    {
        GraniteConfig::getInstance()->preferCarbon();

        $stringTransformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'text'),
        );
        $integerTransformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'count'),
        );
        $dateTimeTransformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'dateTime'),
        );

        $this->assertNull($stringTransformer);
        $this->assertNull($integerTransformer);
        $this->assertNotNull($dateTimeTransformer);
    }

    public function test_carbon_relative_attribute_overrides_carbon_date_parse_relative(): void
    {
        $transformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'relative'),
        );

        $this->assertNotNull($transformer);
        $this->assertTrue($transformer->isParseRelativeEnabled());
        $this->assertSame('2024-01-02 00:00:00', $transformer->transform('tomorrow')?->format('Y-m-d H:i:s'));
    }

    public function test_carbon_range_attribute_overrides_carbon_date_bounds(): void
    {
        $transformer = CarbonTransformerFactory::create(
            new ReflectionProperty(CarbonFactoryFixture::class, 'ranged'),
        );

        $this->assertNotNull($transformer);
        $this->assertSame('2024-01-01', $transformer->getMin());
        $this->assertSame('2024-12-31', $transformer->getMax());
        $this->assertNotNull($transformer->transform('2024-06-15'));
        $this->assertNull($transformer->transform('2023-06-15'));
    }
}

final readonly class CarbonFactoryFixture
{
    public function __construct(
        public Carbon $mutable,
        public CarbonImmutable $immutable,
        public DateTimeInterface $dateTime,
        public string $text,
        public int $count,
        #[CarbonDate(parseRelative: false)]
        #[CarbonRelative(enabled: true, baseDate: '2024-01-01 12:00:00')]
        public Carbon $relative,
        #[CarbonDate(min: '2020-01-01', max: '2030-12-31')]
        #[CarbonRange(min: '2024-01-01', max: '2024-12-31')]
        public Carbon $ranged,
    ) {}
}
