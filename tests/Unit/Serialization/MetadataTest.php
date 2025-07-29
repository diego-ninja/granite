<?php

// tests/Unit/Serialization/MetadataTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use Ninja\Granite\Serialization\Metadata;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Helpers\TestCase;

#[CoversClass(Metadata::class)] class MetadataTest extends TestCase
{
    private Metadata $metadata;

    protected function setUp(): void
    {
        $this->metadata = new Metadata();
        parent::setUp();
    }

    public function test_creates_empty_metadata(): void
    {
        $metadata = new Metadata();

        $this->assertInstanceOf(Metadata::class, $metadata);
    }

    public function test_creates_metadata_with_initial_data(): void
    {
        $propertyNames = ['firstName' => 'first_name', 'lastName' => 'last_name'];
        $hiddenProperties = ['password', 'apiToken'];

        $metadata = new Metadata($propertyNames, $hiddenProperties);

        $this->assertEquals('first_name', $metadata->getSerializedName('firstName'));
        $this->assertTrue($metadata->isHidden('password'));
    }

    public function test_gets_serialized_name_for_mapped_property(): void
    {
        $this->metadata->mapPropertyName('firstName', 'first_name');

        $this->assertEquals('first_name', $this->metadata->getSerializedName('firstName'));
    }

    public function test_gets_original_name_for_unmapped_property(): void
    {
        $serializedName = $this->metadata->getSerializedName('lastName');

        $this->assertEquals('lastName', $serializedName);
    }

    public function test_maps_multiple_property_names(): void
    {
        $this->metadata->mapPropertyName('firstName', 'first_name')
            ->mapPropertyName('lastName', 'last_name')
            ->mapPropertyName('emailAddress', 'email');

        $this->assertEquals('first_name', $this->metadata->getSerializedName('firstName'));
        $this->assertEquals('last_name', $this->metadata->getSerializedName('lastName'));
        $this->assertEquals('email', $this->metadata->getSerializedName('emailAddress'));
    }

    public function test_overwrites_existing_property_mapping(): void
    {
        $this->metadata->mapPropertyName('firstName', 'first_name');
        $this->metadata->mapPropertyName('firstName', 'given_name'); // Overwrite

        $this->assertEquals('given_name', $this->metadata->getSerializedName('firstName'));
    }

    public function test_is_not_hidden_by_default(): void
    {
        $this->assertFalse($this->metadata->isHidden('firstName'));
        $this->assertFalse($this->metadata->isHidden('password'));
        $this->assertFalse($this->metadata->isHidden('anyProperty'));
    }

    public function test_hides_single_property(): void
    {
        $this->metadata->hideProperty('password');

        $this->assertTrue($this->metadata->isHidden('password'));
        $this->assertFalse($this->metadata->isHidden('firstName'));
    }

    public function test_hides_multiple_properties(): void
    {
        $this->metadata->hideProperty('password')
            ->hideProperty('apiToken')
            ->hideProperty('internalId');

        $this->assertTrue($this->metadata->isHidden('password'));
        $this->assertTrue($this->metadata->isHidden('apiToken'));
        $this->assertTrue($this->metadata->isHidden('internalId'));
        $this->assertFalse($this->metadata->isHidden('firstName'));
    }

    public function test_does_not_duplicate_hidden_properties(): void
    {
        $this->metadata->hideProperty('password');
        $this->metadata->hideProperty('password'); // Add same property twice
        $this->metadata->hideProperty('password'); // Add same property third time

        $debug = $this->metadata->debug();

        // Should only appear once in the hidden properties array
        $this->assertEquals(['password'], $debug['hiddenProperties']);
        $this->assertCount(1, $debug['hiddenProperties']);
    }

    public function test_method_chaining_for_property_mapping(): void
    {
        $result = $this->metadata->mapPropertyName('firstName', 'first_name')
            ->mapPropertyName('lastName', 'last_name');

        $this->assertSame($this->metadata, $result);
        $this->assertEquals('first_name', $this->metadata->getSerializedName('firstName'));
        $this->assertEquals('last_name', $this->metadata->getSerializedName('lastName'));
    }

    public function test_method_chaining_for_hiding_properties(): void
    {
        $result = $this->metadata->hideProperty('password')
            ->hideProperty('apiToken');

        $this->assertSame($this->metadata, $result);
        $this->assertTrue($this->metadata->isHidden('password'));
        $this->assertTrue($this->metadata->isHidden('apiToken'));
    }

    public function test_method_chaining_mixed_operations(): void
    {
        $result = $this->metadata->mapPropertyName('firstName', 'first_name')
            ->hideProperty('password')
            ->mapPropertyName('emailAddress', 'email')
            ->hideProperty('apiToken');

        $this->assertSame($this->metadata, $result);
        $this->assertEquals('first_name', $this->metadata->getSerializedName('firstName'));
        $this->assertEquals('email', $this->metadata->getSerializedName('emailAddress'));
        $this->assertTrue($this->metadata->isHidden('password'));
        $this->assertTrue($this->metadata->isHidden('apiToken'));
    }

    public function test_debug_returns_internal_state(): void
    {
        $this->metadata->mapPropertyName('firstName', 'first_name')
            ->mapPropertyName('lastName', 'last_name')
            ->hideProperty('password')
            ->hideProperty('apiToken');

        $debug = $this->metadata->debug();

        $this->assertIsArray($debug);
        $this->assertArrayHasKey('propertyNames', $debug);
        $this->assertArrayHasKey('hiddenProperties', $debug);

        $this->assertEquals([
            'firstName' => 'first_name',
            'lastName' => 'last_name',
        ], $debug['propertyNames']);

        $this->assertEquals(['password', 'apiToken'], $debug['hiddenProperties']);
    }

    public function test_debug_with_empty_metadata(): void
    {
        $debug = $this->metadata->debug();

        $this->assertEquals([
            'propertyNames' => [],
            'hiddenProperties' => [],
        ], $debug);
    }

    public function test_handles_special_characters_in_property_names(): void
    {
        $this->metadata->mapPropertyName('user_name', 'username')
            ->mapPropertyName('email-address', 'email')
            ->hideProperty('private-key')
            ->hideProperty('secret_token');

        $this->assertEquals('username', $this->metadata->getSerializedName('user_name'));
        $this->assertEquals('email', $this->metadata->getSerializedName('email-address'));
        $this->assertTrue($this->metadata->isHidden('private-key'));
        $this->assertTrue($this->metadata->isHidden('secret_token'));
    }

    public function test_handles_unicode_property_names(): void
    {
        $this->metadata->mapPropertyName('título', 'title')
            ->hideProperty('contraseña');

        $this->assertEquals('title', $this->metadata->getSerializedName('título'));
        $this->assertTrue($this->metadata->isHidden('contraseña'));
    }

    public function test_case_sensitive_property_names(): void
    {
        $this->metadata->mapPropertyName('firstName', 'first_name')
            ->mapPropertyName('FirstName', 'FIRST_NAME') // Different case
            ->hideProperty('password')
            ->hideProperty('Password'); // Different case

        $this->assertEquals('first_name', $this->metadata->getSerializedName('firstName'));
        $this->assertEquals('FIRST_NAME', $this->metadata->getSerializedName('FirstName'));
        $this->assertEquals('lastName', $this->metadata->getSerializedName('lastName')); // Not mapped

        $this->assertTrue($this->metadata->isHidden('password'));
        $this->assertTrue($this->metadata->isHidden('Password'));
        $this->assertFalse($this->metadata->isHidden('PASSWORD')); // Different case
    }

    public function test_empty_serialized_names_are_allowed(): void
    {
        $this->metadata->mapPropertyName('firstName', '');

        $this->assertEquals('', $this->metadata->getSerializedName('firstName'));
    }

    public function test_empty_property_names_are_handled(): void
    {
        $this->metadata->mapPropertyName('', 'empty_property')
            ->hideProperty('');

        $this->assertEquals('empty_property', $this->metadata->getSerializedName(''));
        $this->assertTrue($this->metadata->isHidden(''));
    }
}
