<?php

namespace Ninja\Granite\Mapping\Core;

use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Attributes\MapCollection;
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWhen;
use Ninja\Granite\Mapping\Attributes\MapWith;
use ReflectionAttribute;
use ReflectionProperty;

final readonly class AttributeProcessor
{
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

    private function processAttribute(ReflectionAttribute $attribute, array &$config): void
    {
        $attrName = $attribute->getName();
        $attrInstance = $attribute->newInstance();

        switch ($attrName) {
            case Ignore::class:
                $config['ignore'] = true;
                break;

            case MapFrom::class:
                $config['source'] = $attrInstance->source;
                break;

            case MapWith::class:
                $config['transformer'] = $attrInstance->transformer;
                break;

            case MapWhen::class:
                $config['condition'] = $attrInstance->condition;
                break;

            case MapDefault::class:
                $config['default'] = $attrInstance->value;
                $config['hasDefault'] = true;
                break;

            case MapCollection::class:
                $config['transformer'] = $attrInstance->createTransformer(null);
                break;
        }
    }
}