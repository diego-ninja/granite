<?php

namespace Tests\Unit\Mapping;

use Ninja\Granite\Enums\CacheType;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\MapperConfig;
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;
use Tests\Unit\Mapping\Fixtures\Bidirectional\UserEntity;
use Tests\Unit\Mapping\Fixtures\Bidirectional\UserDTO;
use Tests\Unit\Mapping\Fixtures\Bidirectional\OrderEntity;
use Tests\Unit\Mapping\Fixtures\Bidirectional\OrderDTO;
use Tests\Unit\Mapping\Fixtures\Bidirectional\OrderItemEntity;
use Tests\Unit\Mapping\Fixtures\Bidirectional\OrderItemDTO;
use Tests\Unit\Mapping\Fixtures\Bidirectional\BidirectionalMappingProfile;

#[CoversClass(ObjectMapper::class)]
class BidirectionalMappingTest extends TestCase
{
    private ObjectMapper $mapper;

    protected function setUp(): void
    {
        $profile = new BidirectionalMappingProfile();
        $this->mapper = new ObjectMapper(
            MapperConfig::create()
                ->withProfile($profile)
        );
        parent::setUp();
    }

    #[Test]
    public function it_maps_from_source_to_destination(): void
    {
        $source = new UserEntity(
            id: 1,
            firstName: 'John',
            lastName: 'Doe',
            emailAddress: 'john@example.com'
        );

        $result = $this->mapper->map($source, UserDTO::class);

        $this->assertInstanceOf(UserDTO::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John Doe', $result->fullName);
        $this->assertEquals('john@example.com', $result->email);
    }

    #[Test]
    public function it_maps_from_destination_to_source(): void
    {
        $source = new UserDTO(
            id: 1,
            fullName: 'John Doe',
            email: 'john@example.com'
        );

        $result = $this->mapper->map($source, UserEntity::class);

        $this->assertInstanceOf(UserEntity::class, $result);
        $this->assertEquals(1, $result->id);
        $this->assertEquals('John', $result->firstName);
        $this->assertEquals('Doe', $result->lastName);
        $this->assertEquals('john@example.com', $result->emailAddress);
    }

    #[Test]
    public function it_handles_missing_data_in_bidirectional_mapping(): void
    {
        // Missing last name
        $source = new UserEntity(
            id: 1,
            firstName: 'John',
            lastName: null,
            emailAddress: 'john@example.com'
        );

        $dto = $this->mapper->map($source, UserDTO::class);
        $this->assertEquals('John', $dto->fullName); // Only first name

        // Map back
        $entity = $this->mapper->map($dto, UserEntity::class);
        $this->assertEquals('John', $entity->firstName);
        $this->assertNull($entity->lastName);
    }

    #[Test]
    public function it_preserves_data_integrity_in_round_trip_mapping(): void
    {
        $original = new UserEntity(
            id: 1,
            firstName: 'John',
            lastName: 'Doe',
            emailAddress: 'john@example.com'
        );

        // Map to DTO
        $dto = $this->mapper->map($original, UserDTO::class);
        
        // Map back to entity
        $roundTrip = $this->mapper->map($dto, UserEntity::class);

        // Verify data integrity
        $this->assertEquals($original->id, $roundTrip->id);
        $this->assertEquals($original->firstName, $roundTrip->firstName);
        $this->assertEquals($original->lastName, $roundTrip->lastName);
        $this->assertEquals($original->emailAddress, $roundTrip->emailAddress);
    }

    #[Test]
    public function it_maps_collections_bidirectionally(): void
    {
        $sourceCollection = [
            new UserEntity(1, 'John', 'Doe', 'john@example.com'),
            new UserEntity(2, 'Jane', 'Smith', 'jane@example.com'),
            new UserEntity(3, 'Bob', 'Johnson', 'bob@example.com')
        ];

        // Map to DTOs
        $dtoCollection = $this->mapper->mapArray($sourceCollection, UserDTO::class);
        $this->assertCount(3, $dtoCollection);
        $this->assertContainsOnlyInstancesOf(UserDTO::class, $dtoCollection);
        
        // Map back to entities
        $entityCollection = $this->mapper->mapArray($dtoCollection, UserEntity::class);
        $this->assertCount(3, $entityCollection);
        $this->assertContainsOnlyInstancesOf(UserEntity::class, $entityCollection);
        
        // Verify data
        $this->assertEquals('John', $entityCollection[0]->firstName);
        $this->assertEquals('Jane', $entityCollection[1]->firstName);
        $this->assertEquals('Bob', $entityCollection[2]->firstName);
    }

    #[Test]
    public function it_handles_complex_bidirectional_mapping(): void
    {
        $source = new OrderEntity(
            id: 1,
            orderNumber: 'ORD-001',
            customer: new UserEntity(1, 'John', 'Doe', 'john@example.com'),
            items: [
                new OrderItemEntity(1, 'Product 1', 2, 10.99),
                new OrderItemEntity(2, 'Product 2', 1, 24.99)
            ],
            totalAmount: 46.97
        );

        // Map to DTO
        $orderDTO = $this->mapper->map($source, OrderDTO::class);
        
        $this->assertEquals('ORD-001', $orderDTO->number);
        $this->assertEquals('John Doe', $orderDTO->customerName);
        $this->assertCount(2, $orderDTO->items);
        $this->assertEquals(46.97, $orderDTO->total);
        
        // Map back to entity
        $orderEntity = $this->mapper->map($orderDTO, OrderEntity::class);
        
        $this->assertEquals(0, $orderEntity->id);
        $this->assertEquals('ORD-001', $orderEntity->orderNumber);
        $this->assertInstanceOf(UserEntity::class, $orderEntity->customer);
        $this->assertEquals('John', $orderEntity->customer->firstName);
        $this->assertEquals('Doe', $orderEntity->customer->lastName);
        $this->assertCount(2, $orderEntity->items);
        $this->assertEquals(46.97, $orderEntity->totalAmount);
    }
}
