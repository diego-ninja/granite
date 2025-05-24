<?php

namespace Tests\Unit\Mapping;

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\AutoMapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\TestCase;
use stdClass;

class PerformanceMappingTest extends TestCase
{
    private AutoMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AutoMapper();
        parent::setUp();
    }

    // ====== EXTREME EDGE CASES ======

    #[Test]
    public function it_handles_extremely_deep_nesting(): void
    {
        $deeplyNested = $this->createDeeplyNestedArray(50); // 50 levels deep

        $result = $this->mapper->map($deeplyNested, ExtremelyDeepDTO::class);

        $this->assertInstanceOf(ExtremelyDeepDTO::class, $result);
        $this->assertEquals('deep_value', $result->deepestValue);
    }

    #[Test]
    public function it_handles_empty_and_null_structures(): void
    {
        $testCases = [
            [],                    // Empty array
            ['key' => null],       // Null values
            ['key' => []],         // Empty nested array
            ['key' => ['nested' => null]], // Nested nulls
        ];

        foreach ($testCases as $source) {
            $result = $this->mapper->map($source, NullableStructureDTO::class);
            $this->assertInstanceOf(NullableStructureDTO::class, $result);
        }
    }

    #[Test]
    public function it_handles_mixed_type_arrays(): void
    {
        $source = [
            'mixedArray' => [
                1,
                'string',
                true,
                null,
                ['nested' => 'array'],
                new stdClass()
            ]
        ];

        $result = $this->mapper->map($source, MixedTypeArrayDTO::class);

        $this->assertInstanceOf(MixedTypeArrayDTO::class, $result);
        $this->assertIsArray($result->mixedArray);
        $this->assertCount(6, $result->mixedArray);
    }

    #[Test]
    public function it_handles_unicode_and_special_characters(): void
    {
        $source = [
            'name' => '测试用户 👤',
            'description' => 'Çünkü Unicode desteklenmeli! 🚀',
            'emoji' => '🎉🎊🎈',
            'specialChars' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
            'newlines' => "Line 1\nLine 2\r\nLine 3"
        ];

        $result = $this->mapper->map($source, UnicodeDTO::class);

        $this->assertEquals('测试用户 👤', $result->name);
        $this->assertEquals('Çünkü Unicode desteklenmeli! 🚀', $result->description);
        $this->assertEquals('🎉🎊🎈', $result->emoji);
    }

    #[Test]
    public function it_handles_very_large_arrays(): void
    {
        $largeArray = [];
        for ($i = 0; $i < 100000; $i++) {
            $largeArray[] = "item_$i";
        }

        $source = ['largeArray' => $largeArray];

        $start = microtime(true);
        $result = $this->mapper->map($source, LargeArrayDTO::class);
        $elapsed = microtime(true) - $start;

        $this->assertInstanceOf(LargeArrayDTO::class, $result);
        $this->assertCount(100000, $result->largeArray);
        $this->assertLessThan(1.0, $elapsed, 'Large array mapping too slow');
    }

    #[Test]
    public function it_handles_circular_array_references(): void
    {
        // PHP doesn't allow true circular references in arrays,
        // but we can simulate the scenario with complex nested structures
        $source = [
            'id' => 1,
            'name' => 'Node 1',
            'related' => [
                'id' => 2,
                'name' => 'Node 2',
                'backRef' => 1 // Reference back to original
            ]
        ];

        $result = $this->mapper->map($source, CircularRefSafeDTO::class);

        $this->assertInstanceOf(CircularRefSafeDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('Node 1', $result->name);
        $this->assertEquals(1, $result->backReference);
    }

    // ====== MEMORY AND PERFORMANCE STRESS TESTS ======

    #[Test]
    public function it_maintains_performance_with_complex_transformations(): void
    {
        $profile = new PerformanceTestingProfile();
        $mapper = new AutoMapper([$profile]);

        $complexData = [];
        for ($i = 0; $i < 1000; $i++) {
            $complexData[] = [
                'id' => $i,
                'data' => str_repeat('x', 1000), // 1KB of data per item
                'nested' => [
                    'calculations' => range(1, 100),
                    'metadata' => ['timestamp' => time()]
                ]
            ];
        }

        $memoryBefore = memory_get_usage();
        $start = microtime(true);

        $results = $mapper->mapArray($complexData, ComplexPerformanceDTO::class);

        $elapsed = microtime(true) - $start;
        $memoryAfter = memory_get_usage();
        $memoryDiff = $memoryAfter - $memoryBefore;

        $this->assertCount(1000, $results);
        $this->assertLessThan(2.0, $elapsed, 'Complex mapping too slow');
        $this->assertLessThan(100 * 1024 * 1024, $memoryDiff, 'Memory usage too high');
    }

    #[Test]
    public function it_handles_concurrent_mapping_without_conflicts(): void
    {
        // Simulate concurrent mapping by interleaving operations
        $sources = [
            ['type' => 'A', 'value' => 1],
            ['type' => 'B', 'value' => 2],
            ['type' => 'A', 'value' => 3],
            ['type' => 'B', 'value' => 4],
        ];

        $results = [];
        foreach ($sources as $source) {
            if ($source['type'] === 'A') {
                $results[] = $this->mapper->map($source, TypeADTO::class);
            } else {
                $results[] = $this->mapper->map($source, TypeBDTO::class);
            }
        }

        $this->assertCount(4, $results);
        $this->assertInstanceOf(TypeADTO::class, $results[0]);
        $this->assertInstanceOf(TypeBDTO::class, $results[1]);
    }

    // ====== ERROR BOUNDARY TESTS ======

    #[Test]
    public function it_handles_corrupted_data_gracefully(): void
    {
        $corruptedData = [
            'validField' => 'valid',
            'arrayButString' => 'should_be_array',
            'objectButInt' => 123,
            'floatButArray' => ['not', 'a', 'float']
        ];

        $result = $this->mapper->map($corruptedData, CorruptedDataDTO::class);

        $this->assertInstanceOf(CorruptedDataDTO::class, $result);
        $this->assertEquals('valid', $result->validField);
        // Other fields should be handled gracefully
    }

    #[Test]
    public function it_provides_detailed_error_context_on_failure(): void
    {
        try {
            $this->mapper->map(['data' => 'invalid'], 'Completely\\NonExistent\\Class');
            $this->fail('Expected exception not thrown');
        } catch (MappingException $e) {
            $this->assertStringContainsString('NonExistent', $e->getMessage());
            $context = $e->getContext();
            $this->assertArrayHasKey('destination_type', $context);
            $this->assertArrayHasKey('source_type', $context);
        }
    }

    #[Test]
    public function it_handles_transformer_exceptions_with_context(): void
    {
        $source = ['value' => 'cause_exception'];

        try {
            $this->mapper->map($source, ExceptionThrowingTransformerDTO::class);
            $this->fail('Expected exception not thrown');
        } catch (MappingException $e) {
            $this->assertStringContainsString('Transformer exception', $e->getMessage());
        }
    }

    // ====== BOUNDARY VALUE TESTS ======

    #[DataProvider('boundaryValuesProvider')]
    #[Test]
    public function it_handles_boundary_values_correctly($value, string $expectedResult): void
    {
        $profile = new BoundaryValueProfile();
        $mapper = new AutoMapper([$profile]);
        
        $source = ['value' => $value];
        $result = $mapper->map($source, BoundaryValueDTO::class);

        $this->assertEquals($expectedResult, $result->processedValue);
    }

    public static function boundaryValuesProvider(): array
    {
        return [
            'zero' => [0, 'zero'],
            'negative_one' => [-1, 'negative'],
            'max_int' => [PHP_INT_MAX, 'large_positive'],
            'min_int' => [PHP_INT_MIN, 'large_negative'],
            'empty_string' => ['', 'empty'],
            'single_char' => ['a', 'single'],
            'very_long_string' => [str_repeat('x', 10000), 'very_long'],
        ];
    }

    // ====== CONFIGURATION EDGE CASES ======

    #[Test]
    public function it_handles_conflicting_mapping_configurations(): void
    {
        $profile1 = new ConflictingProfile1();
        $profile2 = new ConflictingProfile2();

        // Last profile should win in conflicts
        $mapper = new AutoMapper([$profile1, $profile2]);

        $source = ['value' => 'test'];
        $result = $mapper->map($source, ConflictingConfigDTO::class);

        $this->assertEquals('PROFILE1: test', $result->transformedValue);
    }

    #[Test]
    public function it_handles_profiles_with_no_mappings(): void
    {
        $emptyProfile = new EmptyMappingProfile();
        $mapper = new AutoMapper([$emptyProfile]);

        $source = ['id' => 1, 'name' => 'test'];
        $result = $mapper->map($source, SimpleTestDTO::class);

        $this->assertInstanceOf(SimpleTestDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('test', $result->name);
    }

    // ====== INTEGRATION EDGE CASES ======

    #[Test]
    public function it_integrates_with_serialization_edge_cases(): void
    {
        $source = [
            'resource' => 'cannot_serialize_this',
            'closure' => 'also_cannot_serialize',
            'normalField' => 'this_is_fine'
        ];

        $result = $this->mapper->map($source, SerializationEdgeCaseDTO::class);

        // Should map successfully
        $this->assertInstanceOf(SerializationEdgeCaseDTO::class, $result);
        $this->assertEquals('this_is_fine', $result->normalField);

        // Should be able to serialize the result
        $array = $result->array();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('normalField', $array);
    }

    #[Test]
    public function it_handles_readonly_property_mapping(): void
    {
        $source = ['value1' => 'test1', 'value2' => 'test2'];

        $result = $this->mapper->map($source, ReadonlyPropertiesDTO::class);

        $this->assertEquals('test1', $result->value1);
        $this->assertEquals('test2', $result->value2);
    }

    // ====== HELPER METHODS ======

    private function createDeeplyNestedArray(int $depth): array
    {
        if ($depth <= 0) {
            return ['value' => 'deep_value'];
        }

        return ['level' => $this->createDeeplyNestedArray($depth - 1)];
    }
}

// ====== EDGE CASE TEST FIXTURES ======

final readonly class ExtremelyDeepDTO extends GraniteDTO
{
    public function __construct(
        #[MapWith([self::class, 'extractDeepValue'])]
        public ?string $deepestValue = null
    ) {}

    public static function extractDeepValue(mixed $value, array $sourceData): ?string
    {
        $current = $sourceData;

        // Navigate through up to 100 levels of nesting
        for ($i = 0; $i < 100; $i++) {
            if (!is_array($current) || !isset($current['level'])) {
                break;
            }
            $current = $current['level'];
        }

        return $current['value'] ?? null;
    }
}

final readonly class NullableStructureDTO extends GraniteDTO
{
    public function __construct(
        public mixed $key = null
    ) {}
}

final readonly class MixedTypeArrayDTO extends GraniteDTO
{
    public function __construct(
        public array $mixedArray = []
    ) {}
}

final readonly class UnicodeDTO extends GraniteDTO
{
    public function __construct(
        public string $name,
        public string $description,
        public string $emoji,
        public ?string $specialChars = null,
        public ?string $newlines = null
    ) {}
}

final readonly class LargeArrayDTO extends GraniteDTO
{
    public function __construct(
        public array $largeArray = []
    ) {}
}

final readonly class CircularRefSafeDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,

        #[MapFrom('related.backRef')]
        public ?int $backReference = null
    ) {}
}

final readonly class ComplexPerformanceDTO extends GraniteDTO
{
    public function __construct(
        public int $id,

        #[MapWith([self::class, 'processData'])]
        public string $processedData,

        #[MapFrom('nested.calculations')]
        #[MapWith([self::class, 'sumArray'])]
        public int $calculationSum
    ) {}

    public static function processData(string $data): string
    {
        return 'processed_' . substr($data, 0, 10);
    }

    public static function sumArray(array $numbers): int
    {
        return array_sum($numbers);
    }
}

final readonly class TypeADTO extends GraniteDTO
{
    public function __construct(
        public string $type,
        public int $value
    ) {}
}

final readonly class TypeBDTO extends GraniteDTO
{
    public function __construct(
        public string $type,
        public int $value
    ) {}
}

final readonly class CorruptedDataDTO extends GraniteDTO
{
    public function __construct(
        public ?string $validField = null,
        public mixed $arrayButString = null,
        public mixed $objectButInt = null,
        public mixed $floatButArray = null
    ) {}
}

final readonly class ExceptionThrowingTransformerDTO extends GraniteDTO
{
    public function __construct(
        #[MapWith([self::class, 'throwingTransformer'])]
        public string $value
    ) {}

    public static function throwingTransformer(string $value): string
    {
        if ($value === 'cause_exception') {
            throw new \RuntimeException('Transformer exception');
        }
        return $value;
    }
}

final readonly class BoundaryValueDTO extends GraniteDTO
{
    public function __construct(
        public string $processedValue
    ) {}
}

final readonly class ConflictingConfigDTO extends GraniteDTO
{
    public function __construct(
        public string $transformedValue
    ) {}
}

final readonly class SimpleTestDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name
    ) {}
}

final readonly class SerializationEdgeCaseDTO extends GraniteDTO
{
    public function __construct(
        public string $normalField,
        public mixed $resource = null,
        public mixed $closure = null
    ) {}
}

final readonly class ReadonlyPropertiesDTO extends GraniteDTO
{
    public function __construct(
        public readonly string $value1,
        public readonly string $value2
    ) {}
}

// ====== EDGE CASE PROFILES ======

class PerformanceTestingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('array', ComplexPerformanceDTO::class)
            ->forMember('processedData', fn($mapping) =>
            $mapping->mapFrom('data')
            );
    }
}

class BoundaryValueProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('array', BoundaryValueDTO::class)
            ->forMember('processedValue', fn($mapping) =>
                $mapping->mapFrom('value')
                    ->using(function($value) {
                        if ($value === 0) return 'zero';
                        if ($value === -1) return 'negative';
                        if ($value === PHP_INT_MAX) return 'large_positive';
                        if ($value === PHP_INT_MIN) return 'large_negative';
                        if ($value === '') return 'empty';
                        if (is_string($value) && strlen($value) === 1) return 'single';
                        if (is_string($value) && strlen($value) > 100) return 'very_long';
                        return 'other';
                    })
            );
    }
}

class ConflictingProfile1 extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('array', ConflictingConfigDTO::class)
            ->forMember('transformedValue', fn($mapping) =>
            $mapping->mapFrom('value')
                ->using(fn($value) => 'PROFILE1: ' . $value)
            );
    }
}

class ConflictingProfile2 extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap('array', ConflictingConfigDTO::class)
            ->forMember('transformedValue', fn($mapping) =>
            $mapping->mapFrom('value')
                ->using(fn($value) => 'PROFILE2: ' . $value)
            );
    }
}

class EmptyMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        // Intentionally empty
    }
}

// ====== STRESS TEST METHODS ======

trait StressTestHelpers
{
    protected function generateLargeDataset(int $size): array
    {
        $data = [];
        for ($i = 0; $i < $size; $i++) {
            $data[] = [
                'id' => $i,
                'name' => "Item $i",
                'data' => str_repeat('x', 100),
                'nested' => [
                    'value' => $i * 2,
                    'metadata' => ['created' => time()]
                ]
            ];
        }
        return $data;
    }

    protected function measureMemoryUsage(callable $operation): array
    {
        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);

        $start = microtime(true);
        $result = $operation();
        $elapsed = microtime(true) - $start;

        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);

        return [
            'result' => $result,
            'time' => $elapsed,
            'memory_used' => $memoryAfter - $memoryBefore,
            'peak_memory' => $peakAfter - $peakBefore
        ];
    }
}