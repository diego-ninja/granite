# Object Comparison

Granite provides powerful object comparison capabilities through the `HasComparation` trait. This feature allows you to compare objects for equality or detect specific differences between them.

## ⚠️ Note

All examples in this document use the `Granite` base class. As of version 2.0.0, the legacy `GraniteDTO` and `GraniteVO` classes are deprecated and will be removed in v3.0.0.

## Table of Contents

- [Overview](#overview)
- [Basic Usage](#basic-usage)
- [Equality Checking](#equality-checking)
- [Difference Detection](#difference-detection)
- [Comparison Behavior](#comparison-behavior)
- [Advanced Examples](#advanced-examples)
- [Performance Considerations](#performance-considerations)
- [API Reference](#api-reference)

## Overview

All Granite objects (both DTOs and Value Objects) automatically support comparison through two main methods:

- **`equals(Granite $other): bool`** - Checks if two objects are equal
- **`differs(Granite $other): array`** - Returns detailed differences between objects

These methods perform deep, recursive comparison including:
- Primitive values (strings, integers, floats, booleans)
- Arrays and nested arrays
- DateTime objects (with timezone awareness)
- Enums (both backed and unit enums)
- Nested Granite objects
- Custom objects with `__toString()` methods

## Basic Usage

### Equality Checking

```php
use Ninja\Granite\Granite;

final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email
    ) {}
}

$user1 = User::from(['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']);
$user2 = User::from(['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']);
$user3 = User::from(['id' => 2, 'name' => 'Jane Doe', 'email' => 'jane@example.com']);

// Check equality
var_dump($user1->equals($user2)); // true - all properties match
var_dump($user1->equals($user3)); // false - different values
```

### Difference Detection

```php
$user1 = User::from(['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']);
$user2 = User::from(['id' => 1, 'name' => 'Jane Doe', 'email' => 'jane@example.com']);

$differences = $user1->differs($user2);

/*
Array output:
[
    'name' => [
        'current' => 'John Doe',
        'new' => 'Jane Doe'
    ],
    'email' => [
        'current' => 'john@example.com',
        'new' => 'jane@example.com'
    ]
]
*/
```

## Equality Checking

The `equals()` method performs a deep comparison of all public properties.

### Type Safety

Objects must be of the same type to be considered equal:

```php
$user = User::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
$admin = Admin::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

$user->equals($admin); // false - different classes
```

### Null Handling

Null values are properly handled:

```php
final readonly class Profile extends Granite
{
    public function __construct(
        public string $name,
        public ?string $bio = null
    ) {}
}

$profile1 = Profile::from(['name' => 'John', 'bio' => null]);
$profile2 = Profile::from(['name' => 'John', 'bio' => null]);
$profile3 = Profile::from(['name' => 'John', 'bio' => 'Developer']);

$profile1->equals($profile2); // true - both bio are null
$profile1->equals($profile3); // false - one null, one not
```

### Array Comparison

Arrays are compared recursively:

```php
final readonly class Post extends Granite
{
    public function __construct(
        public string $title,
        public array $tags
    ) {}
}

$post1 = Post::from(['title' => 'My Post', 'tags' => ['php', 'granite']]);
$post2 = Post::from(['title' => 'My Post', 'tags' => ['php', 'granite']]);
$post3 = Post::from(['title' => 'My Post', 'tags' => ['php', 'laravel']]);

$post1->equals($post2); // true - arrays match
$post1->equals($post3); // false - different array values
```

### DateTime Comparison

DateTime objects are compared with timezone awareness:

```php
use DateTime;
use DateTimeZone;

final readonly class Event extends Granite
{
    public function __construct(
        public string $name,
        public DateTime $startDate
    ) {}
}

$utcDate = new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'));
$madridDate = new DateTime('2024-01-15 11:00:00', new DateTimeZone('Europe/Madrid'));

$event1 = Event::from(['name' => 'Conference', 'startDate' => $utcDate]);
$event2 = Event::from(['name' => 'Conference', 'startDate' => $utcDate]);
$event3 = Event::from(['name' => 'Conference', 'startDate' => $madridDate]);

$event1->equals($event2); // true - same timestamp and timezone
$event1->equals($event3); // false - different timezone
```

### Enum Comparison

Both backed and unit enums are supported:

```php
enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

final readonly class Account extends Granite
{
    public function __construct(
        public int $id,
        public Status $status
    ) {}
}

$account1 = Account::from(['id' => 1, 'status' => Status::ACTIVE]);
$account2 = Account::from(['id' => 1, 'status' => Status::ACTIVE]);
$account3 = Account::from(['id' => 1, 'status' => Status::INACTIVE]);

$account1->equals($account2); // true
$account1->equals($account3); // false
```

## Difference Detection

The `differs()` method returns an array containing only the properties that differ, along with their current and new values.

### Basic Differences

```php
$user1 = User::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
$user2 = User::from(['id' => 2, 'name' => 'John', 'email' => 'jane@example.com']);

$differences = $user1->differs($user2);
// [
//     'id' => ['current' => 1, 'new' => 2],
//     'email' => ['current' => 'john@example.com', 'new' => 'jane@example.com']
// ]
// Note: 'name' is not included because it's the same in both objects
```

### Nested Object Differences

When comparing nested Granite objects, differences are shown hierarchically:

```php
final readonly class Address extends Granite
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country
    ) {}
}

final readonly class Company extends Granite
{
    public function __construct(
        public string $name,
        public Address $address
    ) {}
}

$company1 = Company::from([
    'name' => 'Acme Inc',
    'address' => ['street' => '123 Main St', 'city' => 'New York', 'country' => 'USA']
]);

$company2 = Company::from([
    'name' => 'Acme Inc',
    'address' => ['street' => '456 Oak Ave', 'city' => 'Boston', 'country' => 'USA']
]);

$differences = $company1->differs($company2);
// [
//     'address' => [
//         'street' => ['current' => '123 Main St', 'new' => '456 Oak Ave'],
//         'city' => ['current' => 'New York', 'new' => 'Boston']
//     ]
// ]
// Note: nested structure shows only the changed address fields
```

### DateTime Differences

DateTime values are formatted with microseconds and timezone:

```php
$event1 = Event::from([
    'name' => 'Conference',
    'startDate' => new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'))
]);

$event2 = Event::from([
    'name' => 'Conference',
    'startDate' => new DateTime('2024-01-16 15:30:45', new DateTimeZone('Europe/Madrid'))
]);

$differences = $event1->differs($event2);
// [
//     'startDate' => [
//         'current' => '2024-01-15 10:00:00.000000 +00:00',
//         'new' => '2024-01-16 15:30:45.000000 +01:00'
//     ]
// ]
```

### Enum Differences

Enum values are shown as their scalar representation:

```php
$account1 = Account::from(['id' => 1, 'status' => Status::ACTIVE]);
$account2 = Account::from(['id' => 1, 'status' => Status::INACTIVE]);

$differences = $account1->differs($account2);
// [
//     'status' => [
//         'current' => 'active',
//         'new' => 'inactive'
//     ]
// ]
```

### Type Mismatch

Comparing objects of different types throws a `ComparisonException`:

```php
use Ninja\Granite\Exceptions\ComparisonException;

try {
    $user = User::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
    $admin = Admin::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);

    $user->differs($admin); // throws ComparisonException
} catch (ComparisonException $e) {
    echo $e->getMessage();
    // "Cannot compare objects of different types: expected User, got Admin"
}
```

## Comparison Behavior

### Property Scope

Only **public properties** are compared. This aligns with Granite's readonly object design where all properties are typically public.

### Uninitialized Properties

Uninitialized properties are skipped during comparison:

```php
final readonly class OptionalFields extends Granite
{
    public function __construct(
        public string $required,
        public ?string $optional = null
    ) {}
}

// Both objects leave 'optional' uninitialized
$obj1 = new OptionalFields(required: 'value');
$obj2 = new OptionalFields(required: 'value');

$obj1->equals($obj2); // true - uninitialized properties are ignored
```

### Comparison Algorithm

The comparison uses efficient algorithms:

1. **Arrays**: Recursive deep comparison without JSON encoding (efficient for large arrays)
2. **Objects**: Class check first, then property-by-property comparison
3. **DateTime**: Compares both timestamp and timezone
4. **Enums**: Compares the underlying value (for BackedEnum) or name (for UnitEnum)

## Advanced Examples

### Version Control Use Case

Track changes to domain objects:

```php
final readonly class Product extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public float $price,
        public array $tags,
        public DateTime $updatedAt
    ) {}
}

function auditChanges(Product $original, Product $updated): void
{
    $changes = $original->differs($updated);

    if (empty($changes)) {
        echo "No changes detected\n";
        return;
    }

    echo "Product #{$original->id} changes:\n";
    foreach ($changes as $field => $change) {
        echo "- {$field}: {$change['current']} → {$change['new']}\n";
    }
}

$original = Product::from([
    'id' => 1,
    'name' => 'Laptop',
    'price' => 999.99,
    'tags' => ['electronics', 'computers'],
    'updatedAt' => new DateTime('2024-01-15')
]);

$updated = Product::from([
    'id' => 1,
    'name' => 'Gaming Laptop',
    'price' => 1299.99,
    'tags' => ['electronics', 'gaming', 'computers'],
    'updatedAt' => new DateTime('2024-01-20')
]);

auditChanges($original, $updated);
// Output:
// Product #1 changes:
// - name: Laptop → Gaming Laptop
// - price: 999.99 → 1299.99
// - tags: Array → Array
// - updatedAt: 2024-01-15... → 2024-01-20...
```

### Caching Strategy

Determine if cached data needs refreshing:

```php
final readonly class CachedResponse extends Granite
{
    public function __construct(
        public array $data,
        public DateTime $cachedAt,
        public int $ttl
    ) {}
}

function shouldRefreshCache(CachedResponse $cached, CachedResponse $fresh): bool
{
    // If only cachedAt differs, data is still the same
    $differences = $cached->differs($fresh);

    unset($differences['cachedAt']);

    return !empty($differences);
}
```

### State Machine Validation

Ensure valid state transitions:

```php
enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
}

final readonly class Order extends Granite
{
    public function __construct(
        public int $id,
        public OrderStatus $status,
        public array $items
    ) {}
}

function validateTransition(Order $old, Order $new): bool
{
    $differences = $old->differs($new);

    // Only status should change during transition
    if (count($differences) !== 1 || !isset($differences['status'])) {
        throw new InvalidArgumentException('Invalid state transition');
    }

    // Validate allowed transitions
    $allowed = [
        'pending' => ['confirmed'],
        'confirmed' => ['shipped'],
        'shipped' => ['delivered']
    ];

    $oldStatus = $differences['status']['current'];
    $newStatus = $differences['status']['new'];

    return in_array($newStatus, $allowed[$oldStatus] ?? []);
}
```

### Complex Nested Comparison

```php
final readonly class Permission extends Granite
{
    public function __construct(
        public string $resource,
        public array $actions
    ) {}
}

final readonly class Role extends Granite
{
    public function __construct(
        public string $name,
        public array $permissions // array of Permission objects
    ) {}
}

final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public Role $role
    ) {}
}

$user1 = User::from([
    'id' => 1,
    'name' => 'John',
    'role' => [
        'name' => 'Admin',
        'permissions' => [
            ['resource' => 'users', 'actions' => ['read', 'write']],
            ['resource' => 'posts', 'actions' => ['read', 'write', 'delete']]
        ]
    ]
]);

$user2 = User::from([
    'id' => 1,
    'name' => 'John',
    'role' => [
        'name' => 'Admin',
        'permissions' => [
            ['resource' => 'users', 'actions' => ['read', 'write', 'delete']],
            ['resource' => 'posts', 'actions' => ['read', 'write', 'delete']]
        ]
    ]
]);

$differences = $user1->differs($user2);
// Shows nested differences in permissions array
```

## Performance Considerations

### Efficient Array Comparison

Granite uses recursive comparison instead of JSON encoding:

```php
// ✅ GOOD: Efficient recursive comparison
$arr1 = array_fill(0, 10000, 'value');
$arr2 = array_fill(0, 10000, 'value');

$obj1 = DataObject::from(['data' => $arr1]);
$obj2 = DataObject::from(['data' => $arr2]);

$obj1->equals($obj2); // Fast, no JSON encoding overhead
```

### Reflection Caching

Granite caches reflection data for better performance:

```php
// First comparison: reflection metadata is cached
$user1->equals($user2);

// Subsequent comparisons: uses cached metadata (faster)
$user3->equals($user4);
$user5->equals($user6);
```

### Early Returns

Comparison stops at the first difference:

```php
$obj1 = LargeObject::from([/* many fields */]);
$obj2 = LargeObject::from([/* many fields */]);

// If the first property differs, no further properties are checked
$obj1->equals($obj2);
```

## API Reference

### `equals(Granite $other): bool`

Checks if two Granite objects are equal.

**Parameters:**
- `$other` - Another Granite object to compare against

**Returns:**
- `true` if all initialized public properties are equal
- `false` if any property differs or objects are of different types

**Throws:**
- `ReflectionException` - If reflection fails (rare)

**Example:**
```php
$result = $user1->equals($user2);
```

---

### `differs(Granite $other): array`

Returns detailed differences between two objects.

**Parameters:**
- `$other` - Another Granite object to compare against

**Returns:**
- Empty array if objects are equal
- Associative array with differences:
  ```php
  [
      'property_name' => [
          'current' => <current_value>,
          'new' => <new_value>
      ]
  ]
  ```

**Throws:**
- `ComparisonException` - If objects are of different types
- `ReflectionException` - If reflection fails
- `SerializationException` - If value cannot be serialized for comparison

**Example:**
```php
$differences = $user1->differs($user2);

foreach ($differences as $property => $change) {
    echo "{$property}: {$change['current']} → {$change['new']}\n";
}
```

---

## See Also

- [Serialization](serialization.md) - Convert objects to arrays/JSON
- [Validation](validation.md) - Validate object properties
- [Advanced Usage](advanced_usage.md) - Complex patterns and use cases
