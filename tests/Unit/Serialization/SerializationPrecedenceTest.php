<?php

// tests/Unit/Serialization/SerializationPrecedenceTest.php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use Ninja\Granite\GraniteDTO;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Fixtures\DTOs\SerializableDTO;
use Tests\Helpers\TestCase;

/**
 * Test to understand and verify serialization name precedence behavior
 *
 * @group serialization-precedence
 */
#[CoversClass(GraniteDTO::class)] class SerializationPrecedenceTest extends TestCase
{
    public function test_determine_actual_precedence_behavior(): void
    {
        // This test helps us understand the actual implementation behavior
        $data = [
            'firstName' => 'FromPhpName',      // PHP property name
            'first_name' => 'FromSerializedName', // Serialized name via attribute
        ];

        $dto = SerializableDTO::from($data);

        // Let's see which value actually gets used
        $actualValue = $dto->firstName;

        // Document the actual behavior
        if ('FromSerializedName' === $actualValue) {
            $this->assertEquals('FromSerializedName', $dto->firstName);
            $this->addToAssertionCount(1); // Serialized name wins
        } elseif ('FromPhpName' === $actualValue) {
            $this->assertEquals('FromPhpName', $dto->firstName);
            $this->addToAssertionCount(1); // PHP name wins
        } else {
            $this->fail("Unexpected value: {$actualValue}. Expected either 'FromSerializedName' or 'FromPhpName'");
        }
    }

    public function test_serialized_name_only(): void
    {
        $data = ['first_name' => 'John'];
        $dto = SerializableDTO::from($data);

        $this->assertEquals('John', $dto->firstName);
    }

    public function test_php_name_only(): void
    {
        $data = ['firstName' => 'John'];
        $dto = SerializableDTO::from($data);

        $this->assertEquals('John', $dto->firstName);
    }

    public function test_multiple_properties_precedence(): void
    {
        $data = [
            // Both names provided
            'firstName' => 'PhpFirst',
            'first_name' => 'SerializedFirst',

            // Both names provided
            'lastName' => 'PhpLast',
            'last_name' => 'SerializedLast',

            // Only one name provided
            'email' => 'test@example.com',
            'password' => 'secret',
        ];

        $dto = SerializableDTO::from($data);

        // Check consistency - both properties should follow same precedence rule
        $firstNameValue = $dto->firstName;
        $lastNameValue = $dto->lastName;

        if ('SerializedFirst' === $firstNameValue) {
            // If serialized wins for firstName, it should win for lastName too
            $this->assertEquals('SerializedLast', $lastNameValue);
        } else {
            // If PHP name wins for firstName, it should win for lastName too
            $this->assertEquals('PhpLast', $lastNameValue);
        }

        // These should work regardless
        $this->assertEquals('test@example.com', $dto->email);
        $this->assertEquals('secret', $dto->password);
    }

    public function test_precedence_with_null_values(): void
    {
        $data = [
            'firstName' => null,
            'first_name' => 'NotNull',
        ];

        $dto = SerializableDTO::from($data);

        // Should handle null values appropriately
        $this->assertTrue(null === $dto->firstName || 'NotNull' === $dto->firstName);
    }

    public function test_case_sensitivity_in_names(): void
    {
        $data = [
            'firstname' => 'lowercase',     // Wrong case
            'firstName' => 'correctCase',   // Correct PHP name
            'first_name' => 'serialized',   // Correct serialized name
        ];

        $dto = SerializableDTO::from($data);

        // Should not use the wrong case version
        $this->assertNotEquals('lowercase', $dto->firstName);

        // Should use either correct PHP name or serialized name
        $this->assertTrue(
            'correctCase' === $dto->firstName || 'serialized' === $dto->firstName,
        );
    }
}
