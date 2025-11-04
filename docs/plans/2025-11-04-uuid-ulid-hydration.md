# UUID/ULID/Custom ID Hydration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add automatic UUID/ULID/Custom ID conversion during object hydration using `from()` or `fromString()` factory methods.

**Architecture:** Extend `HasTypeConversion` trait with new `convertToUuidLike()`, `looksLikeIdClass()`, and `tryCreateFromValue()` methods. Integrate UUID/ULID check into `convertToNamedType()` after Carbon checks but before GraniteObject checks.

**Tech Stack:** PHP 8.1+, PHPUnit, ramsey/uuid (optional), symfony/uid (optional)

---

## Task 1: Create Test Fixture Classes

**Files:**
- Create: `tests/Fixtures/VOs/CustomUuid.php`
- Create: `tests/Fixtures/VOs/Rcuid.php`
- Create: `tests/Fixtures/VOs/UserId.php`
- Create: `tests/Fixtures/VOs/InvalidId.php`

**Step 1: Create CustomUuid fixture with both from() and fromString()**

Create file `tests/Fixtures/VOs/CustomUuid.php`:

```php
<?php

namespace Tests\Fixtures\VOs;

use InvalidArgumentException;

readonly class CustomUuid
{
    private function __construct(
        public string $value
    ) {
    }

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return new self($value);
        }

        throw new InvalidArgumentException('CustomUuid requires a string value');
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

**Step 2: Create Rcuid fixture with only fromString()**

Create file `tests/Fixtures/VOs/Rcuid.php`:

```php
<?php

namespace Tests\Fixtures\VOs;

use InvalidArgumentException;

readonly class Rcuid
{
    private function __construct(
        public string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        if (empty($value)) {
            throw new InvalidArgumentException('Rcuid cannot be empty');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```

**Step 3: Create UserId fixture with only from()**

Create file `tests/Fixtures/VOs/UserId.php`:

```php
<?php

namespace Tests\Fixtures\VOs;

use InvalidArgumentException;

readonly class UserId
{
    private function __construct(
        public string $value
    ) {
    }

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) || is_int($value)) {
            return new self((string) $value);
        }

        throw new InvalidArgumentException('UserId requires string or int value');
    }

    public function toString(): string
    {
        return $this->value;
    }
}
```

**Step 4: Create InvalidId fixture that throws exceptions**

Create file `tests/Fixtures/VOs/InvalidId.php`:

```php
<?php

namespace Tests\Fixtures\VOs;

use RuntimeException;

readonly class InvalidId
{
    private function __construct(
        public string $value
    ) {
    }

    public static function from(mixed $value): self
    {
        throw new RuntimeException('from() always fails');
    }

    public static function fromString(string $value): self
    {
        throw new RuntimeException('fromString() always fails');
    }
}
```

**Step 5: Commit fixture classes**

```bash
git add tests/Fixtures/VOs/
git commit -m "test: add UUID/ULID fixture classes for testing"
```

---

## Task 2: Add Helper Methods to Test Class

**Files:**
- Modify: `tests/Unit/Traits/HasTypeConversionTest.php:299-316`

**Step 1: Add test methods to TestClassWithTypeConversion**

In `tests/Unit/Traits/HasTypeConversionTest.php`, add these methods to the `TestClassWithTypeConversion` class (after line 316):

```php
    public function testConvertToUuidLike($value, $typeName)
    {
        return self::convertToUuidLike($value, $typeName);
    }

    public function testLooksLikeIdClass($className)
    {
        return self::looksLikeIdClass($className);
    }

    public function testTryCreateFromValue($value, $className)
    {
        return self::tryCreateFromValue($value, $className);
    }
```

**Step 2: Add UUID/ULID properties to TestTypeConversionClass**

In `tests/Unit/Traits/HasTypeConversionTest.php`, add these properties to the `TestTypeConversionClass` class (after line 368):

```php
    // UUID/ULID properties for testing
    public \Tests\Fixtures\VOs\CustomUuid $customUuid;
    public \Tests\Fixtures\VOs\Rcuid $rcuid;
    public \Tests\Fixtures\VOs\UserId $userId;
    public \Tests\Fixtures\VOs\InvalidId $invalidId;
```

**Step 3: Commit test helper additions**

```bash
git add tests/Unit/Traits/HasTypeConversionTest.php
git commit -m "test: add UUID/ULID test helper methods"
```

---

## Task 3: Write Tests for looksLikeIdClass()

**Files:**
- Modify: `tests/Unit/Traits/HasTypeConversionTest.php` (add tests before final closing brace)

**Step 1: Write failing test for looksLikeIdClass() with UUID classes**

Add this test method to `HasTypeConversionTest` class:

```php
    public function test_looks_like_id_class_uuid_variants(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // Should match "uuid"
        $this->assertTrue($testClass->testLooksLikeIdClass('CustomUuid'));
        $this->assertTrue($testClass->testLooksLikeIdClass('App\\OrderUuid'));

        // Should match "ulid"
        $this->assertTrue($testClass->testLooksLikeIdClass('Ulid'));
        $this->assertTrue($testClass->testLooksLikeIdClass('App\\Domain\\Ulid'));

        // Should match "uid"
        $this->assertTrue($testClass->testLooksLikeIdClass('Rcuid'));
        $this->assertTrue($testClass->testLooksLikeIdClass('CustomUid'));

        // Should match "id"
        $this->assertTrue($testClass->testLooksLikeIdClass('UserId'));
        $this->assertTrue($testClass->testLooksLikeIdClass('OrderId'));

        // Should not match
        $this->assertFalse($testClass->testLooksLikeIdClass('Customer'));
        $this->assertFalse($testClass->testLooksLikeIdClass('OrderStatus'));
    }
```

**Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php::test_looks_like_id_class_uuid_variants`

Expected: FAIL with "Call to undefined method TestClassWithTypeConversion::testLooksLikeIdClass()"

**Step 3: Implement looksLikeIdClass() method**

In `src/Traits/HasTypeConversion.php`, add this private static method (after line 211, before the final closing brace):

```php
    /**
     * Check if a class name looks like an ID class based on naming heuristics.
     *
     * @param string $className Fully qualified class name
     * @return bool True if class name contains uuid, ulid, uid, or id
     */
    private static function looksLikeIdClass(string $className): bool
    {
        $baseName = class_basename($className);
        return (bool) preg_match('/uuid|ulid|uid|id/i', $baseName);
    }
```

**Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php::test_looks_like_id_class_uuid_variants`

Expected: PASS

**Step 5: Commit looksLikeIdClass() implementation**

```bash
git add src/Traits/HasTypeConversion.php tests/Unit/Traits/HasTypeConversionTest.php
git commit -m "feat: add looksLikeIdClass() helper for ID class detection"
```

---

## Task 4: Write Tests for tryCreateFromValue()

**Files:**
- Modify: `tests/Unit/Traits/HasTypeConversionTest.php`

**Step 1: Write failing test for tryCreateFromValue() with from() method**

Add this test to `HasTypeConversionTest` class:

```php
    public function test_try_create_from_value_with_from_method(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testTryCreateFromValue(
            'user-123',
            \Tests\Fixtures\VOs\UserId::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\UserId::class, $result);
        $this->assertEquals('user-123', $result->value);
    }
```

**Step 2: Write failing test for tryCreateFromValue() with fromString() method**

Add this test:

```php
    public function test_try_create_from_value_with_from_string_method(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testTryCreateFromValue(
            'rc-456',
            \Tests\Fixtures\VOs\Rcuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\Rcuid::class, $result);
        $this->assertEquals('rc-456', $result->value);
    }
```

**Step 3: Write failing test for tryCreateFromValue() preferring from() over fromString()**

Add this test:

```php
    public function test_try_create_from_value_prefers_from_over_from_string(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // CustomUuid has both methods - should use from()
        $result = $testClass->testTryCreateFromValue(
            'custom-789',
            \Tests\Fixtures\VOs\CustomUuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\CustomUuid::class, $result);
        $this->assertEquals('custom-789', $result->value);
    }
```

**Step 4: Write failing test for tryCreateFromValue() with already correct instance**

Add this test:

```php
    public function test_try_create_from_value_already_correct_type(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $userId = \Tests\Fixtures\VOs\UserId::from('existing');
        $result = $testClass->testTryCreateFromValue(
            $userId,
            \Tests\Fixtures\VOs\UserId::class
        );

        $this->assertSame($userId, $result);
    }
```

**Step 5: Write failing test for tryCreateFromValue() with exception handling**

Add this test:

```php
    public function test_try_create_from_value_handles_exceptions(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // InvalidId throws exceptions from both methods
        $result = $testClass->testTryCreateFromValue(
            'invalid',
            \Tests\Fixtures\VOs\InvalidId::class
        );

        // Should return original value unchanged
        $this->assertEquals('invalid', $result);
    }
```

**Step 6: Write failing test for tryCreateFromValue() without factory methods**

Add this test:

```php
    public function test_try_create_from_value_no_factory_methods(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // stdClass has no from() or fromString()
        $result = $testClass->testTryCreateFromValue(
            'test-value',
            \stdClass::class
        );

        // Should return original value
        $this->assertEquals('test-value', $result);
    }
```

**Step 7: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php --filter="test_try_create_from_value"`

Expected: FAIL with "Call to undefined method TestClassWithTypeConversion::testTryCreateFromValue()"

**Step 8: Implement tryCreateFromValue() method**

In `src/Traits/HasTypeConversion.php`, add this private static method (before `looksLikeIdClass()`):

```php
    /**
     * Try to create an instance from a value using from() or fromString() factory methods.
     *
     * @param mixed $value Value to convert
     * @param class-string $className Target class name
     * @return mixed Created instance or original value if conversion failed
     */
    private static function tryCreateFromValue(mixed $value, string $className): mixed
    {
        // Already correct type
        if ($value instanceof $className) {
            return $value;
        }

        // Try from() first
        if (method_exists($className, 'from')) {
            try {
                return $className::from($value);
            } catch (Throwable) {
                // Fall through to fromString
            }
        }

        // Try fromString() as fallback
        if (method_exists($className, 'fromString')) {
            try {
                return $className::fromString($value);
            } catch (Throwable) {
                // Both failed, return original
            }
        }

        // Couldn't convert, return original value unchanged
        return $value;
    }
```

**Step 9: Add missing import**

In `src/Traits/HasTypeConversion.php`, add `Throwable` to the use statements at the top (around line 16):

```php
use Throwable;
```

**Step 10: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php --filter="test_try_create_from_value"`

Expected: All 6 tests PASS

**Step 11: Commit tryCreateFromValue() implementation**

```bash
git add src/Traits/HasTypeConversion.php tests/Unit/Traits/HasTypeConversionTest.php
git commit -m "feat: add tryCreateFromValue() for UUID/ULID instantiation"
```

---

## Task 5: Write Tests for convertToUuidLike()

**Files:**
- Modify: `tests/Unit/Traits/HasTypeConversionTest.php`

**Step 1: Write failing test for convertToUuidLike() with custom UUID**

Add this test:

```php
    public function test_convert_to_uuid_like_custom_uuid(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'custom-uuid-123',
            \Tests\Fixtures\VOs\CustomUuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\CustomUuid::class, $result);
        $this->assertEquals('custom-uuid-123', $result->value);
    }
```

**Step 2: Write failing test for convertToUuidLike() with Rcuid**

Add this test:

```php
    public function test_convert_to_uuid_like_rcuid(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'rcuid-456',
            \Tests\Fixtures\VOs\Rcuid::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\Rcuid::class, $result);
        $this->assertEquals('rcuid-456', $result->value);
    }
```

**Step 3: Write failing test for convertToUuidLike() with UserId**

Add this test:

```php
    public function test_convert_to_uuid_like_user_id(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'user-789',
            \Tests\Fixtures\VOs\UserId::class
        );

        $this->assertInstanceOf(\Tests\Fixtures\VOs\UserId::class, $result);
        $this->assertEquals('user-789', $result->value);
    }
```

**Step 4: Write failing test for convertToUuidLike() with non-ID class**

Add this test:

```php
    public function test_convert_to_uuid_like_non_id_class(): void
    {
        $testClass = new TestClassWithTypeConversion();

        // Customer doesn't match naming heuristic
        $result = $testClass->testConvertToUuidLike(
            'customer-data',
            \Tests\Fixtures\VOs\TestGraniteObject::class
        );

        // Should return original value unchanged
        $this->assertEquals('customer-data', $result);
    }
```

**Step 5: Write failing test for convertToUuidLike() handling exceptions**

Add this test:

```php
    public function test_convert_to_uuid_like_handles_conversion_failure(): void
    {
        $testClass = new TestClassWithTypeConversion();

        $result = $testClass->testConvertToUuidLike(
            'will-fail',
            \Tests\Fixtures\VOs\InvalidId::class
        );

        // Should return original value when conversion fails
        $this->assertEquals('will-fail', $result);
    }
```

**Step 6: Write failing test for convertToUuidLike() with Ramsey UUID (optional)**

Add this test:

```php
    public function test_convert_to_uuid_like_ramsey_uuid(): void
    {
        if (!interface_exists('Ramsey\Uuid\UuidInterface')) {
            $this->markTestSkipped('ramsey/uuid not installed');
        }

        $testClass = new TestClassWithTypeConversion();

        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $testClass->testConvertToUuidLike(
            $uuidString,
            \Ramsey\Uuid\Uuid::class
        );

        $this->assertInstanceOf(\Ramsey\Uuid\UuidInterface::class, $result);
        $this->assertEquals($uuidString, $result->toString());
    }
```

**Step 7: Write failing test for convertToUuidLike() with Symfony Uuid (optional)**

Add this test:

```php
    public function test_convert_to_uuid_like_symfony_uuid(): void
    {
        if (!class_exists('Symfony\Component\Uid\Uuid')) {
            $this->markTestSkipped('symfony/uid not installed');
        }

        $testClass = new TestClassWithTypeConversion();

        $uuidString = '550e8400-e29b-41d4-a716-446655440000';
        $result = $testClass->testConvertToUuidLike(
            $uuidString,
            \Symfony\Component\Uid\Uuid::class
        );

        $this->assertInstanceOf(\Symfony\Component\Uid\AbstractUid::class, $result);
        $this->assertEquals($uuidString, (string) $result);
    }
```

**Step 8: Write failing test for convertToUuidLike() with Symfony Ulid (optional)**

Add this test:

```php
    public function test_convert_to_uuid_like_symfony_ulid(): void
    {
        if (!class_exists('Symfony\Component\Uid\Ulid')) {
            $this->markTestSkipped('symfony/uid not installed');
        }

        $testClass = new TestClassWithTypeConversion();

        $ulidString = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $result = $testClass->testConvertToUuidLike(
            $ulidString,
            \Symfony\Component\Uid\Ulid::class
        );

        $this->assertInstanceOf(\Symfony\Component\Uid\AbstractUid::class, $result);
    }
```

**Step 9: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php --filter="test_convert_to_uuid_like"`

Expected: FAIL with "Call to undefined method TestClassWithTypeConversion::testConvertToUuidLike()"

**Step 10: Implement convertToUuidLike() method**

In `src/Traits/HasTypeConversion.php`, add this private static method (before `tryCreateFromValue()`):

```php
    /**
     * Convert value to UUID/ULID instance using hybrid detection.
     *
     * @param mixed $value Value to convert
     * @param string $typeName Target type name
     * @return mixed Converted UUID/ULID instance or original value
     */
    private static function convertToUuidLike(mixed $value, string $typeName): mixed
    {
        // Step 1: Check known libraries
        if (interface_exists('Ramsey\Uuid\UuidInterface') &&
            is_subclass_of($typeName, 'Ramsey\Uuid\UuidInterface')) {
            return self::tryCreateFromValue($value, $typeName);
        }

        if (class_exists('Symfony\Component\Uid\AbstractUid') &&
            is_subclass_of($typeName, 'Symfony\Component\Uid\AbstractUid')) {
            return self::tryCreateFromValue($value, $typeName);
        }

        // Step 2: Duck-typing for custom ID classes
        if (self::looksLikeIdClass($typeName)) {
            return self::tryCreateFromValue($value, $typeName);
        }

        // Not a UUID-like type, return original value
        return $value;
    }
```

**Step 11: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php --filter="test_convert_to_uuid_like"`

Expected: All tests PASS (known library tests may be skipped if libraries not installed)

**Step 12: Commit convertToUuidLike() implementation**

```bash
git add src/Traits/HasTypeConversion.php tests/Unit/Traits/HasTypeConversionTest.php
git commit -m "feat: add convertToUuidLike() for UUID/ULID conversion"
```

---

## Task 6: Integrate UUID/ULID Conversion into convertToNamedType()

**Files:**
- Modify: `src/Traits/HasTypeConversion.php:96-154`

**Step 1: Write failing integration test**

Add this test to `tests/Unit/Traits/HasTypeConversionTest.php`:

```php
    public function test_convert_to_named_type_custom_uuid(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'customUuid');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $testClass = new TestClassWithTypeConversion();
            $result = $testClass->testConvertToNamedType('uuid-integration', $type);

            $this->assertInstanceOf(\Tests\Fixtures\VOs\CustomUuid::class, $result);
            $this->assertEquals('uuid-integration', $result->value);
        }
    }
```

**Step 2: Add integration test for Rcuid**

Add this test:

```php
    public function test_convert_to_named_type_rcuid(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'rcuid');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $testClass = new TestClassWithTypeConversion();
            $result = $testClass->testConvertToNamedType('rcuid-integration', $type);

            $this->assertInstanceOf(\Tests\Fixtures\VOs\Rcuid::class, $result);
            $this->assertEquals('rcuid-integration', $result->value);
        }
    }
```

**Step 3: Add integration test for UserId**

Add this test:

```php
    public function test_convert_to_named_type_user_id(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'userId');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $testClass = new TestClassWithTypeConversion();
            $result = $testClass->testConvertToNamedType('user-integration', $type);

            $this->assertInstanceOf(\Tests\Fixtures\VOs\UserId::class, $result);
            $this->assertEquals('user-integration', $result->value);
        }
    }
```

**Step 4: Add integration test for invalid ID handling**

Add this test:

```php
    public function test_convert_to_named_type_invalid_id_returns_original(): void
    {
        $property = new ReflectionProperty(TestTypeConversionClass::class, 'invalidId');
        $type = $property->getType();

        if ($type instanceof ReflectionNamedType) {
            $testClass = new TestClassWithTypeConversion();
            $result = $testClass->testConvertToNamedType('invalid-value', $type);

            // Should return original value when conversion fails
            $this->assertEquals('invalid-value', $result);
        }
    }
```

**Step 5: Run integration tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php --filter="test_convert_to_named_type_.*uid|test_convert_to_named_type_invalid_id"`

Expected: FAIL - tests return original string value instead of UUID/ULID objects

**Step 6: Integrate UUID/ULID check into convertToNamedType()**

In `src/Traits/HasTypeConversion.php`, modify the `convertToNamedType()` method. After the Carbon check (around line 107), add the UUID/ULID check:

```php
    private static function convertToNamedType(
        mixed $value,
        ReflectionNamedType $type,
        ?ReflectionProperty $property = null,
        ?DateTimeProvider $classProvider = null,
    ): mixed {
        $typeName = $type->getName();

        // Check for Carbon classes first (before general DateTime check)
        if (CarbonSupport::isCarbonClass($typeName)) {
            return self::convertToCarbon($value, $typeName, $property, $classProvider);
        }

        // Check for UUID/ULID classes
        if (!$type->isBuiltin()) {
            $uuidResult = self::convertToUuidLike($value, $typeName);
            if ($uuidResult !== $value) {
                return $uuidResult;
            }
        }

        // Check for GraniteObject first
        if (is_subclass_of($typeName, GraniteObject::class)) {
            if (null === $value) {
                return null;
            }

            return $typeName::from($value);
        }

        // ... rest of method unchanged
    }
```

**Step 7: Run integration tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php --filter="test_convert_to_named_type_.*uid|test_convert_to_named_type_invalid_id"`

Expected: All integration tests PASS

**Step 8: Run full HasTypeConversionTest suite**

Run: `./vendor/bin/phpunit tests/Unit/Traits/HasTypeConversionTest.php`

Expected: All tests PASS (no regressions)

**Step 9: Commit integration**

```bash
git add src/Traits/HasTypeConversion.php tests/Unit/Traits/HasTypeConversionTest.php
git commit -m "feat: integrate UUID/ULID conversion into type conversion flow"
```

---

## Task 7: End-to-End Integration Test

**Files:**
- Create: `tests/Integration/UuidHydrationTest.php`

**Step 1: Write failing end-to-end test**

Create file `tests/Integration/UuidHydrationTest.php`:

```php
<?php

namespace Tests\Integration;

use Ninja\Granite\GraniteVO;
use Tests\Fixtures\VOs\CustomUuid;
use Tests\Fixtures\VOs\Rcuid;
use Tests\Fixtures\VOs\UserId;
use Tests\Helpers\TestCase;

class UuidHydrationTest extends TestCase
{
    public function test_hydrate_object_with_uuid_properties(): void
    {
        $data = [
            'orderId' => 'order-123',
            'customerId' => 'customer-456',
            'trackingId' => 'track-789',
        ];

        $order = OrderWithIds::from($data);

        $this->assertInstanceOf(CustomUuid::class, $order->orderId);
        $this->assertEquals('order-123', $order->orderId->value);

        $this->assertInstanceOf(UserId::class, $order->customerId);
        $this->assertEquals('customer-456', $order->customerId->value);

        $this->assertInstanceOf(Rcuid::class, $order->trackingId);
        $this->assertEquals('track-789', $order->trackingId->value);
    }

    public function test_hydrate_object_with_uuid_from_json(): void
    {
        $json = '{"productId":"prod-111","supplierId":"supp-222"}';

        $product = ProductWithIds::from($json);

        $this->assertInstanceOf(CustomUuid::class, $product->productId);
        $this->assertEquals('prod-111', $product->productId->value);

        $this->assertInstanceOf(UserId::class, $product->supplierId);
        $this->assertEquals('supp-222', $product->supplierId->value);
    }

    public function test_hydrate_object_with_mixed_types(): void
    {
        $data = [
            'id' => 'mixed-123',
            'name' => 'Test Product',
            'quantity' => 42,
        ];

        $item = ItemWithMixedTypes::from($data);

        $this->assertInstanceOf(UserId::class, $item->id);
        $this->assertEquals('mixed-123', $item->id->value);
        $this->assertEquals('Test Product', $item->name);
        $this->assertEquals(42, $item->quantity);
    }
}

readonly class OrderWithIds extends GraniteVO
{
    public CustomUuid $orderId;
    public UserId $customerId;
    public Rcuid $trackingId;
}

readonly class ProductWithIds extends GraniteVO
{
    public CustomUuid $productId;
    public UserId $supplierId;
}

readonly class ItemWithMixedTypes extends GraniteVO
{
    public UserId $id;
    public string $name;
    public int $quantity;
}
```

**Step 2: Run end-to-end test to verify it fails**

Run: `./vendor/bin/phpunit tests/Integration/UuidHydrationTest.php`

Expected: Should PASS now (integration is already complete from Task 6)

**Step 3: Verify test passes**

Run: `./vendor/bin/phpunit tests/Integration/UuidHydrationTest.php`

Expected: All 3 integration tests PASS

**Step 4: Commit end-to-end tests**

```bash
git add tests/Integration/UuidHydrationTest.php
git commit -m "test: add end-to-end UUID/ULID hydration integration tests"
```

---

## Task 8: Run Full Test Suite and Verify

**Step 1: Run full test suite**

Run: `./vendor/bin/phpunit`

Expected: All tests PASS

**Step 2: Check for any PHPStan errors**

Run: `./vendor/bin/phpstan analyse`

Expected: No errors

**Step 3: Run code style fixer**

Run: `./vendor/bin/pint`

Expected: Code formatted correctly

**Step 4: Final commit for any style fixes**

```bash
git add -A
git commit -m "style: apply code formatting"
```

---

## Task 9: Update Documentation

**Files:**
- Modify: `docs/hydration.md` (add section about UUID/ULID support)

**Step 1: Add UUID/ULID documentation section**

In `docs/hydration.md`, find an appropriate section (likely near type conversion) and add:

```markdown
### UUID/ULID/Custom ID Conversion

Granite automatically converts string values to UUID/ULID objects when property types are ID classes:

#### Supported Libraries

- **ramsey/uuid**: Automatic support for `Ramsey\Uuid\UuidInterface`
- **symfony/uid**: Automatic support for `Symfony\Component\Uid\AbstractUid` (Uuid, Ulid)

#### Custom ID Classes

Custom ID classes are detected if:
1. Class name contains: `uuid`, `ulid`, `uid`, or `id` (case-insensitive)
2. Class has public static `from()` or `fromString()` method

**Example:**

```php
readonly class OrderId
{
    public function __construct(public string $value) {}

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        return new self((string) $value);
    }
}

readonly class Order extends GraniteVO
{
    public OrderId $id;
    public string $description;
}

// Automatic conversion
$order = Order::from(['id' => 'order-123', 'description' => 'Test']);
// $order->id is an OrderId instance, not a string
```

#### Factory Method Priority

1. Tries `from()` first (accepts any type)
2. Falls back to `fromString()` (string-specific)

#### Error Handling

If conversion fails, the original value is returned unchanged. Type errors will surface at PHP's type checking level.
```

**Step 2: Commit documentation update**

```bash
git add docs/hydration.md
git commit -m "docs: add UUID/ULID/Custom ID conversion documentation"
```

---

## Verification Checklist

- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] PHPStan analysis passes with no errors
- [ ] Code style (Pint) applied
- [ ] Documentation updated
- [ ] Known libraries (ramsey/uuid, symfony/uid) work when installed
- [ ] Custom ID classes work (CustomUuid, Rcuid, UserId)
- [ ] Error handling works (InvalidId returns original value)
- [ ] No performance regression on non-UUID types
- [ ] Feature committed to feature/uuid_hydration branch

## Final Notes

- All tests follow TDD: write test, make it fail, implement, make it pass
- Each task is small and focused (5-15 minutes)
- Commits are frequent and descriptive
- Error handling is lenient (returns original value on failure)
- Code follows existing patterns (Carbon/DateTime conversion)
