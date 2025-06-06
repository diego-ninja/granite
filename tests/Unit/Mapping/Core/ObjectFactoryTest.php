<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping\Core;

use Ninja\Granite\Mapping\Core\ObjectFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\Fixtures\Core\ObjectFactory\DTOWithConstructor;
use Tests\Fixtures\Core\ObjectFactory\DTOWithDefaultsAndNullables;
use Tests\Fixtures\Core\ObjectFactory\DTOWithReadonly;
use Tests\Fixtures\Core\ObjectFactory\EmptyDTO;
use Tests\Fixtures\Core\ObjectFactory\SimpleDTO; // Adjusted for constructor tests
use Tests\Helpers\TestCase;

#[CoversClass(ObjectFactory::class)]
class ObjectFactoryTest extends TestCase
{
    #[Test]
    public function test_create_std_class(): void
    {
        $factory = new ObjectFactory();
        $data = ['foo' => 'bar', 'baz' => 123];
        $obj = $factory->create($data, 'stdClass');

        $this->assertInstanceOf(stdClass::class, $obj);
        $this->assertEquals('bar', $obj->foo);
        $this->assertEquals(123, $obj->baz);
    }

    #[Test]
    public function test_create_dto_without_constructor_public_properties(): void
    {
        $factory = new ObjectFactory();
        $data = ['name' => 'Test', 'value' => 100];
        $dto = $factory->create($data, EmptyDTO::class);

        $this->assertInstanceOf(EmptyDTO::class, $dto);
        $this->assertEquals('Test', $dto->name);
        $this->assertEquals(100, $dto->value);
    }

    #[Test]
    public function test_create_dto_with_constructor_matching_data(): void
    {
        $factory = new ObjectFactory();
        $data = ['name' => 'Alice', 'value' => 30];
        // DTOWithConstructor takes name and value in constructor
        $dto = $factory->create($data, DTOWithConstructor::class);

        $this->assertInstanceOf(DTOWithConstructor::class, $dto);
        $this->assertEquals('Alice', $dto->name);
        $this->assertEquals(30, $dto->value);
    }

    #[Test]
    public function test_create_dto_with_constructor_defaults_and_missing_data(): void
    {
        $factory = new ObjectFactory();
        $data = ['id' => 'xyz'];
        $dto = $factory->create($data, DTOWithDefaultsAndNullables::class);

        $this->assertInstanceOf(DTOWithDefaultsAndNullables::class, $dto);
        $this->assertEquals('xyz', $dto->id);
        $this->assertEquals('default_type', $dto->type); // Constructor default
        $this->assertEquals('default_desc', $dto->description); // Constructor default for nullable
        $this->assertEquals(10, $dto->priority); // Constructor default
        $this->assertTrue($dto->active); // Constructor default
        $this->assertEquals(1.0, $dto->score); // Constructor default
        $this->assertEquals(['default_tag'], $dto->tags); // Constructor default

        // 'type' is missing in data, so constructor default 'default_type' should be used.
        // If 'type' => null was provided in data, PHP would throw a TypeError because the constructor
        // parameter `string $type` is not nullable. ObjectFactory passes data as-is if key exists.
        $data2 = ['id' => 'abc', 'description' => 'custom_desc', 'priority' => 99];
        $dto2 = $factory->create($data2, DTOWithDefaultsAndNullables::class);
        $this->assertEquals('abc', $dto2->id);
        $this->assertEquals('default_type', $dto2->type);
        $this->assertEquals('custom_desc', $dto2->description);
        $this->assertEquals(99, $dto2->priority);
    }

    #[Test]
    public function test_create_dto_with_constructor_respects_explicit_null_for_nullable_params(): void
    {
        $factory = new ObjectFactory();
        // DTOWithDefaultsAndNullables: __construct(?string $description = 'default_desc')
        $data = ['id' => 'id123', 'description' => null];
        $dto = $factory->create($data, DTOWithDefaultsAndNullables::class);
        $this->assertNull($dto->description, "Explicit null in data should override constructor default for nullable param.");
    }


    #[Test]
    public function test_create_dto_with_constructor_and_remaining_properties(): void
    {
        $factory = new ObjectFactory();
        // SimpleDTO constructor: __construct(string $name, ?int $valueFromConstructor = null)
        // Public properties: string $name, int $value, ?string $description = null
        $data = ['name' => 'Bob', 'value' => 50, 'description' => 'Test Desc', 'extra' => 'ignored'];

        // Case 1: 'value' is NOT a constructor param, should be set by populate
        $simpleDtoNoValInCtor = new class('Bob') extends SimpleDTO {
            // value is not taken by this specific constructor variant for testing populate
             public function __construct(string $name) { $this->name = $name; }
        };
        // Need to use a class name string for ObjectFactory, so create a temporary class
        // This anonymous class trick won't work directly with ObjectFactory::create string className.
        // So, let's adjust SimpleDTO to have a constructor that doesn't take 'value'.
        // For this test, we'll assume SimpleDTO's constructor is `__construct(string $name)`
        // and `value` is a public property set afterwards.
        // This requires a specific fixture or adjusting SimpleDTO.
        // Let's create a new fixture: DTOForPartialConstructor.php

        // Re-using SimpleDTO and assuming value is not set by its constructor for this data
        // SimpleDTO's constructor is now new SimpleDTO(string $name, ?int $valueFromConstructor = null)
        // If 'value' is in $data but not matching a constructor param name, it will be set by populate.
        // If 'valueFromConstructor' is in $data, it will be used by constructor.

        $dataForPopulate = ['name' => 'Bob', 'value' => 50, 'description' => 'Test Desc'];
        $dto = $factory->create($dataForPopulate, SimpleDTO::class); // name goes to ctor, value & description to populate

        $this->assertInstanceOf(SimpleDTO::class, $dto);
        $this->assertEquals('Bob', $dto->name); // Set by constructor
        $this->assertEquals(50, $dto->value); // Set by populate after constructor
        $this->assertEquals('Test Desc', $dto->description); // Set by populate
        $this->assertObjectNotHasProperty('extra', $dto);
    }

    #[Test]
    public function test_create_dto_with_readonly_properties_in_constructor(): void
    {
        $factory = new ObjectFactory();
        $data = ['id' => 'ro123', 'name' => 'Readonly Test', 'description' => 'A description'];
        $dto = $factory->create($data, DTOWithReadonly::class);

        $this->assertInstanceOf(DTOWithReadonly::class, $dto);
        $this->assertEquals('ro123', $dto->id);
        $this->assertEquals('Readonly Test', $dto->name);
        $this->assertEquals('A description', $dto->description);
    }

    #[Test]
    public function test_populate_public_writable_properties(): void
    {
        $factory = new ObjectFactory();
        $dto = new EmptyDTO();
        $data = ['name' => 'Populated', 'value' => 200, 'optional' => 'set'];

        $factory->populate($dto, $data);

        $this->assertEquals('Populated', $dto->name);
        $this->assertEquals(200, $dto->value);
        $this->assertEquals('set', $dto->optional);
    }

    #[Test]
    public function test_populate_skips_readonly_properties(): void
    {
        $factory = new ObjectFactory();
        // DTOWithReadonly is a readonly class. All its properties are effectively readonly.
        $dto = new DTOWithReadonly('initial_id', 'initial_name', 'initial_desc');

        $data = ['id' => 'new_id_ignored', 'name' => 'new_name_ignored', 'description' => 'new_desc_ignored'];
        $factory->populate($dto, $data);

        $this->assertEquals('initial_id', $dto->id); // Readonly, should not change
        $this->assertEquals('initial_name', $dto->name); // Readonly, should not change
        $this->assertEquals('initial_desc', $dto->description); // Readonly, should not change
    }

    #[Test]
    public function test_populate_ignores_non_existent_properties(): void
    {
        $factory = new ObjectFactory();
        $dto = new EmptyDTO();
        $data = ['non_existent' => 'value', 'name' => 'Still Populated'];

        $factory->populate($dto, $data);

        $this->assertObjectNotHasProperty('non_existent', $dto);
        $this->assertEquals('Still Populated', $dto->name);
    }
}
