<?php

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Attributes\MapCollection;
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWhen;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Ninja\Granite\Transformers\CarbonTransformer;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * Enhanced attribute processor with Carbon support.
 * Processes property attributes to build mapping configuration.
 */
final readonly class AttributeProcessor
{
    /**
     * Process property attributes to build configuration array.
     *
     * @param ReflectionProperty $property Property to process
     * @return array<string, mixed> Property configuration
     */
    public function processProperty(ReflectionProperty $property): array
    {
        $config = [
            'source' => $property->getName(),
            'transformer' => null,
            'condition' => null,
            'default' => null,
            'hasDefault' => false,
            'ignore' => false,
        ];

        $attributes = $property->getAttributes();

        foreach ($attributes as $attribute) {
            $this->processAttribute($attribute, $config);
        }

        return $config;
    }

    /**
     * Build Carbon transformer from individual Carbon attributes.
     *
     * @param ReflectionProperty $property Property to check for Carbon attributes
     * @return CarbonTransformer|null Carbon transformer or null if no Carbon attributes found
     */
    public function buildCarbonTransformer(ReflectionProperty $property): ?CarbonTransformer
    {
        // Check for comprehensive CarbonDate attribute first
        $carbonDateAttrs = $property->getAttributes(CarbonDate::class, ReflectionAttribute::IS_INSTANCEOF);
        if ( ! empty($carbonDateAttrs)) {
            /** @var CarbonDate $attr */
            $attr = $carbonDateAttrs[0]->newInstance();
            return $attr->createTransformer();
        }

        // Build from individual attributes
        $parseRelative = true;
        $min = null;
        $max = null;


        // Process Range
        $rangeAttrs = $property->getAttributes(CarbonRange::class, ReflectionAttribute::IS_INSTANCEOF);
        if ( ! empty($rangeAttrs)) {
            /** @var CarbonRange $rangeAttr */
            $rangeAttr = $rangeAttrs[0]->newInstance();
            $min = $rangeAttr->min;
            $max = $rangeAttr->max;
        }

        // Process CarbonRelative
        $relativeAttrs = $property->getAttributes(CarbonRelative::class, ReflectionAttribute::IS_INSTANCEOF);
        if ( ! empty($relativeAttrs)) {
            /** @var CarbonRelative $relativeAttr */
            $relativeAttr = $relativeAttrs[0]->newInstance();
            $parseRelative = $relativeAttr->enabled;
        }

        // Only create transformer if we have some Carbon configuration
        if (null !== $min || null !== $max || ! $parseRelative) {

            return new CarbonTransformer(
                parseRelative: $parseRelative,
                min: $min,
                max: $max,
            );
        }

        return null;
    }

    /**
     * Check if property has any Carbon-related attributes.
     *
     * @param ReflectionProperty $property Property to check
     * @return bool True if property has Carbon attributes
     */
    public function hasCarbonAttributes(ReflectionProperty $property): bool
    {
        $carbonAttributeClasses = [
            CarbonDate::class,
            CarbonRange::class,
            CarbonRelative::class,
        ];

        foreach ($carbonAttributeClasses as $attrClass) {
            $attrs = $property->getAttributes($attrClass, ReflectionAttribute::IS_INSTANCEOF);
            if ( ! empty($attrs)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all Carbon attribute instances from a property.
     *
     * @param ReflectionProperty $property Property to check
     * @return array<object> Array of Carbon attribute instances
     */
    public function getCarbonAttributes(ReflectionProperty $property): array
    {
        $carbonAttributeClasses = [
            CarbonDate::class,
            CarbonRange::class,
            CarbonRelative::class,
        ];

        $instances = [];

        foreach ($carbonAttributeClasses as $attrClass) {
            $attrs = $property->getAttributes($attrClass, ReflectionAttribute::IS_INSTANCEOF);
            foreach ($attrs as $attr) {
                $instances[] = $attr->newInstance();
            }
        }

        return $instances;
    }

    /**
     * Process individual attribute and update configuration.
     *
     * @param ReflectionAttribute $attribute Attribute to process
     * @param array<string, mixed> $config Configuration array to update
     * @return void
     */
    private function processAttribute(ReflectionAttribute $attribute, array &$config): void
    {
        $attrName = $attribute->getName();
        $attrInstance = $attribute->newInstance();

        switch ($attrName) {
            case Ignore::class:
                $config['ignore'] = true;
                break;

            case MapFrom::class:
                if ($attrInstance instanceof MapFrom) {
                    $config['source'] = $attrInstance->source;
                }
                break;

            case MapWith::class:
                if ($attrInstance instanceof MapWith) {
                    $config['transformer'] = $attrInstance->transformer;
                }
                break;

            case MapWhen::class:
                if ($attrInstance instanceof MapWhen) {
                    $config['condition'] = $attrInstance->condition;
                }
                break;

            case MapDefault::class:
                if ($attrInstance instanceof MapDefault) {
                    $config['default'] = $attrInstance->value;
                    $config['hasDefault'] = true;
                }
                break;

            case MapCollection::class:
                if ($attrInstance instanceof MapCollection) {
                    $config['transformer'] = $attrInstance->createTransformer(null);
                }
                break;

                // Carbon-specific attributes
            case CarbonDate::class:
                if ($attrInstance instanceof CarbonDate) {
                    $config['transformer'] = $attrInstance->createTransformer();
                }
                break;
            case CarbonRange::class:
            case CarbonRelative::class:
                // These are handled by getCarbonTransformerFromAttributes in GraniteDTO
                // We mark that Carbon attributes are present
                $config['hasCarbonAttributes'] = true;
                break;
        }
    }
}
