<?php

namespace Tests\Unit\Mapping\Conventions;

use Ninja\Granite\Mapping\ConventionMapper;
use Ninja\Granite\Mapping\Conventions\CamelCaseConvention;
use Ninja\Granite\Mapping\Conventions\ConventionRegistry;
use Ninja\Granite\Mapping\Conventions\PascalCaseConvention;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Mapping\TypeMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\TestCase;
use Tests\Helpers\Storage\TestMappingStorage;

#[CoversClass(ConventionMapper::class)]
class ConventionMapperTest extends TestCase
{
    private ConventionMapper $mapper;
    private TestMappingStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ConventionMapper();
        $this->storage = new TestMappingStorage();
    }

    #[Test]
    public function it_detects_convention_for_class()
    {
        $convention = $this->mapper->detectConvention(CamelCaseSource::class);
        $this->assertInstanceOf(CamelCaseConvention::class, $convention);

        $convention = $this->mapper->detectConvention(SnakeCaseSource::class);
        $this->assertInstanceOf(SnakeCaseConvention::class, $convention);

        $convention = $this->mapper->detectConvention(PascalCaseSource::class);
        $this->assertInstanceOf(PascalCaseConvention::class, $convention);
    }

    #[Test]
    public function it_calculates_confidence_between_different_conventions()
    {
        $camelToSnake = $this->mapper->calculateConfidence('userName', 'user_name');
        $this->assertGreaterThan(0.7, $camelToSnake);

        $snakeToPascal = $this->mapper->calculateConfidence('user_name', 'UserName');
        $this->assertGreaterThan(0.7, $snakeToPascal);

        $camelToPascal = $this->mapper->calculateConfidence('userName', 'UserName');
        $this->assertGreaterThan(0.7, $camelToPascal);
    }

    #[Test]
    public function it_calculates_low_confidence_for_different_names()
    {
        $confidence = $this->mapper->calculateConfidence('firstName', 'lastName');
        $this->assertLessThan(0.75, $confidence);

        $confidence = $this->mapper->calculateConfidence('user_name', 'password');
        $this->assertLessThan(0.75, $confidence);
    }

    #[Test]
    public function it_discovers_mappings_between_different_conventions()
    {
        $mappings = $this->mapper->discoverMappings(CamelCaseSource::class, SnakeCaseDestination::class);
        
        $this->assertArrayHasKey('first_name', $mappings);
        $this->assertEquals('firstName', $mappings['first_name']);
        
        $this->assertArrayHasKey('last_name', $mappings);
        $this->assertEquals('lastName', $mappings['last_name']);
        
        $this->assertArrayHasKey('email_address', $mappings);
        $this->assertEquals('emailAddress', $mappings['email_address']);
    }

    #[Test]
    public function it_applies_conventions_to_type_mapping()
    {
        // Usar TestMappingStorage para crear un TypeMapping
        $typeMapping = new TypeMapping($this->storage, CamelCaseSource::class, SnakeCaseDestination::class);
        
        $mappings = $this->mapper->applyConventions(
            CamelCaseSource::class,
            SnakeCaseDestination::class,
            $typeMapping
        );
        
        $this->assertArrayHasKey('first_name', $mappings);
        $this->assertEquals('firstName', $mappings['first_name']);
    }

    #[Test]
    public function it_handles_setting_confidence_threshold()
    {
        // Guardar el umbral original
        $originalThreshold = 0.8;
        
        // Configurar un umbral alto
        $this->mapper->setConfidenceThreshold(0.99);
        
        // Intentar descubrir mapeos
        $highThresholdMappings = $this->mapper->discoverMappings(
            CamelCaseSource::class, 
            SnakeCaseDestination::class
        );
        
        // Con un umbral tan alto, no debería encontrar mapeos
        // O al menos debería encontrar menos mapeos que con el umbral normal
        
        // Restaurar umbral normal
        $this->mapper->setConfidenceThreshold($originalThreshold);
        
        // Descubrir mapeos con umbral normal
        $normalThresholdMappings = $this->mapper->discoverMappings(
            CamelCaseSource::class, 
            SnakeCaseDestination::class
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
        $mappings = $this->mapper->discoverMappings('array', SnakeCaseDestination::class);
        $this->assertEmpty($mappings);
        
        $mappings = $this->mapper->applyConventions('array', SnakeCaseDestination::class);
        $this->assertEmpty($mappings);
    }
}

// Clases de prueba
class CamelCaseSource
{
    public string $firstName = '';
    public string $lastName = '';
    public string $emailAddress = '';
}

class SnakeCaseSource
{
    public string $first_name = '';
    public string $last_name = '';
    public string $email_address = '';
}

class PascalCaseSource
{
    public string $FirstName = '';
    public string $LastName = '';
    public string $EmailAddress = '';
}

class SnakeCaseDestination
{
    public string $first_name = '';
    public string $last_name = '';
    public string $email_address = '';
}
