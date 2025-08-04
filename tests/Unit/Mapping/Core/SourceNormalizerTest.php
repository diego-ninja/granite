<?php

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Mapping\Core\SourceNormalizer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use stdClass;
use Tests\Helpers\TestCase;

class SourceNormalizerTest extends TestCase
{
    private SourceNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new SourceNormalizer();
    }

    public function test_normalize_array(): void
    {
        $source = ['name' => 'John', 'age' => 30];
        $result = $this->normalizer->normalize($source);

        $this->assertEquals($source, $result);
    }

    public function test_normalize_granite_object(): void
    {
        $source = TestNormalizerGraniteVO::from(['name' => 'Jane', 'age' => 25]);
        $result = $this->normalizer->normalize($source);

        $this->assertEquals(['name' => 'Jane', 'age' => 25], $result);
    }

    public function test_normalize_stdclass(): void
    {
        $source = new stdClass();
        $source->name = 'Bob';
        $source->age = 35;

        $result = $this->normalizer->normalize($source);

        $this->assertEquals(['name' => 'Bob', 'age' => 35], $result);
    }

    public function test_normalize_regular_object(): void
    {
        $source = new TestNormalizerRegularObject('Alice', 28);
        $result = $this->normalizer->normalize($source);

        $this->assertEquals(['name' => 'Alice', 'age' => 28], $result);
    }

    public function test_normalize_object_with_uninitialized_properties(): void
    {
        $source = new TestNormalizerUninitializedObject();
        $source->name = 'Charlie';
        // age property is uninitialized

        $result = $this->normalizer->normalize($source);

        $this->assertEquals(['name' => 'Charlie'], $result);
    }

    public function test_normalize_throws_exception_for_unsupported_type(): void
    {
        $this->expectException(MappingException::class);
        $this->normalizer->normalize('string');
    }

    public function test_normalize_throws_exception_for_integer(): void
    {
        $this->expectException(MappingException::class);
        $this->normalizer->normalize(42);
    }

    public function test_normalize_throws_exception_for_null(): void
    {
        $this->expectException(MappingException::class);
        $this->normalizer->normalize(null);
    }
}

readonly class TestNormalizerGraniteVO extends GraniteVO
{
    public string $name;
    public int $age;
}

class TestNormalizerRegularObject
{
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

class TestNormalizerUninitializedObject
{
    public string $name;
    public int $age;
}
