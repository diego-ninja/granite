<?php

namespace Ninja\Granite\Monads\Traits;

use Ninja\Granite\Monads\Contracts\Maybe;
use Ninja\Granite\Monads\Factories\Maybe;

trait MaybeTrait
{
    /**
     * Get property value as Maybe
     */
    public function getMaybe(string $property): Maybe
    {
        try {
            $reflection = new \ReflectionClass($this);

            if (!$reflection->hasProperty($property)) {
                return Maybe::none();
            }

            $prop = $reflection->getProperty($property);

            if (!$prop->isInitialized($this)) {
                return Maybe::none();
            }

            return Maybe::of($prop->getValue($this));
        } catch (\Throwable) {
            return Maybe::none();
        }
    }

    /**
     * Safe property access with transformation
     */
    public function mapProperty(string $property, callable $mapper): Maybe
    {
        return $this->getMaybe($property)->map($mapper);
    }

    /**
     * Chain property access safely
     */
    public function flatMapProperty(string $property, callable $mapper): Maybe
    {
        return $this->getMaybe($property)->flatMap($mapper);
    }

    /**
     * Get nested property safely
     */
    public function getNestedMaybe(string ...$properties): Maybe
    {
        $current = Maybe::some($this);

        foreach ($properties as $property) {
            $current = $current->flatMap(function($obj) use ($property) {
                if ($obj === null) {
                    return Maybe::none();
                }

                if (method_exists($obj, 'getMaybe')) {
                    return $obj->getMaybe($property);
                }

                if (is_object($obj)) {
                    try {
                        $reflection = new \ReflectionClass($obj);
                        if ($reflection->hasProperty($property)) {
                            $prop = $reflection->getProperty($property);
                            if ($prop->isInitialized($obj)) {
                                return Maybe::of($prop->getValue($obj));
                            }
                        }
                    } catch (\Throwable) {
                        // Fall through to return None
                    }
                }

                return Maybe::none();
            });
        }

        return $current;
    }
}