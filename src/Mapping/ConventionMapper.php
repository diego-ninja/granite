<?php

namespace Ninja\Granite\Mapping;

use Ninja\Granite\Mapping\Contracts\NamingConvention;
use Ninja\Granite\Mapping\Conventions\ConventionRegistry;
use Ninja\Granite\Mapping\TypeMapping;
use Ninja\Granite\Support\ReflectionCache;
use ReflectionClass;
use ReflectionProperty;

class ConventionMapper
{
    /**
     * @var ConventionRegistry
     */
    private ConventionRegistry $registry;
    
    /**
     * @var float Confidence threshold for considering a match
     */
    private float $confidenceThreshold;
    
    /**
     * @var array Cache of discovered mappings
     */
    private array $discoveredMappings = [];
    
    public function __construct(
        ?ConventionRegistry $registry = null,
        float $confidenceThreshold = 0.8
    ) {
        $this->registry = $registry ?? new ConventionRegistry();
        $this->confidenceThreshold = $confidenceThreshold;
    }
    
    /**
     * Detects the naming convention for a type.
     *
     * @param string $typeName Class name
     * @return NamingConvention|null Detected convention or null if it cannot be determined
     * @throws \ReflectionException
     */
    public function detectConvention(string $typeName): ?NamingConvention
    {
        $reflection = new ReflectionClass($typeName);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        
        $conventions = $this->registry->getAll();
        $scores = array_fill_keys(array_keys($conventions), 0);
        
        foreach ($properties as $property) {
            $name = $property->getName();
            
            foreach ($conventions as $key => $convention) {
                if ($convention->matches($name)) {
                    $scores[$key]++;
                }
            }
        }
        
        // Determine the predominant convention
        arsort($scores);
        $topConvention = key($scores);
        
        return $scores[$topConvention] > 0 
            ? $conventions[$topConvention] 
            : null;
    }
    
    /**
     * Finds the best match between two properties based on conventions.
     *
     * @param string $sourceName Source property name
     * @param string $destinationName Destination property name
     * @return float Match confidence (0.0-1.0)
     */
    public function calculateConfidence(string $sourceName, string $destinationName): float
    {
        $sourceConventions = $this->registry->getAll();
        $destinationConventions = $this->registry->getAll();
        
        $highestConfidence = 0.0;
        
        foreach ($sourceConventions as $sourceConvention) {
            if (!$sourceConvention->matches($sourceName)) {
                continue;
            }
            
            $normalized = $sourceConvention->normalize($sourceName);
            
            foreach ($destinationConventions as $destinationConvention) {
                if (!$destinationConvention->matches($destinationName)) {
                    continue;
                }
                
                $destinationNormalized = $destinationConvention->normalize($destinationName);
                
                // Calculate similarity between normalized forms
                $similarity = $this->calculateStringSimilarity($normalized, $destinationNormalized);
                
                if ($similarity > $highestConfidence) {
                    $highestConfidence = $similarity;
                }
            }
        }
        
        return $highestConfidence;
    }
    
    /**
     * Calculates similarity between two strings.
     * Uses a combination of Levenshtein distance and sound similarity.
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        // Exactly equal
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Normalize to lowercase for comparison
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        // If equal after normalization
        if ($str1 === $str2) {
            return 0.95;
        }
        
        // Calculate similarity based on Levenshtein distance
        $maxLength = max(strlen($str1), strlen($str2));
        if ($maxLength === 0) {
            return 0.0;
        }
        
        $levenshtein = levenshtein($str1, $str2);
        $levenshteinSimilarity = 1.0 - ($levenshtein / $maxLength);
        
        // Calculate sound similarity (Soundex)
        $soundexSimilarity = soundex($str1) === soundex($str2) ? 0.7 : 0.0;
        
        // Combine similarities
        return max($levenshteinSimilarity, $soundexSimilarity);
    }
    
    /**
     * Discovers automatic mappings between two types.
     *
     * @param string $sourceType Source type
     * @param string $destinationType Destination type
     * @return array Discovered mappings [destinationProperty => sourceProperty]
     */
    public function discoverMappings(string $sourceType, string $destinationType): array
    {
        $cacheKey = $sourceType . '->' . $destinationType;
        
        if (isset($this->discoveredMappings[$cacheKey])) {
            return $this->discoveredMappings[$cacheKey];
        }
        
        // If source is not a class (e.g., 'array'), we can't use reflection on it
        if ($sourceType === 'array' || !class_exists($sourceType)) {
            // Return empty mappings since we can't determine properties
            $this->discoveredMappings[$cacheKey] = [];
            return [];
        }
        
        $sourceReflection = new \ReflectionClass($sourceType);
        $destinationReflection = new \ReflectionClass($destinationType);
        
        $sourceProperties = $sourceReflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        $destinationProperties = $destinationReflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        $sourceNames = array_map(fn($prop) => $prop->getName(), $sourceProperties);
        $destinationNames = array_map(fn($prop) => $prop->getName(), $destinationProperties);
        
        $mappings = [];
        
        foreach ($destinationNames as $destinationName) {
            $bestMatch = null;
            $bestConfidence = 0.0;
            
            // Evaluar todas las propiedades fuente para encontrar la mejor coincidencia
            foreach ($sourceNames as $sourceName) {
                $confidence = $this->calculateConfidence($sourceName, $destinationName);
                
                // Actualizar el mejor coincidente solo si la confianza es mayor que la anterior
                if ($confidence > $bestConfidence) {
                    $bestMatch = $sourceName;
                    $bestConfidence = $confidence;
                }
            }
            
            // Solo incluir en el mapeo si la mejor coincidencia supera el umbral
            if ($bestMatch !== null && $bestConfidence >= $this->confidenceThreshold) {
                $mappings[$destinationName] = $bestMatch;
            }
        }
        
        $this->discoveredMappings[$cacheKey] = $mappings;
        
        return $mappings;
    }
    
    /**
     * Applies the discovered mappings to a TypeMapping.
     *
     * @param string $sourceType Source type
     * @param string $destinationType Destination type
     * @param TypeMapping|null $typeMapping Existing type mapping (optional)
     * @return array The discovered mappings
     */
    public function applyConventions(string $sourceType, string $destinationType, ?TypeMapping $typeMapping = null): array
    {
        // Skip if the source type is 'array' or not a valid class
        if ($sourceType === 'array' || !class_exists($sourceType)) {
            return [];
        }
        
        $mappings = $this->discoverMappings($sourceType, $destinationType);
        
        if ($typeMapping !== null) {
            foreach ($mappings as $destinationName => $sourceName) {
                if ($destinationName !== $sourceName) {
                    $typeMapping->forMember($destinationName, fn($mapping) => $mapping->mapFrom($sourceName));
                }
            }
        }
        
        return $mappings;
    }
    
    /**
     * Sets the confidence threshold for matches.
     *
     * @param float $threshold Threshold between 0.0 and 1.0
     * @return $this For method chaining
     */
    public function setConfidenceThreshold(float $threshold): self
    {
        $this->confidenceThreshold = max(0.0, min(1.0, $threshold));
        return $this;
    }
    
    /**
     * Registers a new convention.
     *
     * @param NamingConvention $convention Convention to register
     * @return $this For method chaining
     */
    public function registerConvention(NamingConvention $convention): self
    {
        $this->registry->register($convention);
        return $this;
    }
    
    /**
     * Clears the cache of discovered mappings.
     * 
     * @return void
     */
    public function clearMappingsCache(): void
    {
        $this->discoveredMappings = [];
    }
}
