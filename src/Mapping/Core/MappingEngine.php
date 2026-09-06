<?php
// ABOUTME: Defines MappingEngine as part of the object mapping pipeline.
// ABOUTME: Owns the MappingEngine boundary between mapping configuration and execution.

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\Contracts\Mapper;
use Ninja\Granite\Mapping\Exceptions\MappingException;
use Throwable;

/**
 * Core mapping engine responsible for executing mapping operations.
 * Delegates specific tasks to specialized components.
 */
final class MappingEngine
{
    private SourceNormalizer $sourceNormalizer;
    private DataTransformer $dataTransformer;
    private ObjectFactory $objectFactory;
    private ConfigurationBuilder $configBuilder;

    public function __construct(ConfigurationBuilder $configBuilder, Mapper $mapper)
    {
        $this->configBuilder = $configBuilder;
        $this->sourceNormalizer = new SourceNormalizer();
        $this->dataTransformer = new DataTransformer($mapper);
        $this->objectFactory = new ObjectFactory();
    }

    /**
     * Map source data to destination type.
     * @throws MappingException
     * @throws GraniteException
     */
    public function map(mixed $source, string $destinationType): object
    {
        $destinationType = $this->validateDestinationType($destinationType);

        try {
            $sourceData = $this->sourceNormalizer->normalize($source);
            $config = $this->configBuilder->getConfiguration($source, $destinationType);
            $transformedData = $this->dataTransformer->transform($sourceData, $config, $destinationType);

            return $this->objectFactory->create($transformedData, $destinationType);

        } catch (GraniteException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->createMappingException($source, $destinationType, $e);
        }
    }

    /**
     * Map source data to existing destination object.
     * @throws MappingException
     */
    public function mapTo(mixed $source, object $destination): object
    {
        try {
            $sourceData = $this->sourceNormalizer->normalize($source);
            $config = $this->configBuilder->getConfiguration($source, get_class($destination));
            $transformedData = $this->dataTransformer->transform($sourceData, $config, get_class($destination));

            return $this->objectFactory->populate($destination, $transformedData);

        } catch (GraniteException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw $this->createMappingException($source, get_class($destination), $e);
        }
    }

    /**
     * Validate that destination type exists and is instantiable.
     * @throws MappingException
     */
    /** @return class-string */
    private function validateDestinationType(string $destinationType): string
    {
        if ( ! class_exists($destinationType)) {
            throw MappingException::destinationTypeNotFound($destinationType);
        }

        return $destinationType;
    }

    /**
     * Create a mapping exception with context.
     */
    private function createMappingException(mixed $source, string $destinationType, Throwable $previous): MappingException
    {
        $sourceType = is_object($source) ? get_class($source) : gettype($source);

        return new MappingException(
            $sourceType,
            $destinationType,
            "Mapping failed: " . $previous->getMessage(),
            null,
            0,
            $previous,
        );
    }
}
