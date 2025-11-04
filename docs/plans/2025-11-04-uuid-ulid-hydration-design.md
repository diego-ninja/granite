# UUID/ULID/Custom ID Hydration Support

**Date:** 2025-11-04
**Status:** Approved
**Component:** Type Conversion (`HasTypeConversion` trait)

## Overview

Add support for automatic UUID/ULID/Custom ID conversion during object hydration. When a property type is a UUID/ULID class, the system will automatically instantiate it using `from()` or `fromString()` static factory methods.

## Problem Statement

Currently, when hydrating data into Granite objects, UUID/ULID properties receive raw string values instead of properly instantiated UUID/ULID objects. This requires manual conversion in user code and breaks type safety.

**Example Current Behavior:**
```php
class Order {
    public OrderId $id;  // Property expects OrderId object
}

// Hydration receives: ['id' => 'abc-123']
// Result: Type error - string provided, OrderId expected
```

**Desired Behavior:**
```php
// Hydration receives: ['id' => 'abc-123']
// System calls: OrderId::from('abc-123') or OrderId::fromString('abc-123')
// Result: Property gets OrderId object
```

## Requirements

1. Support standard UUID/ULID libraries (Ramsey UUID, Symfony Uid)
2. Support custom ID classes with `from()` or `fromString()` factory methods
3. Try `from()` first, fall back to `fromString()`
4. Return original value unchanged if conversion fails (lenient approach)
5. Detect classes using hybrid approach: known libraries first, then duck-typing

## Architecture

### Integration Point

The conversion happens in `HasTypeConversion::convertToNamedType()` method, inserted after Carbon checks but before GraniteObject checks.

**Conversion Priority:**
1. Carbon classes
2. **UUID/ULID classes** ← New
3. GraniteObject classes
4. DateTime classes
5. Enums

### Detection Strategy (Hybrid)

#### Phase 1: Known Libraries
Check if type implements known UUID/ULID interfaces:
- `Ramsey\Uuid\UuidInterface` (ramsey/uuid library)
- `Symfony\Component\Uid\AbstractUid` (symfony/uid library)

Use existence checks to avoid errors when libraries aren't installed.

#### Phase 2: Duck-typing
For non-standard classes, use heuristic detection:
- Class name contains: `uuid`, `ulid`, `uid`, or `id` (case-insensitive)
- AND class has public static method `from()` or `fromString()`

**Examples that match:**
- `Rcuid` → matches "uid"
- `CustomUid` → matches "uid"
- `OrderUuid` → matches "uuid"
- `UserId` → matches "id"
- `Ulid` → matches "ulid"

### Conversion Logic

```php
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

private static function looksLikeIdClass(string $className): bool
{
    $baseName = class_basename($className);
    return (bool) preg_match('/uuid|ulid|uid|id/i', $baseName);
}

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

### Error Handling

**Philosophy:** Lenient conversion - return original value if conversion fails.

**Failure scenarios:**
- Invalid format (UUID string malformed) → original value returned
- Factory method throws exception → exception caught, original value returned
- No matching factory method found → original value returned

**Rationale:** Matches existing hydration philosophy. Type errors surface at PHP level if property has strict type hint.

## Implementation Details

### File Changes

**Primary Change:** `src/Traits/HasTypeConversion.php`

1. Add three new private static methods:
   - `convertToUuidLike()` - Main conversion orchestrator
   - `looksLikeIdClass()` - Duck-typing heuristic
   - `tryCreateFromValue()` - Factory method invocation with error handling

2. Modify `convertToNamedType()` method:
   - Insert UUID/ULID check after line 107 (after Carbon check)
   - Check if type is not builtin before attempting conversion
   - Only return converted value if it differs from original

### Code Location

Insert after line 107 in `convertToNamedType()`:

```php
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
    ...
```

## Testing Strategy

### Test Cases Required

1. **Known Libraries (conditional on library presence)**
   - Ramsey UUID from string
   - Symfony Uuid from string
   - Symfony Ulid from string

2. **Custom ID Classes**
   - Class with only `from()` method
   - Class with only `fromString()` method
   - Class with both methods (verify `from()` tried first)
   - Class named `Rcuid` with `fromString()` (specific request)

3. **Edge Cases**
   - Value already correct type (no conversion)
   - Invalid format throws exception (returns original value)
   - Both factory methods throw exceptions (returns original value)
   - Class name doesn't match heuristic (ignored by duck-typing)
   - Non-builtin type without factory methods (returns original)

4. **Integration Tests**
   - Hydrate object with UUID property from array
   - Hydrate object with custom ID property from JSON
   - Hydrate object with mixed types (UUID + regular properties)

## Success Criteria

- [ ] Ramsey UUID/Symfony Uid automatically converted when libraries installed
- [ ] Custom ID classes with `from()` or `fromString()` work correctly
- [ ] `Rcuid` class specifically works (user requirement)
- [ ] Invalid formats don't crash hydration (original value returned)
- [ ] No performance regression on non-UUID types
- [ ] All tests pass (unit + integration)

## Alternatives Considered

### Alternative 1: Inline Implementation
Add UUID/ULID logic directly in `convertToNamedType()`.

**Rejected:** Would make method too large and harder to test in isolation.

### Alternative 2: Extract TypeConverterService
Create dedicated service class for all type conversions.

**Rejected:** Major refactoring, breaks existing trait pattern, overkill for this feature.

### Alternative 3: Pure Duck-typing (no known library checks)
Only use method_exists checks, skip interface/class checks.

**Rejected:** Known libraries benefit from fast-path optimization without reflection overhead.

## Future Enhancements

- Add support for custom conversion attributes (e.g., `#[IdConverter('from')]`)
- Support additional factory method patterns (e.g., `fromInt()`, `parse()`)
- Add configuration for strict vs lenient conversion mode
- Cache reflection results for repeated conversions

## References

- Ramsey UUID: https://github.com/ramsey/uuid
- Symfony Uid: https://symfony.com/doc/current/components/uid.html
- Existing type conversion: `src/Traits/HasTypeConversion.php`
- Hydration system: `src/Hydration/`
