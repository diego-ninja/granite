<?php

namespace Tests\Unit\Mapping\Attributes;

use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Contracts\Transformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;

#[CoversClass(MapWith::class)]
class MapWithTest extends TestCase
{
    private ObjectMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ObjectMapper();
        parent::setUp();
    }

    #[Test]
    public function it_transforms_value_with_callable(): void
    {
        $source = [
            'name' => 'john doe'
        ];

        $result = $this->mapper->map($source, CallableTransformerDTO::class);

        $this->assertEquals('JOHN DOE', $result->name);
    }

    #[Test]
    public function it_transforms_value_with_static_method(): void
    {
        $source = [
            'value' => 'test'
        ];

        $result = $this->mapper->map($source, StaticMethodTransformerDTO::class);

        $this->assertEquals('STATIC: test', $result->value);
    }

    #[Test]
    public function it_transforms_value_with_transformer_class(): void
    {
        $source = [
            'value' => 'test'
        ];

        $result = $this->mapper->map($source, TransformerClassDTO::class);

        $this->assertEquals('CUSTOM: test', $result->value);
    }

    #[Test]
    public function it_transforms_with_source_data_context(): void
    {
        $source = [
            'firstName' => 'John',
            'lastName' => 'Doe'
        ];

        $result = $this->mapper->map($source, ContextTransformerDTO::class);

        $this->assertEquals('John Doe', $result->fullName);
    }

    #[Test]
    public function it_handles_null_values(): void
    {
        $source = [
            'value' => null
        ];

        $result = $this->mapper->map($source, NullHandlingTransformerDTO::class);

        $this->assertEquals('DEFAULT', $result->value);
    }

    #[Test]
    public function it_works_with_different_data_types(): void
    {
        $source = [
            'stringValue' => '42',
            'intValue' => '100',
            'boolValue' => 1,
            'arrayValue' => '1,2,3'
        ];

        $result = $this->mapper->map($source, TypeConversionDTO::class);

        $this->assertSame('42', $result->stringValue);
        $this->assertSame(100, $result->intValue);
        $this->assertSame(true, $result->boolValue);
        $this->assertSame([1, 2, 3], $result->arrayValue);
    }

    #[Test]
    public function it_combines_with_map_from_attribute(): void
    {
        $source = [
            'user' => [
                'name' => 'john doe'
            ]
        ];

        $result = $this->mapper->map($source, CombinedAttributesDTO::class);

        $this->assertEquals('JOHN DOE', $result->userName);
    }

    #[Test]
    public function it_handles_transformer_with_constructor_parameters(): void
    {
        $source = [
            'formattedPrice' => 42.99
        ];

        $result = $this->mapper->map($source, ParameterizedTransformerDTO::class);

        $this->assertEquals('$42.99', $result->formattedPrice);
    }
}

// Test DTOs and Transformers
class CallableTransformerDTO
{
    public function __construct(
        #[MapWith([CallableTransformerDTO::class, 'toUpperCase'])]
        public string $name = ''
    ) {
    }
    
    public static function toUpperCase(string $value): string
    {
        return strtoupper($value);
    }
}

class StaticMethodTransformerDTO
{
    public function __construct(
        #[MapWith([self::class, 'staticTransformer'])]
        public string $value = ''
    ) {
    }

    public static function staticTransformer(string $value): string
    {
        return 'STATIC: ' . $value;
    }
}

class CustomTransformer implements Transformer
{
    public function transform(mixed $value, array $sourceData = []): string
    {
        return 'CUSTOM: ' . $value;
    }
}

class TransformerClassDTO
{
    public function __construct(
        #[MapWith(new CustomTransformer())]
        public string $value = ''
    ) {
    }
}

class ContextTransformerDTO
{
    public function __construct(
        #[MapWith([self::class, 'combineNames'])]
        public string $fullName = ''
    ) {
    }

    public static function combineNames(mixed $value, array $sourceData): string
    {
        return $sourceData['firstName'] . ' ' . $sourceData['lastName'];
    }
}

class NullHandlingTransformerDTO
{
    public function __construct(
        #[MapWith([self::class, 'handleNull'])]
        public string $value = ''
    ) {
    }

    public static function handleNull(?string $value): string
    {
        return $value ?? 'DEFAULT';
    }
}

class TypeConversionDTO
{
    public function __construct(
        #[MapWith([TypeConversionDTO::class, 'toString'])]
        public string $stringValue = '',

        #[MapWith([TypeConversionDTO::class, 'toInt'])]
        public int $intValue = 0,

        #[MapWith([TypeConversionDTO::class, 'toBool'])]
        public bool $boolValue = false,

        #[MapWith([TypeConversionDTO::class, 'toIntArray'])]
        public array $arrayValue = []
    ) {
    }
    
    public static function toString($v): string
    {
        return (string)$v;
    }
    
    public static function toInt($v): int
    {
        return (int)$v;
    }
    
    public static function toBool($v): bool
    {
        return (bool)$v;
    }
    
    public static function toIntArray($v): array
    {
        return array_map('intval', explode(',', $v));
    }
}

class CombinedAttributesDTO
{
    public function __construct(
        #[MapFrom('user.name')]
        #[MapWith([CombinedAttributesDTO::class, 'toUpperCase'])]
        public string $userName = ''
    ) {
    }
    
    public static function toUpperCase(string $value): string
    {
        return strtoupper($value);
    }
}

class CurrencyTransformer implements Transformer
{
    public function __construct(private string $symbol = '$')
    {
    }

    public function transform(mixed $value, array $sourceData = []): string
    {
        return $this->symbol . number_format((float)$value, 2);
    }
}

class ParameterizedTransformerDTO
{
    public function __construct(
        #[MapWith(new CurrencyTransformer())]
        public string $formattedPrice = ''
    ) {
    }
}
