<?php

namespace Tests\Unit\Mapping\Attributes;

use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\ObjectMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;

#[CoversClass(Ignore::class)]
class IgnoreTest extends TestCase
{
    private ObjectMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ObjectMapper();
        parent::setUp();
    }

    #[Test]
    public function it_ignores_marked_properties(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'password' => 'secret123',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, IgnoreDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertNull($result->password);
    }

    #[Test]
    public function it_ignores_multiple_properties(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'password' => 'secret123',
            'apiKey' => 'api-key-123',
            'secretToken' => 'token-456',
            'email' => 'john@example.com'
        ];

        $result = $this->mapper->map($source, MultipleIgnoreDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertNull($result->password);
        $this->assertNull($result->apiKey);
        $this->assertNull($result->secretToken);
    }

    #[Test]
    public function it_ignores_property_even_when_explicitly_provided(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'password' => 'secret123'
        ];

        $destination = new IgnoreDTO();
        $destination->password = 'existing-password';

        $result = $this->mapper->mapTo($source, $destination);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertEquals('existing-password', $result->password); // La propiedad ignorada debe mantener su valor original
    }

    #[Test]
    public function it_ignores_nested_properties(): void
    {
        $source = [
            'user' => [
                'id' => 1,
                'name' => 'John Doe',
                'password' => 'secret123'
            ]
        ];

        $result = $this->mapper->map($source, NestedIgnoreDTO::class);

        $this->assertEquals(1, $result->userId);
        $this->assertEquals('John Doe', $result->userName);
        $this->assertNull($result->userPassword);
    }

    #[Test]
    public function it_prioritizes_ignore_over_other_attributes(): void
    {
        $source = [
            'id' => 1,
            'name' => 'John Doe',
            'password' => 'secret123'
        ];

        $result = $this->mapper->map($source, ConflictingAttributesDTO::class);

        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->name);
        $this->assertNull($result->password); // Should be null despite MapFrom
    }
}

// Test DTOs
class IgnoreDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        #[Ignore]
        public ?string $password = null,
        public ?string $email = null
    ) {
    }
}

class MultipleIgnoreDTO
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        #[Ignore]
        public ?string $password = null,
        #[Ignore]
        public ?string $apiKey = null,
        #[Ignore]
        public ?string $secretToken = null,
        public ?string $email = null
    ) {
    }
}

class NestedIgnoreDTO
{
    public function __construct(
        #[MapFrom('user.id')]
        public ?int $userId = null,
        
        #[MapFrom('user.name')]
        public ?string $userName = null,
        
        #[MapFrom('user.password')]
        #[Ignore]
        public ?string $userPassword = null
    ) {
    }
}

class ConflictingAttributesDTO
{
    public function __construct(
        public int $id,
        public string $name,
        
        #[MapFrom('password')]
        #[Ignore]
        public ?string $password = null
    ) {
    }
}
