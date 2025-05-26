<?php

namespace Ninja\Granite\Mapping\Core;

use Exception;
use Ninja\Granite\Exceptions\GraniteException;
use Ninja\Granite\Mapping\Exceptions\MappingException;

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

    public function __construct(ConfigurationBuilder $configBuilder)
    {
        $this->configBuilder = $configBuilder;
        $this->sourceNormalizer = new SourceNormalizer();
        $this->dataTransformer = new DataTransformer();
        $this->objectFactory = new ObjectFactory();
    }

    /**
     * Map source data to destination type.
     * @throws MappingException
     * @throws GraniteException
     */
    public function map(mixed $source, string $destinationType): object
    {
        $this->validateDestinationType($destinationType);

        try {
            $sourceData = $this->sourceNormalizer->normalize($source);
            $config = $this->configBuilder->getConfiguration($source, $destinationType);
            $transformedData = $this->dataTransformer->transform($sourceData, $config);

            return $this->objectFactory->create($transformedData, $destinationType);

        } catch (GraniteException $e) {
            throw $e;
        } catch (Exception $e) {
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
            $transformedData = $this->dataTransformer->transform($sourceData, $config);

            return $this->objectFactory->populate($destination, $transformedData);

        } catch (Exception $e) {
            throw $this->createMappingException($source, get_class($destination), $e);
        }
    }

    /**
     * Validate that destination type exists and is instantiable.
     * @throws MappingException
     */
    private function validateDestinationType(string $destinationType): void
    {
        if (!class_exists($destinationType)) {
            throw MappingException::destinationTypeNotFound($destinationType);
        }
    }

    /**
     * Create a mapping exception with context.
     */
    private function createMappingException(mixed $source, string $destinationType, Exception $previous): MappingException
    {
        $sourceType = is_object($source) ? get_class($source) : gettype($source);

        return new MappingException(
            $sourceType,
            $destinationType,
            "Mapping failed: " . $previous->getMessage(),
            null,
            0,
            $previous
        );
    }
}