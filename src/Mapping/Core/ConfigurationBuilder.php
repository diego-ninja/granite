<?php
// ABOUTME: Defines ConfigurationBuilder as part of the object mapping pipeline.
// ABOUTME: Owns the ConfigurationBuilder boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Core;

use ArrayObject;
use BackedEnum;
use Closure;
use Ninja\Granite\Config\GraniteConfig;
use Ninja\Granite\Exceptions\ReflectionException;
use Ninja\Granite\Mapping\Cache\PersistentMappingCache;
use Ninja\Granite\Mapping\Cache\SharedMappingCache;
use Ninja\Granite\Mapping\Contracts\MappingCache;
use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\ConventionMapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Ninja\Granite\Mapping\MappingProfile;
use Ninja\Granite\Mapping\PropertyMapping;
use Ninja\Granite\Mapping\Traits\MappingStorageTrait;
use Ninja\Granite\Mapping\TypeMapping;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionClass;
use ReflectionProperty;
use ReflectionReference;
use SplObjectStorage;
use UnitEnum;

/**
 * Builds and manages mapping configurations.
 * Handles profile registration, convention mapping, and caching.
 */
final class ConfigurationBuilder
{
    use MappingStorageTrait;

    private static ?string $processFingerprint = null;
    private static ?string $compilerFingerprint = null;
    /** @var array<string, string> */
    private static array $classCodeFingerprints = [];
    /** @var array<string, string> */
    private static array $typePairCodeFingerprints = [];

    private MappingCache $cache;
    private ConventionMapper $conventionMapper;
    /** @var array<int, MappingProfile> */
    private array $profiles = [];
    /** @var array<int, int> */
    private array $profileRevisions = [];
    /** @var array<int, NamingConvention> */
    private array $conventions = [];
    private bool $useConventions;
    private float $conventionThreshold;
    private ?string $configurationFingerprint = null;
    private int $observedMappingRevision = 0;
    private ?string $observedConventionState = null;
    private int $observedGlobalConfigRevision;

    /** @param array<int, mixed> $conventions */
    public function __construct(
        MappingCache $cache,
        bool $useConventions = false,
        float $conventionThreshold = 0.8,
        array $conventions = [],
    ) {
        $this->cache = $cache;
        $this->useConventions = $useConventions;
        $this->conventionThreshold = $conventionThreshold;
        $this->conventionMapper = new ConventionMapper(null, $conventionThreshold);
        foreach ($conventions as $convention) {
            if ( ! $convention instanceof NamingConvention) {
                continue;
            }
            $this->conventions[] = $convention;
            $this->conventionMapper->registerConvention($convention);
        }
        $this->observedConventionState = $this->conventionStateFingerprint();
        $this->observedGlobalConfigRevision = GraniteConfig::getRevision();
    }

    /**
     * Get mapping configuration for source to destination.
     * @return array<string, array<string, mixed>>
     */
    public function getConfiguration(mixed $source, string $destinationType): array
    {
        $sourceType = is_object($source) ? get_class($source) : 'array';
        $this->refreshProfileRevisions();
        $cacheSourceType = $this->cacheSourceType($sourceType, $destinationType);

        // Check cache first
        if ($this->cache->has($cacheSourceType, $destinationType)) {
            return $this->cache->get($cacheSourceType, $destinationType) ?? [];
        }

        // Build new configuration
        $config = $this->buildConfiguration($sourceType, $destinationType);

        // Cache it
        $this->cache->put($cacheSourceType, $destinationType, $config);

        return $config;
    }

    public function preloadConfiguration(string $sourceType, string $destinationType): void
    {
        $this->refreshProfileRevisions();
        $cacheSourceType = $this->cacheSourceType($sourceType, $destinationType);
        if ($this->cache->has($cacheSourceType, $destinationType)) {
            return;
        }

        $this->cache->put(
            $cacheSourceType,
            $destinationType,
            $this->buildConfiguration($sourceType, $destinationType),
        );
    }

    /**
     * Create reverse configuration for existing mapping.
     * @throws MappingException
     */
    public function createReverseConfiguration(string $sourceType, string $destinationType, TypeMapping $reverseMapping): void
    {
        $originalConfig = $this->getConfiguration($sourceType, $destinationType);

        foreach ($originalConfig as $destProp => $config) {
            $sourceProp = $config['source'] ?? null;

            // Skip if no explicit source property or complex transformers
            if ( ! is_string($sourceProp) || $sourceProp === $destProp || ($config['transformer'] ?? null) !== null) {
                continue;
            }

            // Create reverse mapping
            $reverseMapping->forMember($sourceProp, fn(PropertyMapping $mapping) => $mapping->mapFrom($destProp));
        }
    }

    // =================
    // Profile Management
    // =================

    public function addProfile(MappingProfile $profile): void
    {
        $this->profiles[] = $profile;
        $this->profileRevisions[spl_object_id($profile)] = $profile->getMappingRevision();
        $this->invalidateConfigurationCaches();
    }

    public function addPropertyMapping(
        string $sourceType,
        string $destinationType,
        string $property,
        PropertyMapping $mapping,
    ): void {
        $key = $sourceType . '->' . $destinationType;
        $existingMapping = $this->mappings[$key][$property] ?? null;
        if ($existingMapping instanceof PropertyMapping) {
            $existingMapping->stopObservingMutations($this);
        }
        $this->mappings[$key][$property] = $mapping;
        $this->mappingRevision++;
        $this->observeMapping($mapping);
        $this->observedMappingRevision = $this->getMappingRevision();
        $this->invalidateConfigurationCaches();
    }

    /** @param array<int, mixed> $profiles */
    public function warmupCache(array $profiles): void
    {
        foreach ($profiles as $profile) {
            if ($profile instanceof MappingProfile) {
                $this->warmupProfileCache($profile);
            }
        }
    }

    // ===================
    // Convention Management
    // ===================

    public function enableConventions(bool $enabled): void
    {
        $this->useConventions = $enabled;
        $this->invalidateConfigurationCaches();
    }

    public function setConventionThreshold(float $threshold): void
    {
        $this->conventionThreshold = $threshold;
        $this->conventionMapper->setConfidenceThreshold($threshold);
        $this->invalidateConfigurationCaches();
    }

    public function registerConvention(NamingConvention $convention): void
    {
        $this->conventions[] = $convention;
        $this->conventionMapper->registerConvention($convention);
        $this->observedConventionState = $this->conventionStateFingerprint();
        $this->invalidateConfigurationCaches();
    }

    // ==============
    // Cache Management
    // ==============

    public function clearCache(): void
    {
        $this->cache->clear();
        $this->conventionMapper->clearMappingsCache();
    }

    public function hasCachedConfiguration(string $sourceType, string $destinationType): bool
    {
        $this->refreshProfileRevisions();

        return $this->cache->has($this->cacheSourceType($sourceType, $destinationType), $destinationType);
    }

    /** @internal Used by the public cache facade. */
    public function scopeCacheSourceType(string $sourceType, string $destinationType): string
    {
        $this->refreshProfileRevisions();

        return $this->cacheSourceType($sourceType, $destinationType);
    }

    private function invalidateConfigurationCaches(): void
    {
        $this->configurationFingerprint = null;
        if ( ! $this->cache instanceof SharedMappingCache && ! $this->cache instanceof PersistentMappingCache) {
            $this->cache->clear();
        }
        $this->conventionMapper->clearMappingsCache();
    }

    /**
     * Build mapping configuration from profiles and conventions.
     * @return array<string, array<string, mixed>>
     */
    private function buildConfiguration(string $sourceType, string $destinationType): array
    {
        $config = [];

        // Get destination properties
        $properties = $this->getDestinationProperties($destinationType);

        foreach ($properties as $property) {
            $propertyName = $property->getName();

            // Check for explicit mapping from profiles
            $mapping = $this->findExplicitMapping($sourceType, $destinationType, $propertyName);

            if (null !== $mapping) {
                $config[$propertyName] = $this->buildPropertyConfig($mapping, $propertyName);
            } else {
                // Build from attributes or conventions
                $config[$propertyName] = $this->buildPropertyFromAttributes($property, $sourceType, $destinationType);
            }
        }

        // Apply convention-based mapping if enabled
        if ($this->useConventions) {
            $config = $this->applyConventionMappings($sourceType, $destinationType, $config);
        }

        return $config;
    }

    /**
     * Find explicit mapping from registered profiles.
     */
    private function findExplicitMapping(string $sourceType, string $destinationType, string $property): ?PropertyMapping
    {
        // Check direct mappings first
        $mapping = $this->getMapping($sourceType, $destinationType, $property);
        if (null !== $mapping) {
            return $mapping;
        }

        // Check profiles
        foreach ($this->profiles as $profile) {
            $mapping = $profile->getMapping($sourceType, $destinationType, $property);
            if ($mapping instanceof PropertyMapping) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * Build property configuration from PropertyMapping.
     */
    /** @return array<string, mixed> */
    private function buildPropertyConfig(PropertyMapping $mapping, string $propertyName): array
    {
        return $mapping->toConfig($propertyName);
    }

    /**
     * Build property configuration from attributes.
     */
    /** @return array<string, mixed> */
    private function buildPropertyFromAttributes(ReflectionProperty $property, string $sourceType, string $destinationType): array
    {
        $attributeProcessor = new AttributeProcessor();
        return $attributeProcessor->processProperty($property);
    }

    /**
     * Apply convention-based mappings.
     */
    /** @param array<string, array<string, mixed>> $config
     * @return array<string, array<string, mixed>>
     */
    private function applyConventionMappings(string $sourceType, string $destinationType, array $config): array
    {
        if ('array' === $sourceType || ! class_exists($sourceType) || ! class_exists($destinationType)) {
            return $config;
        }

        $conventionMappings = $this->conventionMapper->discoverMappings($sourceType, $destinationType);

        foreach ($conventionMappings as $destProperty => $sourceProperty) {
            // Only apply if no explicit mapping exists
            if ( ! isset($config[$destProperty]) || $config[$destProperty]['source'] === $destProperty) {
                $config[$destProperty]['source'] = $sourceProperty;
            }
        }

        return $config;
    }

    /**
     * Get destination type properties.
     * @param string $destinationType
     * @throws ReflectionException
     */
    /** @return array<int, ReflectionProperty> */
    private function getDestinationProperties(string $destinationType): array
    {
        if ( ! class_exists($destinationType)) {
            return [];
        }

        return array_values(ReflectionCache::getPublicProperties($destinationType));
    }

    private function warmupProfileCache(MappingProfile $profile): void
    {
        $this->refreshProfileRevisions();
        foreach ($profile->configuredTypePairs() as [$sourceType, $destinationType]) {
            $cacheSourceType = $this->cacheSourceType($sourceType, $destinationType);
            if ( ! $this->cache->has($cacheSourceType, $destinationType)) {
                $config = $this->buildConfiguration($sourceType, $destinationType);
                $this->cache->put($cacheSourceType, $destinationType, $config);
            }
        }
    }

    private function refreshProfileRevisions(): void
    {
        $globalConfigRevision = GraniteConfig::getRevision();
        if ($this->observedGlobalConfigRevision !== $globalConfigRevision) {
            $this->observedGlobalConfigRevision = $globalConfigRevision;
            $this->invalidateConfigurationCaches();
        }

        $mappingRevision = $this->getMappingRevision();
        if ($this->observedMappingRevision !== $mappingRevision) {
            $this->observedMappingRevision = $mappingRevision;
            $this->invalidateConfigurationCaches();
        }

        $conventionState = $this->conventionStateFingerprint();
        if ($this->observedConventionState !== $conventionState) {
            $this->observedConventionState = $conventionState;
            $this->invalidateConfigurationCaches();
        }

        foreach ($this->profiles as $profile) {
            $objectId = spl_object_id($profile);
            $revision = $profile->getMappingRevision();
            if (($this->profileRevisions[$objectId] ?? null) === $revision) {
                continue;
            }

            $this->profileRevisions[$objectId] = $revision;
            $this->invalidateConfigurationCaches();
            break;
        }
    }

    private function conventionStateFingerprint(): string
    {
        return hash('sha256', serialize($this->normalizeFingerprintValue($this->conventions)));
    }

    private function cacheSourceType(string $sourceType, string $destinationType): string
    {
        if ( ! $this->cache instanceof SharedMappingCache && ! $this->cache instanceof PersistentMappingCache) {
            return $sourceType;
        }

        $fingerprint = $this->configurationFingerprint ??= $this->buildConfigurationFingerprint();
        $pair = $sourceType . '->' . $destinationType;
        $codeFingerprint = self::$typePairCodeFingerprints[$pair] ??= hash('sha256', implode('|', [
            $this->classCodeFingerprint($sourceType),
            $this->classCodeFingerprint($destinationType),
        ]));

        return $sourceType . '#' . $fingerprint . '#' . $codeFingerprint;
    }

    private function buildConfigurationFingerprint(): string
    {
        $profileMappings = [];
        foreach ($this->profiles as $profile) {
            $mappings = [];
            foreach ($profile->configuredTypePairs() as [$sourceType, $destinationType]) {
                foreach ($profile->getMappingsForTypes($sourceType, $destinationType) as $property => $mapping) {
                    $mappings[$sourceType . '->' . $destinationType][$property] = $mapping->toConfig($property);
                }
            }
            $profileMappings[] = [
                'class' => $profile::class,
                'code' => $this->classCodeFingerprint($profile::class),
                'mappings' => $mappings,
            ];
        }

        $directMappings = [];
        foreach ($this->mappings as $pair => $mappings) {
            foreach ($mappings as $property => $mapping) {
                $directMappings[$pair][$property] = $mapping->toConfig($property);
            }
        }

        $configurationState = [
            'schema' => 2,
            'compiler' => $this->mappingCompilerFingerprint(),
            'graniteConfig' => GraniteConfig::getInstance()->fingerprint(),
            'useConventions' => $this->useConventions,
            'conventionThreshold' => $this->conventionThreshold,
            'conventions' => $this->conventions,
            'profiles' => $profileMappings,
            'mappings' => $directMappings,
        ];
        $hasRuntimeOnlyConfiguration = $this->containsRuntimeOnlyValue($configurationState);
        $configuration = [];
        foreach ($configurationState as $key => $value) {
            $configuration[$key] = $this->normalizeFingerprintValue($value);
        }

        if ($hasRuntimeOnlyConfiguration) {
            self::$processFingerprint ??= bin2hex(random_bytes(16));
            $configuration['process'] = self::$processFingerprint;
        }

        return hash('sha256', serialize($configuration));
    }

    /**
     * @param SplObjectStorage<object, int>|null $seen
     * @param ArrayObject<string, int>|null $seenReferences
     */
    private function normalizeFingerprintValue(
        mixed $value,
        ?SplObjectStorage $seen = null,
        ?ArrayObject $seenReferences = null,
    ): mixed {
        $seen ??= new SplObjectStorage();
        $seenReferences ??= new ArrayObject();

        if (is_array($value)) {
            if ( ! array_is_list($value)) {
                ksort($value);
            }
            $normalized = [];
            foreach ($value as $key => $nestedValue) {
                $reference = ReflectionReference::fromArrayElement($value, $key);
                if (null !== $reference) {
                    $referenceId = bin2hex($reference->getId());
                    if (isset($seenReferences[$referenceId])) {
                        $normalized[$key] = ['arrayReference' => $seenReferences[$referenceId]];
                        continue;
                    }
                    $seenReferences[$referenceId] = count($seenReferences);
                }

                $normalized[$key] = $this->normalizeFingerprintValue($nestedValue, $seen, $seenReferences);
            }
            return $normalized;
        }

        if ($value instanceof UnitEnum) {
            return [
                'enum' => $value::class,
                'value' => $value instanceof BackedEnum ? $value->value : $value->name,
            ];
        }

        if ($value instanceof Closure) {
            return ['closure' => spl_object_id($value)];
        }

        if (is_object($value)) {
            if (isset($seen[$value])) {
                return ['reference' => $seen[$value]];
            }

            $seen[$value] = count($seen);
            $normalized = [
                'class' => $value::class,
                'code' => $this->classCodeFingerprint($value::class),
                'state' => $this->normalizeFingerprintValue((array) $value, $seen, $seenReferences),
            ];

            if ( ! $value instanceof NamingConvention) {
                $normalized['runtime'] = spl_object_id($value);
            }

            return $normalized;
        }

        if (is_resource($value)) {
            return ['resource' => get_resource_type($value), 'id' => get_resource_id($value)];
        }

        if (is_float($value) && ! is_finite($value)) {
            return ['float' => (string) $value];
        }

        if (is_string($value) && 1 !== preg_match('//u', $value)) {
            return ['binary' => base64_encode($value)];
        }

        return $value;
    }

    /** @param SplObjectStorage<object, true>|null $seen */
    private function containsRuntimeOnlyValue(mixed $value, ?SplObjectStorage $seen = null): bool
    {
        $seen ??= new SplObjectStorage();

        if (is_array($value)) {
            foreach ($value as $key => $nestedValue) {
                if (null !== ReflectionReference::fromArrayElement($value, $key)
                    || $this->containsRuntimeOnlyValue($nestedValue, $seen)) {
                    return true;
                }
            }

            return false;
        }

        if ($value instanceof NamingConvention) {
            if (isset($seen[$value])) {
                return false;
            }
            $seen[$value] = true;

            return $this->containsRuntimeOnlyValue((array) $value, $seen);
        }

        return $value instanceof Closure || is_object($value) || is_resource($value);
    }

    private function classCodeFingerprint(string $class): string
    {
        if (isset(self::$classCodeFingerprints[$class])) {
            return self::$classCodeFingerprints[$class];
        }

        if ( ! class_exists($class) && ! interface_exists($class) && ! enum_exists($class)) {
            return self::$classCodeFingerprints[$class] = $class;
        }

        try {
            $reflection = ReflectionCache::getClass($class);
            $reflections = [$reflection];
            $parent = $reflection->getParentClass();
            while (false !== $parent) {
                $reflections[] = $parent;
                $parent = $parent->getParentClass();
            }

            $files = [];
            foreach ($reflections as $classReflection) {
                $fileName = $classReflection->getFileName();
                if (false !== $fileName && is_file($fileName)) {
                    $files[$fileName] = true;
                }
                $this->collectTraitFiles($classReflection, $files);
            }

            ksort($files);
            $hashes = [];
            foreach (array_keys($files) as $file) {
                $hashes[$file] = hash_file('sha256', $file) ?: '';
            }

            return self::$classCodeFingerprints[$class] = hash(
                'sha256',
                json_encode($hashes, JSON_THROW_ON_ERROR),
            );
        } catch (ReflectionException) {
            return self::$classCodeFingerprints[$class] = $class;
        }
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<string, bool> $files
     */
    private function collectTraitFiles(ReflectionClass $reflection, array &$files): void
    {
        foreach ($reflection->getTraits() as $trait) {
            $fileName = $trait->getFileName();
            if (false !== $fileName && is_file($fileName)) {
                $files[$fileName] = true;
            }
            $this->collectTraitFiles($trait, $files);
        }
    }

    private function mappingCompilerFingerprint(): string
    {
        if (null !== self::$compilerFingerprint) {
            return self::$compilerFingerprint;
        }

        $patterns = [
            dirname(__DIR__) . '/*.php',
            dirname(__DIR__) . '/*/*.php',
            dirname(__DIR__, 2) . '/Serialization/Attributes/*.php',
            dirname(__DIR__, 2) . '/Serialization/Carbon*.php',
            dirname(__DIR__, 2) . '/Transformers/*.php',
            dirname(__DIR__, 2) . '/Support/ReflectionCache.php',
        ];
        $files = [];
        foreach ($patterns as $pattern) {
            $matchedFiles = glob($pattern);
            if (false !== $matchedFiles) {
                $files = [...$files, ...$matchedFiles];
            }
        }
        $files = array_values(array_unique($files));
        sort($files);

        $hashes = [];
        foreach ($files as $file) {
            if (is_file($file)) {
                $hashes[$file] = hash_file('sha256', $file) ?: '';
            }
        }

        return self::$compilerFingerprint = hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
    }
}
