<?php

declare(strict_types=1);

namespace Tests\Unit\Hydration\Hydrators;

use JsonSerializable;
use LogicException;
use Ninja\Granite\Hydration\Hydrators\ObjectHydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(ObjectHydrator::class)]
final class ObjectHydratorTest extends TestCase
{
    public function test_private_to_array_falls_back_to_json_serializable(): void
    {
        $source = new class implements JsonSerializable {
            private function toArray(): array
            {
                self::failIfInvoked();
            }

            public function jsonSerialize(): array
            {
                return ['source' => 'json'];
            }

            private static function failIfInvoked(): never
            {
                throw new LogicException('Private toArray() must not be invoked');
            }
        };

        $result = (new ObjectHydrator())->hydrate($source, stdClass::class);

        $this->assertSame(['source' => 'json'], $result);
    }

    public function test_private_to_array_falls_back_to_public_properties(): void
    {
        $source = new class {
            public string $source = 'property';

            private function toArray(): array
            {
                throw new LogicException('Private toArray() must not be invoked');
            }
        };

        $result = (new ObjectHydrator())->hydrate($source, stdClass::class);

        $this->assertSame(['source' => 'property'], $result);
    }
}
