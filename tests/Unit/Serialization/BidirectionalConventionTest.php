<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use Ninja\Granite\Serialization\MetadataCache;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\DTOs\TestBidirectionalKebabDto;
use Tests\Fixtures\DTOs\TestBidirectionalSnakeDto;

class BidirectionalConventionTest extends TestCase
{
    protected function setUp(): void
    {
        MetadataCache::clearCache();
    }

    /**
     * Test que valida que la convención funciona en AMBAS direcciones
     */
    public function testSnakeCaseBidirectional(): void
    {
        // === PASO 1: Hidratación (snake_case → camelCase) ===
        $snakeCaseData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
            'phone_number' => '+1234567890',
        ];

        $dto = TestBidirectionalSnakeDto::from($snakeCaseData);

        // Verificar que se hidrataron correctamente las propiedades camelCase
        $this->assertEquals('John', $dto->firstName);
        $this->assertEquals('Doe', $dto->lastName);
        $this->assertEquals('john@example.com', $dto->emailAddress);
        $this->assertEquals('+1234567890', $dto->phoneNumber);

        // === PASO 2: Serialización (camelCase → snake_case) ===
        $serializedData = $dto->array();

        $expectedSerialized = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
            'phone_number' => '+1234567890',
        ];

        $this->assertEquals($expectedSerialized, $serializedData);

        // === PASO 3: Ciclo completo (snake_case → camelCase → snake_case) ===
        $json = $dto->json();
        $decodedJson = json_decode($json, true);
        $this->assertEquals($expectedSerialized, $decodedJson);
    }

    /**
     * Test con kebab-case para asegurar que funciona con diferentes convenciones
     */
    public function testKebabCaseBidirectional(): void
    {
        // === PASO 1: Hidratación (kebab-case → camelCase) ===
        $kebabCaseData = [
            'product-name' => 'Awesome Widget',
            'unit-price' => 29.99,
            'is-available' => true,
            'stock-count' => 100,
        ];

        $dto = TestBidirectionalKebabDto::from($kebabCaseData);

        // Verificar hidratación
        $this->assertEquals('Awesome Widget', $dto->productName);
        $this->assertEquals(29.99, $dto->unitPrice);
        $this->assertTrue($dto->isAvailable);
        $this->assertEquals(100, $dto->stockCount);

        // === PASO 2: Serialización (camelCase → kebab-case) ===
        $serializedData = $dto->array();

        $expectedSerialized = [
            'product-name' => 'Awesome Widget',
            'unit-price' => 29.99,
            'is-available' => true,
            'stock-count' => 100,
        ];

        $this->assertEquals($expectedSerialized, $serializedData);
    }

    /**
     * Test para validar que funciona con datos de entrada mixtos
     */
    public function testMixedInputFormats(): void
    {
        // Datos de entrada con formatos mixtos
        $mixedData = [
            'first_name' => 'Mixed',      // snake_case (correcto)
            'lastName' => 'Format',       // camelCase (fallback)
            'email_address' => 'mixed@example.com', // snake_case (correcto)
        ];

        $dto = TestBidirectionalSnakeDto::from($mixedData);

        $this->assertEquals('Mixed', $dto->firstName);
        $this->assertEquals('Format', $dto->lastName);
        $this->assertEquals('mixed@example.com', $dto->emailAddress);

        // La serialización debería normalizar todo a snake_case
        $serialized = $dto->array();
        $expected = [
            'first_name' => 'Mixed',
            'last_name' => 'Format',
            'email_address' => 'mixed@example.com',
        ];

        $this->assertEquals($expected, $serialized);
    }

    /**
     * Test que valida que propiedades no encontradas quedan sin inicializar
     */
    public function testPartialHydration(): void
    {
        $partialData = [
            'first_name' => 'John',
            // last_name y email_address no están presentes
        ];

        $dto = TestBidirectionalSnakeDto::from($partialData);

        $this->assertEquals('John', $dto->firstName);
        // Las otras propiedades no deberían estar inicializadas

        // Solo deberían serializarse las propiedades inicializadas
        $serialized = $dto->array();
        $this->assertArrayHasKey('first_name', $serialized);
        $this->assertEquals('John', $serialized['first_name']);

        // Las propiedades no inicializadas no deberían aparecer en la serialización
        $this->assertArrayNotHasKey('last_name', $serialized);
        $this->assertArrayNotHasKey('email_address', $serialized);
    }

    /**
     * Test con JSON string como entrada
     */
    public function testJsonStringInput(): void
    {
        $jsonString = '{"first_name": "JSON", "last_name": "Test", "email_address": "json@example.com"}';

        $dto = TestBidirectionalSnakeDto::from($jsonString);

        $this->assertEquals('JSON', $dto->firstName);
        $this->assertEquals('Test', $dto->lastName);
        $this->assertEquals('json@example.com', $dto->emailAddress);

        // Round-trip test
        $outputJson = $dto->json();
        $expectedOutput = '{"first_name":"JSON","last_name":"Test","email_address":"json@example.com"}';

        $this->assertJsonStringEqualsJsonString($expectedOutput, $outputJson);
    }

    /**
     * Test de rendimiento básico
     */
    public function testPerformance(): void
    {
        $data = [
            'first_name' => 'Performance',
            'last_name' => 'Test',
            'email_address' => 'perf@example.com',
            'phone_number' => '+1234567890',
        ];

        $start = microtime(true);

        // Crear y serializar 100 objetos
        for ($i = 0; $i < 100; $i++) {
            $dto = TestBidirectionalSnakeDto::from($data);
            $serialized = $dto->array();
        }

        $duration = microtime(true) - $start;

        // Debería completarse en menos de 1 segundo
        $this->assertLessThan(1.0, $duration);
    }
}
