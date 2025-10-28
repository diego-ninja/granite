# Pebble - Lightweight Immutable Snapshots

Pebble is a lightweight, immutable data container designed for creating quick snapshots of mutable objects without the overhead of validation, custom serialization, or type definitions.

## When to Use Pebble

### Use Pebble when you need:
- Quick snapshots of Eloquent models or other mutable objects
- Lightweight DTOs for simple data transfer
- Immutable copies for caching or comparison
- Fast object creation without validation overhead
- Dynamic property access without explicit class definitions

### Use Granite when you need:
- Validation of input data
- Custom serialization with naming conventions
- Type-safe properties with strict typing
- Complex transformations and mappings
- Full-featured DTOs or Value Objects

## Quick Start

```php
use Ninja\Granite\Pebble;

// Create from an Eloquent model
$user = User::query()->first();
$userSnapshot = Pebble::from($user);

// Access properties
echo $userSnapshot->name;
echo $userSnapshot->email;

// Export to different formats
$array = $userSnapshot->array();
$json = $userSnapshot->json();
```

## Features

### Immutability

Pebble objects are completely immutable. Once created, their properties cannot be modified:

```php
$pebble = Peeble::from(['name' => 'John', 'age' => 30]);

// This will throw an InvalidArgumentException
$pebble->name = 'Jane';

// Instead, create a new instance with merged data
$updated = $pebble->merge(['name' => 'Jane']);
```

### Multiple Data Sources

Pebble can extract data from various sources:

```php
// From arrays
$pebble = Peeble::from(['name' => 'John', 'email' => 'john@example.com']);

// From JSON strings
$pebble = Peeble::from('{"name": "John", "email": "john@example.com"}');

// From objects with public properties
$stdClass = new stdClass();
$stdClass->name = 'John';
$stdClass->email = 'john@example.com';
$pebble = Peeble::from($stdClass);

// From Eloquent models (with toArray method)
$user = User::find(1);
$pebble = Peeble::from($user);

// From Granite objects
$graniteUser = UserDTO::from(['name' => 'John', 'email' => 'john@example.com']);
$pebble = Peeble::from($graniteUser);
```

### Smart Property Extraction

Pebble intelligently extracts properties from objects using multiple strategies:

#### Priority Order:
1. **Granite objects** - Uses `array()` method
2. **Objects with `toArray()` method** - Calls the method
3. **JsonSerializable objects** - Uses `jsonSerialize()`
4. **Public properties** - Directly extracts public properties
5. **Getter methods** - Extracts data from `getName()`, `isActive()`, `hasPermission()` methods

```php
class User
{
    public string $name = 'John';
    private string $password = 'secret';

    public function getEmail(): string
    {
        return 'john@example.com';
    }

    public function isActive(): bool
    {
        return true;
    }

    // Private getters are ignored
    private function getPassword(): string
    {
        return $this->password;
    }
}

$user = new User();
$pebble = Peeble::from($user);

echo $pebble->name;     // 'John'
echo $pebble->email;    // 'john@example.com'
echo $pebble->active;   // true
echo $pebble->password; // null (not extracted)
```

### Property Access Methods

Peeble implements **ArrayAccess** and **Countable** interfaces for maximum flexibility:

```php
$peeble = Peeble::from([
    'name' => 'John',
    'age' => 30,
    'email' => 'john@example.com',
]);

// Magic __get
echo $peeble->name; // 'John'

// ArrayAccess interface - use array syntax!
echo $peeble['name'];  // 'John'
echo $peeble['age'];   // 30

// Check existence (both syntaxes work)
if (isset($peeble->name)) { }
if (isset($peeble['name'])) { }

// Countable interface - native count() support
$count = count($peeble); // 3
$count = $peeble->count(); // 3 (also works)

// Get with default value
$city = $peeble->get('city', 'Unknown'); // 'Unknown'

// Check if property exists (even if null)
$peeble->has('name'); // true
$peeble->has('city'); // false

// Check if empty
$peeble->isEmpty(); // false
```

### Filtering and Transformation

```php
$user = Peeble::from([
    'id' => 1,
    'name' => 'John',
    'email' => 'john@example.com',
    'password' => 'hashed_password',
    'internal_id' => 'xyz',
]);

// Get only specific properties
$publicData = $user->only(['id', 'name', 'email']);
// Contains: id, name, email

// Exclude specific properties
$safeData = $user->except(['password', 'internal_id']);
// Contains: id, name, email

// Merge new data (creates new instance)
$enriched = $user->merge(['status' => 'active', 'verified' => true]);
// Contains all original properties plus status and verified

// Chain transformations
$apiResponse = $user
    ->except(['password'])
    ->merge(['created_at' => now()->toDateTimeString()]);
```

### Fast Comparison with Fingerprinting

Peeble uses **O(1) fingerprint comparison** for blazing-fast equality checks:

```php
$user1 = Peeble::from(['name' => 'John', 'age' => 30]);
$user2 = Peeble::from(['name' => 'John', 'age' => 30]);
$user3 = Peeble::from(['name' => 'Jane', 'age' => 25]);

// O(1) comparison using cached xxh3 hash
$user1->equals($user2); // true - instant comparison!
$user1->equals($user3); // false

// Compare with arrays (computes hash on-the-fly)
$user1->equals(['name' => 'John', 'age' => 30]); // true

// Get unique fingerprint for caching/deduplication
$hash1 = $user1->fingerprint(); // e.g., "a3f5d2c8b4e1..."
$hash2 = $user2->fingerprint(); // same as $hash1
$hash3 = $user3->fingerprint(); // different

// Use fingerprints for caching keys
Cache::put("data:{$user1->fingerprint()}", $data);

// Use fingerprints for deduplication
$seen = [];
foreach ($objects as $obj) {
    $fp = $obj->fingerprint();
    if (!isset($seen[$fp])) {
        $seen[$fp] = true;
        // Process unique object
    }
}
```

**Performance Note:** Fingerprints are computed once at construction using **xxh3** (the fastest non-cryptographic hash), making comparisons nearly instant regardless of object size.

### Serialization

```php
$peeble = Peeble::from(['name' => 'John', 'age' => 30]);

// To array
$array = $peeble->array();

// To JSON
$json = $peeble->json();

// JsonSerializable interface
$json = json_encode($peeble); // Works automatically

// String conversion
echo $peeble; // JSON representation
```

## Common Use Cases

### 1. Creating Immutable Snapshots

```php
// Create a snapshot before modification
$originalUser = User::find(1);
$snapshot = Peeble::from($originalUser);

// Modify the model
$originalUser->name = 'Jane';
$originalUser->save();

// Compare with snapshot
if (!$snapshot->equals($originalUser->toArray())) {
    // User was modified
    echo "Name changed from {$snapshot->name} to {$originalUser->name}";
}
```

### 2. API Response DTOs

```php
// Create a safe API response without sensitive data
$user = User::find(1);

$response = Peeble::from($user)
    ->except(['password', 'remember_token', 'two_factor_secret'])
    ->merge(['generated_at' => now()->toIso8601String()]);

return response()->json($response);
```

### 3. Caching

```php
// Cache an immutable snapshot
$user = User::find(1);
$userSnapshot = Peeble::from($user);

Cache::put("user:{$user->id}", $userSnapshot->array(), now()->addHour());

// Retrieve and recreate
$cached = Cache::get("user:{$user->id}");
$userSnapshot = Peeble::from($cached);
```

### 4. Creating Multiple Views

```php
$user = User::find(1);

// Public profile view
$publicProfile = Peeble::from($user)->only([
    'id',
    'name',
    'avatar',
]);

// Admin view
$adminView = Peeble::from($user); // All data

// API view with enrichment
$apiView = Peeble::from($user)
    ->except(['password'])
    ->merge([
        'links' => [
            'self' => route('users.show', $user),
            'posts' => route('users.posts', $user),
        ],
    ]);
```

### 5. Event Data Snapshots

```php
class UserUpdated
{
    public function __construct(
        public readonly Peeble $before,
        public readonly Peeble $after,
    ) {}
}

// In your service
$before = Peeble::from($user);

$user->update($data);

$after = Peeble::from($user->fresh());

event(new UserUpdated($before, $after));
```

## Performance Characteristics

Peeble is designed for maximum performance:

- **No validation overhead** - Data is accepted as-is
- **No type conversion** - Properties are stored as provided
- **Efficient property access** - Uses `get_object_vars()` for public property extraction
- **Minimal memory overhead** - Simple array-based storage
- **Fast instantiation** - No complex initialization
- **O(1) comparisons** - Cached xxh3 fingerprints for instant equality checks
- **Eager fingerprinting** - Hash computed once at construction (readonly constraint)

### Performance Comparison

```php
use Ninja\Granite\Pebble;
use Ninja\Granite\Granite;

$data = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];

// Pebble - Fastest (no validation)
$start = microtime(true);
for ($i = 0; $i < 10000; $i++) {
    Pebble::from($data);
}
$peebleTime = microtime(true) - $start;

// Granite - Slower (with validation)
$start = microtime(true);
for ($i = 0; $i < 10000; $i++) {
    UserDTO::from($data); // Granite class with validation
}
$graniteTime = microtime(true) - $start;

// Pebble is typically 3-5x faster than Granite
echo "Pebble: {$peebleTime}s\n";
echo "Granite: {$graniteTime}s\n";
```

## Limitations

### 1. No Validation
Peeble does not validate input data. If you need validation, use Granite:

```php
// Pebble accepts anything
$peeble = Peeble::from(['email' => 'not-an-email']); // ✓ Works

// Granite validates
$dto = UserDTO::from(['email' => 'not-an-email']); // ✗ Throws exception
```

### 2. No Type Safety
Peeble properties are dynamic and not type-safe:

```php
$peeble = Peeble::from(['age' => '30']); // String
echo $peeble->age; // '30' (string, not int)
```

### 3. No Custom Serialization
Peeble cannot customize property names during serialization:

```php
// Pebble
$peeble = Peeble::from(['firstName' => 'John']);
$peeble->json(); // {"firstName": "John"}

// Granite with SerializedName
final readonly class User extends Granite
{
    public function __construct(
        #[SerializedName('first_name')]
        public string $firstName,
    ) {}
}
$user = User::from(['firstName' => 'John']);
$user->json(); // {"first_name": "John"}
```

### 4. No Nested Object Handling
Peeble doesn't automatically convert nested objects:

```php
$data = [
    'name' => 'John',
    'address' => ['street' => '123 Main St', 'city' => 'NY'],
];

$peeble = Peeble::from($data);
$peeble->address; // array (not a Pebble)
```

## Best Practices

### 1. Use Peeble for Simple Snapshots

```php
// Good - Simple data snapshot
$snapshot = Peeble::from($user);

// Bad - Complex DTO with validation needs
$dto = Peeble::from($complexUserData); // Use Granite instead
```

### 2. Combine with Granite

```php
// Use Granite for input validation
$validatedUser = UserDTO::from($request->all()); // Validates

// Use Pebble for internal snapshots
$snapshot = Peeble::from($validatedUser); // Fast, immutable
```

### 3. Filter Sensitive Data Early

```php
// Good - Filter once
$safeData = Peeble::from($user)->except(['password']);

// Bad - Repeatedly filtering
$data1 = Peeble::from($user);
$data2 = Peeble::from($data1->except(['password']));
```

### 4. Use for Event Data

```php
// Good - Immutable event data
event(new UserCreated(
    Peeble::from($user)
));

// Bad - Mutable model reference
event(new UserCreated($user)); // Model can be changed
```

## API Reference

### Static Methods

#### `Peeble::from(array|object|string $source): self`
Create a new Peeble from various data sources.

### Instance Methods

#### `__get(string $name): mixed`
Get a property value (returns null if not found).

#### `offsetGet(mixed $offset): mixed` (ArrayAccess)
Get a property value using array syntax `$pebble['key']`.

#### `offsetExists(mixed $offset): bool` (ArrayAccess)
Check if property exists using array syntax `isset($pebble['key'])`.

#### `has(string $name): bool`
Check if a property exists.

#### `get(string $name, mixed $default = null): mixed`
Get a property with a default value.

#### `array(): array`
Get all data as an array.

#### `json(): string`
Convert to JSON string.

#### `fingerprint(): string`
Get the unique xxh3 hash fingerprint of this Pebble's data (computed at construction).

#### `equals(mixed $other): bool`
Compare with another Pebble or array using O(1) fingerprint comparison.

#### `isEmpty(): bool`
Check if Pebble has no properties.

#### `count(): int` (Countable)
Get the number of properties. Works with native `count()` function.

#### `only(array $keys): self`
Create new Pebble with only specified properties.

#### `except(array $keys): self`
Create new Peeble without specified properties.

#### `merge(array $data): self`
Create new Pebble with merged data.

### Interfaces Implemented

- **JsonSerializable** - Can be used with `json_encode()`
- **ArrayAccess** - Supports array-style access: `$pebble['key']`
- **Countable** - Works with native `count()` function

## Conclusion

Peeble is perfect for scenarios where you need:
- Fast, lightweight immutable objects
- Quick snapshots for caching or comparison
- Simple data containers without validation overhead
- Developer-friendly dynamic property access

For more complex scenarios requiring validation, type safety, or custom serialization, use the full-featured [Granite](../README.md) class instead.
