<?php

namespace Tests\Unit\Mapping\Core;

use JsonSerializable;
use Ninja\Granite\GraniteVO;
use Ninja\Granite\Mapping\Core\SourceNormalizer;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use stdClass;
use Tests\Helpers\TestCase;
use TypeError;

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

    public function test_normalize_prefers_public_to_array_adapter(): void
    {
        $source = new TestNormalizerToArrayObject();

        $this->assertSame(['name' => 'adapter'], $this->normalizer->normalize($source));
    }

    public function test_normalize_prefers_to_array_over_json_serializable(): void
    {
        $source = new TestNormalizerBothAdapters();

        $this->assertSame(['name' => 'to-array'], $this->normalizer->normalize($source));
    }

    public function test_normalize_converts_json_serialized_stdclass(): void
    {
        $source = new TestNormalizerJsonObject();

        $this->assertSame(['name' => 'json'], $this->normalizer->normalize($source));
    }

    public function test_normalize_falls_back_when_to_array_is_private(): void
    {
        $source = new TestNormalizerPrivateToArrayObject();

        $this->assertSame(['name' => 'public'], $this->normalizer->normalize($source));
    }

    public function test_normalize_rejects_invalid_to_array_result(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('toArray');

        $this->normalizer->normalize(new TestNormalizerInvalidToArrayObject());
    }

    public function test_normalize_preserves_adapter_throwable_as_previous(): void
    {
        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('toArray');

        try {
            $this->normalizer->normalize(new TestNormalizerThrowingToArrayObject());
        } catch (MappingException $exception) {
            $this->assertInstanceOf(TypeError::class, $exception->getPrevious());
            throw $exception;
        }
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

class TestNormalizerToArrayObject
{
    public string $name = 'public';
    public function toArray(): array
    {
        return ['name' => 'adapter'];
    }
}

class TestNormalizerBothAdapters implements JsonSerializable
{
    public function toArray(): array
    {
        return ['name' => 'to-array'];
    }

    public function jsonSerialize(): array
    {
        return ['name' => 'json'];
    }
}

class TestNormalizerJsonObject implements JsonSerializable
{
    public function jsonSerialize(): stdClass
    {
        $result = new stdClass();
        $result->name = 'json';

        return $result;
    }
}

class TestNormalizerPrivateToArrayObject
{
    public string $name = 'public';
    private function toArray(): array
    {
        return ['name' => 'private'];
    }
}

class TestNormalizerInvalidToArrayObject
{
    public function toArray(): string
    {
        return 'invalid';
    }
}

class TestNormalizerThrowingToArrayObject
{
    public function toArray(): array
    {
        throw new TypeError('adapter failed');
    }
}

class TestNormalizerUninitializedObject
{
    public string $name;
    public int $age;
}
