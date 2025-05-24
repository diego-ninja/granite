<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\AutoMapper;
use Ninja\Granite\Mapping\ConventionMapper;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Storage\TestMappingStorage;
use Tests\Helpers\TestCase;

#[CoversClass(AutoMapper::class)]
#[CoversClass(ConventionMapper::class)]
class ConventionIntegrationTest extends TestCase
{
    private AutoMapper $mapper;
    private ConventionMapper $conventionMapper;
    private TestMappingStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->conventionMapper = new ConventionMapper();
        $this->storage = new TestMappingStorage();
        
        // Crear una instancia de AutoMapper (si necesitamos usarla más adelante)
        // Por ahora nos enfocamos en testear el ConventionMapper directamente
        $this->mapper = new AutoMapper();
    }

    #[Test]
    public function it_discovers_mappings_between_camel_and_snake_case()
    {
        // Descubrir mapeos entre camelCase y snake_case
        $mappings = $this->conventionMapper->discoverMappings(
            CamelCaseUser::class, 
            SnakeCaseUser::class
        );
        
        $this->assertArrayHasKey('first_name', $mappings);
        $this->assertEquals('firstName', $mappings['first_name']);
        
        $this->assertArrayHasKey('last_name', $mappings);
        $this->assertEquals('lastName', $mappings['last_name']);
        
        $this->assertArrayHasKey('email_address', $mappings);
        $this->assertEquals('emailAddress', $mappings['email_address']);
        
        $this->assertArrayHasKey('phone_number', $mappings);
        $this->assertEquals('phoneNumber', $mappings['phone_number']);
    }

    #[Test]
    public function it_discovers_mappings_between_pascal_and_snake_case()
    {
        // Descubrir mapeos entre PascalCase y snake_case
        $mappings = $this->conventionMapper->discoverMappings(
            PascalCaseUser::class, 
            SnakeCaseUser::class
        );
        
        $this->assertArrayHasKey('first_name', $mappings);
        $this->assertEquals('FirstName', $mappings['first_name']);
        
        $this->assertArrayHasKey('last_name', $mappings);
        $this->assertEquals('LastName', $mappings['last_name']);
        
        $this->assertArrayHasKey('email_address', $mappings);
        $this->assertEquals('EmailAddress', $mappings['email_address']);
    }

    #[Test]
    public function it_applies_conventions_to_type_mapping()
    {
        // Crear un TypeMapping con TestMappingStorage
        $typeMapping = new TypeMapping(
            $this->storage, 
            CamelCaseUser::class, 
            SnakeCaseUser::class
        );
        
        // Aplicar las convenciones descubiertas al TypeMapping
        $mappings = $this->conventionMapper->applyConventions(
            CamelCaseUser::class,
            SnakeCaseUser::class,
            $typeMapping
        );
        
        // Verificar que las convenciones se aplicaron correctamente
        $this->assertNotEmpty($mappings);
        $this->assertArrayHasKey('first_name', $mappings);
        $this->assertEquals('firstName', $mappings['first_name']);
    }

    #[Test]
    public function it_respects_confidence_threshold()
    {
        // Establecer un umbral de confianza muy alto
        $this->conventionMapper->setConfidenceThreshold(0.99);
        
        // Intentar descubrir mapeos con un umbral muy alto
        $highThresholdMappings = $this->conventionMapper->discoverMappings(
            CamelCaseUser::class,
            SnakeCaseUser::class
        );
        
        // Restaurar umbral normal
        $this->conventionMapper->setConfidenceThreshold(0.8);
        
        // Intentar de nuevo
        $normalThresholdMappings = $this->conventionMapper->discoverMappings(
            CamelCaseUser::class,
            SnakeCaseUser::class
        );
        
        // Debería encontrar más mapeos con el umbral normal
        $this->assertGreaterThanOrEqual(
            count($highThresholdMappings), 
            count($normalThresholdMappings)
        );
    }

    #[Test]
    public function it_handles_array_source_types_gracefully()
    {
        // No debería lanzar excepciones
        $mappings = $this->conventionMapper->discoverMappings('array', SnakeCaseUser::class);
        $this->assertEmpty($mappings);
        
        $mappings = $this->conventionMapper->applyConventions('array', SnakeCaseUser::class);
        $this->assertEmpty($mappings);
    }
}

// Clases de prueba
class CamelCaseUser
{
    public string $firstName = '';
    public string $lastName = '';
    public string $emailAddress = '';
    public string $phoneNumber = '';
}

class SnakeCaseUser
{
    public ?string $first_name = null;
    public ?string $last_name = null;
    public ?string $email_address = null;
    public ?string $phone_number = null;
}

class PascalCaseUser
{
    public string $FirstName = '';
    public string $LastName = '';
    public string $EmailAddress = '';
    public string $PhoneNumber = '';
}
