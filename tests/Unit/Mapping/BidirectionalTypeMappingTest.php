<?php

declare(strict_types=1);

namespace Tests\Unit\Mapping;

use Ninja\Granite\Mapping\BidirectionalTypeMapping;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\Contracts\MappingStorage; // Not strictly needed if using concrete InMemory
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Mapping\DestinationDTO;
use Tests\Fixtures\Mapping\InMemoryMappingStorage; // Import the concrete storage
use Tests\Fixtures\Mapping\SourceDTO;
use Tests\Helpers\TestCase;

#[CoversClass(BidirectionalTypeMapping::class)]
class BidirectionalTypeMappingTest extends TestCase
{
    private InMemoryMappingStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = new InMemoryMappingStorage();
    }

    #[Test]
    public function test_constructor_and_getters(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);

        $this->assertEquals(SourceDTO::class, $mapping->getForwardMapping()->getSourceType());
        $this->assertEquals(DestinationDTO::class, $mapping->getForwardMapping()->getDestinationType());
        $this->assertEquals(DestinationDTO::class, $mapping->getReverseMapping()->getSourceType());
        $this->assertEquals(SourceDTO::class, $mapping->getReverseMapping()->getDestinationType());
    }

    #[Test]
    public function test_for_member_pairs(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        $mapping->forMemberPairs([
            'sourceProp1' => 'destProp1', // SourceDTO.sourceProp1 -> DestinationDTO.destProp1
            'sourceProp2' => 'destProp2', // SourceDTO.sourceProp2 -> DestinationDTO.destProp2
        ]);
        $mapping->seal();

        $forwardProp1Mapping = $this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'destProp1');
        $this->assertNotNull($forwardProp1Mapping);
        $this->assertEquals('sourceProp1', $forwardProp1Mapping->getSourceProperty());

        $forwardProp2Mapping = $this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'destProp2');
        $this->assertNotNull($forwardProp2Mapping);
        $this->assertEquals('sourceProp2', $forwardProp2Mapping->getSourceProperty());

        $reverseProp1Mapping = $this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'sourceProp1');
        $this->assertNotNull($reverseProp1Mapping);
        $this->assertEquals('destProp1', $reverseProp1Mapping->getSourceProperty());

        $reverseProp2Mapping = $this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'sourceProp2');
        $this->assertNotNull($reverseProp2Mapping);
        $this->assertEquals('destProp2', $reverseProp2Mapping->getSourceProperty());
    }

    #[Test]
    public function test_for_members_with_callable_config(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);

        // Forward: DestinationDTO.destProp1 should be ignored when mapping from SourceDTO
        $mapping->forForwardMember('destProp1', static fn (PropertyMapping $pm) => $pm->ignore());
        // Reverse: SourceDTO.sourceProp1 should be mapped from DestinationDTO.common property
        $mapping->forReverseMember('sourceProp1', static fn (PropertyMapping $pm) => $pm->mapFrom('common'));

        $mapping->seal();

        $forwardMappingConfig = $this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'destProp1');
        $this->assertNotNull($forwardMappingConfig);
        $this->assertTrue($forwardMappingConfig->isIgnored());

        $reverseMappingConfig = $this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'sourceProp1');
        $this->assertNotNull($reverseMappingConfig);
        $this->assertEquals('common', $reverseMappingConfig->getSourceProperty());
    }

    #[Test]
    public function test_for_forward_member_and_for_reverse_member(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        // Forward: DestinationDTO.destOnlyProp mapped from SourceDTO.sourceForDestOnly
        $mapping->forForwardMember('destOnlyProp', static fn (PropertyMapping $pm) => $pm->mapFrom('sourceForDestOnly'));
        // Reverse: SourceDTO.srcProp mapped from DestinationDTO.destForSourceOnly
        $mapping->forReverseMember('srcProp', static fn (PropertyMapping $pm) => $pm->mapFrom('destForSourceOnly'));
        $mapping->seal();

        $forwardConfig = $this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'destOnlyProp');
        $this->assertNotNull($forwardConfig);
        $this->assertEquals('sourceForDestOnly', $forwardConfig->getSourceProperty());

        $reverseConfig = $this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'srcProp');
        $this->assertNotNull($reverseConfig);
        $this->assertEquals('destForSourceOnly', $reverseConfig->getSourceProperty());
    }

    #[Test]
    public function test_seal_prevents_further_configuration(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        $mapping->seal();

        $this->expectException(\RuntimeException::class); // Corrected exception type
        $mapping->forMemberPairs(['sourceProp1' => 'destProp1']);
    }

    #[Test]
    public function test_seal_prevents_further_for_members_config(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        $mapping->seal();

        $this->expectException(\RuntimeException::class); // Corrected exception type
        // Corrected to use valid forMembers signature for BidirectionalTypeMapping
        $mapping->forMembers('sourceProp1', 'destProp1');
    }

    #[Test]
    public function test_seal_prevents_further_for_forward_member_config(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        $mapping->seal();

        $this->expectException(\RuntimeException::class); // Corrected exception type
        $mapping->forForwardMember('destOnlyProp', fn(PropertyMapping $pm) => $pm->ignore());
    }

    #[Test]
    public function test_seal_prevents_further_for_reverse_member_config(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        $mapping->seal();

        $this->expectException(\RuntimeException::class); // Corrected exception type
        $mapping->forReverseMember('srcProp', fn(PropertyMapping $pm) => $pm->ignore()); // Corrected property name
    }

    #[Test]
    public function test_property_mappings_are_distinct_for_forward_and_reverse(): void
    {
        $mapping = new BidirectionalTypeMapping($this->storage, SourceDTO::class, DestinationDTO::class);
        $mapping->forMemberPairs(['common' => 'common']);
        $mapping->forForwardMember('destOnlyProp', static fn (PropertyMapping $pm) => $pm->mapFrom('sourceForDestOnly'));
        $mapping->forReverseMember('srcProp', static fn (PropertyMapping $pm) => $pm->mapFrom('destForSourceOnly'));
        $mapping->seal();

        // Forward: SourceDTO -> DestinationDTO
        $forwardCommon = $this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'common');
        $this->assertNotNull($forwardCommon);
        $this->assertEquals('common', $forwardCommon->getSourceProperty());

        $forwardDestOnly = $this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'destOnlyProp');
        $this->assertNotNull($forwardDestOnly);
        $this->assertEquals('sourceForDestOnly', $forwardDestOnly->getSourceProperty());

        // Ensure srcProp is not defined for forward mapping to DestinationDTO as it's a source property
        $this->assertNull($this->storage->getMapping(SourceDTO::class, DestinationDTO::class, 'srcProp'));

        // Reverse: DestinationDTO -> SourceDTO
        $reverseCommon = $this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'common');
        $this->assertNotNull($reverseCommon);
        $this->assertEquals('common', $reverseCommon->getSourceProperty());

        $reverseSrcProp = $this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'srcProp');
        $this->assertNotNull($reverseSrcProp);
        $this->assertEquals('destForSourceOnly', $reverseSrcProp->getSourceProperty());

        // Ensure destOnlyProp is not defined for reverse mapping to SourceDTO as it's a property of DestinationDTO being mapped from
        $this->assertNull($this->storage->getMapping(DestinationDTO::class, SourceDTO::class, 'destOnlyProp'));
    }
}
