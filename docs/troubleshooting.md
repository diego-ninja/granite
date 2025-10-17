# Troubleshooting Guide

This guide helps you diagnose and solve common issues when using Granite.

## Table of Contents

- [Validation Issues](#validation-issues)
- [Serialization Problems](#serialization-problems)
- [AutoMapper Issues](#automapper-issues)
- [Performance Problems](#performance-problems)
- [Type Conversion Errors](#type-conversion-errors)
- [Memory Issues](#memory-issues)
- [Debugging Techniques](#debugging-techniques)
- [Common Error Messages](#common-error-messages)

## Validation Issues

### Problem: ValidationException with Unclear Messages

**Symptoms**:
```php
// Exception: Validation failed for User
$user = User::from([
    'name' => 'John',
    'email' => 'invalid-email'
]);
```

**Diagnosis**:
```php
try {
    $user = User::from($data);
} catch (ValidationException $e) {
    // Get detailed error information
    echo "Errors:\n";
    foreach ($e->getErrors() as $field => $messages) {
        echo "- {$field}: " . implode(', ', $messages) . "\n";
    }
    
    // Get formatted message
    echo $e->getFormattedMessage();
}
```

**Solutions**:

1. **Check validation rules**:
```php
final readonly class User extends Granite
{
    public function __construct(
        #[Required('Name is required')] // Custom message
        #[Min(2, 'Name must be at least 2 characters')]
        public string $name,
        
        #[Required('Email is required')]
        #[Email('Please provide a valid email address')]
        public string $email
    ) {}
}
```

2. **Debug validation rules**:
```php
// Use RuleExtractor to see what rules are applied
use Ninja\Granite\Validation\RuleExtractor;

$rules = RuleExtractor::extractRules(User::class);
var_dump($rules); // See all validation rules for the class
```

### Problem: Validation Rules Not Working

**Symptoms**: No validation is happening even with attributes.

**Diagnosis**:
```php
// Check if you're using Granite instead of Granite
class User extends Granite // ❌ No validation
{
    // Validation attributes here won't work
}

class User extends Granite // ✅ Validation enabled
{
    // Validation attributes work here
}
```

**Solution**: Always use `Granite` for objects that need validation.

### Problem: Custom Validation Rules Not Triggering

**Symptoms**:
```php
#[Callback(fn($value) => strlen($value) > 10)]
public string $description;
// Not validating properly
```

**Diagnosis**:
```php
// Test the callback separately
$callback = fn($value) => strlen($value) > 10;
var_dump($callback('short')); // Should return false
var_dump($callback('this is a longer string')); // Should return true
```

**Solutions**:

1. **Check callback signature**:
```php
// Correct signature: (mixed $value, ?array $allData = null): bool
#[Callback(function($value, $allData = null) {
    return strlen($value) > 10;
})]
public string $description;
```

2. **Use proper rule class**:
```php
use Ninja\Granite\Validation\Rules\Callback;

protected static function rules(): array
{
    return [
        'description' => [
            new Callback(function($value) {
                return strlen($value) > 10;
            }, 'Description must be longer than 10 characters')
        ]
    ];
}
```

## Serialization Problems

### Problem: Properties Not Appearing in JSON

**Symptoms**:
```php
$user = new User(1, 'John', 'john@example.com', 'secret');
$json = $user->json();
// Missing properties in output
```

**Diagnosis**:
```php
// Check if properties are hidden
$array = $user->array();
var_dump($array); // See what's actually being serialized

// Check metadata
use Ninja\Granite\Serialization\MetadataCache;
$metadata = MetadataCache::getMetadata(User::class);
var_dump($metadata->debug()); // See hidden properties and name mappings
```

**Solutions**:

1. **Check for Hidden attribute**:
```php
class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        
        #[Hidden] // This won't appear in JSON
        public string $password
    ) {}
}
```

2. **Check property visibility**:
```php
class User extends Granite
{
    public function __construct(
        public int $id, // ✅ Public - will serialize
        private string $secret // ❌ Private - won't serialize
    ) {}
}
```

3. **Check initialization**:
```php
$user = new User(1, 'John', 'john@example.com');
// If constructor parameters are optional and not provided,
// they won't be initialized and won't serialize
```

### Problem: DateTime Serialization Issues

**Symptoms**: DateTime objects not serializing correctly.

**Diagnosis**:
```php
$user = User::from([
    'name' => 'John',
    'createdAt' => 'invalid-date-string'
]);
// Check if DateTime conversion is failing
```

**Solutions**:

1. **Use proper date formats**:
```php
// Good formats
$user = User::from([
    'name' => 'John',
    'createdAt' => '2023-01-15T10:30:00Z', // ISO 8601
    'createdAt' => '2023-01-15 10:30:00',  // MySQL format
    'createdAt' => 1642248600,             // Unix timestamp
]);
```

2. **Handle invalid dates gracefully**:
```php
final readonly class User extends Granite
{
    public function __construct(
        public string $name,
        public ?DateTime $createdAt = null // Allow null for invalid dates
    ) {}
    
    protected static function rules(): array
    {
        return [
            'createdAt' => [
                new Callback(function($value) {
                    if ($value === null) return true;
                    try {
                        new DateTime($value);
                        return true;
                    } catch (Exception $e) {
                        return false;
                    }
                }, 'Invalid date format')
            ]
        ];
    }
}
```

### Problem: Enum Serialization Issues

**Symptoms**: Enums not converting properly.

**Diagnosis**:
```php
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

// Test enum conversion
$user = User::from([
    'status' => 'active' // Should work
]);

$user = User::from([
    'status' => 'ACTIVE' // Might not work - case sensitive
]);
```

**Solutions**:

1. **Use backed enum values**:
```php
// For backed enums, use the value, not the name
$user = User::from([
    'status' => 'active' // Use 'active', not 'ACTIVE'
]);
```

2. **Use enum name for unit enums**:
```php
enum Priority {
    case LOW;
    case MEDIUM;
    case HIGH;
}

$task = Task::from([
    'priority' => 'LOW' // Use the case name
]);
```

3. **Add custom conversion**:
```php
use Ninja\Granite\Validation\Rules\Callback;

protected static function rules(): array
{
    return [
        'status' => [
            new Callback(function($value) {
                return Status::tryFrom(strtolower($value)) !== null;
            })
        ]
    ];
}
```

## AutoMapper Issues

### Problem: Properties Not Mapping

**Symptoms**: AutoMapper not copying properties between objects.

**Diagnosis**:
```php
$mapper = new AutoMapper();

// Enable debugging
$sourceData = $source->array();
var_dump('Source data:', $sourceData);

$result = $mapper->map($source, DestinationType::class);
$resultData = $result->array();
var_dump('Result data:', $resultData);

// Check mapping configuration
$mappings = $mapper->getMappingsForTypes(SourceType::class, DestinationType::class);
var_dump('Mappings:', $mappings);
```

**Solutions**:

1. **Check property names match**:
```php
class Source {
    public int $userId; // Different name
    public string $fullName;
}

class Destination {
    public int $id; // Different name
    public string $name;
}

// Need explicit mapping
$mapper->createMap(Source::class, Destination::class)
    ->forMember('id', fn($m) => $m->mapFrom('userId'))
    ->forMember('name', fn($m) => $m->mapFrom('fullName'))
    ->seal();
```

2. **Check conventions are enabled**:
```php
$mapper = new AutoMapper(useConventions: true);
$mapper->setConventionConfidenceThreshold(0.7); // Lower threshold
```

3. **Use MapFrom attributes**:
```php
use Ninja\Granite\Mapping\Attributes\MapFrom;

final readonly class Destination extends Granite
{
    public function __construct(
        #[MapFrom('userId')]
        public int $id,
        
        #[MapFrom('fullName')]
        public string $name
    ) {}
}
```

### Problem: Mapping Performance Issues

**Symptoms**: AutoMapper is slow with large datasets.

**Diagnosis**:
```php
$start = microtime(true);
$results = $mapper->mapArray($largeArray, DestinationType::class);
$end = microtime(true);
echo "Mapping took: " . ($end - $start) . " seconds\n";

// Check cache stats
if ($mapper->getCache() instanceof SharedMappingCache) {
    var_dump($mapper->getCache()->getStats());
}
```

**Solutions**:

1. **Use appropriate caching**:
```php
use Ninja\Granite\Enums\CacheType;

// For web applications
$mapper = new AutoMapper(cacheType: CacheType::Shared);

// For high-performance scenarios
$mapper = new AutoMapper(cacheType: CacheType::Persistent);
```

2. **Preload mappings**:
```php
use Ninja\Granite\Mapping\MappingPreloader;

$typePairs = [
    [UserEntity::class, UserResponse::class],
    [ProductEntity::class, ProductResponse::class]
];

MappingPreloader::preload($mapper, $typePairs);
```

3. **Use bulk operations**:
```php
// Instead of mapping one by one
foreach ($entities as $entity) {
    $responses[] = $mapper->map($entity, ResponseType::class); // Slow
}

// Use mapArray
$responses = $mapper->mapArray($entities, ResponseType::class); // Faster
```

### Problem: Circular Reference in Mapping

**Symptoms**: Stack overflow or infinite recursion during mapping.

**Diagnosis**:
```php
// Check for circular references in your objects
class User {
    public array $orders; // Contains Order objects
}

class Order {
    public User $user; // Contains User object - CIRCULAR!
}
```

**Solutions**:

1. **Use IDs instead of full objects**:
```php
final readonly class UserResponse extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public array $orderIds // Just IDs, not full Order objects
    ) {}
}
```

2. **Create specific mapping DTOs**:
```php
final readonly class OrderSummary extends Granite
{
    public function __construct(
        public int $id,
        public float $total,
        public DateTime $createdAt
        // No user reference
    ) {}
}

final readonly class UserWithOrders extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var OrderSummary[] */
        public array $orders
    ) {}
}
```

## Performance Problems

### Problem: High Memory Usage

**Symptoms**: Application using excessive memory with Granite objects.

**Diagnosis**:
```php
// Monitor memory usage
$memoryBefore = memory_get_usage(true);

$users = [];
for ($i = 0; $i < 10000; $i++) {
    $users[] = User::from([
        'id' => $i,
        'name' => "User {$i}",
        'email' => "user{$i}@example.com"
    ]);
}

$memoryAfter = memory_get_usage(true);
echo "Memory used: " . ($memoryAfter - $memoryBefore) . " bytes\n";
```

**Solutions**:

1. **Use generators for large datasets**:
```php
function processUsers(array $userData): Generator
{
    foreach ($userData as $data) {
        yield User::from($data);
    }
}

// Process one at a time instead of loading all
foreach (processUsers($largeDataset) as $user) {
    // Process user
    processUser($user);
}
```

2. **Clear reflection cache periodically**:
```php
// In long-running processes
if ($processedCount % 10000 === 0) {
    // Clear caches to free memory
    $mapper->clearCache();
    gc_collect_cycles();
}
```

3. **Use simpler DTOs when possible**:
```php
// Instead of complex nested objects
final readonly class ComplexUser extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public Address $address,
        public UserProfile $profile,
        public array $orders,
        public array $permissions
    ) {}
}

// Use simpler version
final readonly class SimpleUser extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email
    ) {}
}
```

### Problem: Slow Object Creation

**Symptoms**: Creating Granite objects is slow.

**Diagnosis**:
```php
$start = microtime(true);
for ($i = 0; $i < 1000; $i++) {
    $user = User::from([
        'id' => $i,
        'name' => "User {$i}",
        'email' => "user{$i}@example.com"
    ]);
}
$end = microtime(true);
echo "Creation time: " . ($end - $start) . " seconds\n";
```

**Solutions**:

1. **Minimize validation for DTOs**:
```php
// Use Granite for simple data transfer (no validation)
final readonly class UserResponse extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email
    ) {}
}

// Use Granite only when validation is needed
final readonly class CreateUserRequest extends Granite
{
    public function __construct(
        #[Required]
        #[Email]
        public string $email,
        
        #[Required]
        #[Min(2)]
        public string $name
    ) {}
}
```

2. **Cache reflection data**:
```php
// Reflection cache is enabled by default, but you can preload
use Ninja\Granite\Support\ReflectionCache;

// Preload reflection data for frequently used classes
ReflectionCache::getClass(User::class);
ReflectionCache::getPublicProperties(User::class);
```

## Type Conversion Errors

### Problem: Automatic Type Conversion Failing

**Symptoms**: Properties not converting to expected types.

**Diagnosis**:
```php
// Test conversion manually
$user = User::from([
    'id' => '123',        // String that should become int
    'isActive' => 'true', // String that should become bool
    'createdAt' => '2023-01-15T10:30:00Z' // String that should become DateTime
]);

var_dump($user->id);        // Should be int(123)
var_dump($user->isActive);  // Should be bool(true)
var_dump($user->createdAt); // Should be DateTime object
```

**Solutions**:

1. **Use explicit conversion**:
```php
final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public bool $isActive,
        public DateTime $createdAt
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            isActive: filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN),
            createdAt: new DateTime($data['createdAt'])
        );
    }
}
```

2. **Add validation for expected types**:
```php
final readonly class User extends Granite
{
    public function __construct(
        #[IntegerType]
        public int $id,
        
        #[BooleanType]
        public bool $isActive,
        
        public DateTime $createdAt
    ) {}
}
```

### Problem: Null Value Handling

**Symptoms**: Unexpected null values or null conversion errors.

**Diagnosis**:
```php
// Check if null values are being handled correctly
$user = User::from([
    'id' => 1,
    'name' => 'John',
    'email' => null // This might cause issues
]);
```

**Solutions**:

1. **Make properties nullable when appropriate**:
```php
final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email = null // Explicitly nullable
    ) {}
}
```

2. **Use validation to handle nulls**:
```php
final readonly class User extends Granite
{
    public function __construct(
        #[Required] // Will fail if null
        public string $email
    ) {}
}
```

## Memory Issues

### Problem: Memory Leaks with Long-Running Processes

**Symptoms**: Memory usage grows over time.

**Diagnosis**:
```php
// Monitor memory in long-running process
while (true) {
    // Process some data
    $user = User::from($userData);
    processUser($user);
    
    // Check memory periodically
    if ($counter % 1000 === 0) {
        echo "Memory: " . memory_get_usage(true) . "\n";
        echo "Peak: " . memory_get_peak_usage(true) . "\n";
    }
}
```

**Solutions**:

1. **Clear caches periodically**:
```php
use Ninja\Granite\Support\ReflectionCache;
use Ninja\Granite\Serialization\MetadataCache;

// In long-running processes
if ($processedCount % 10000 === 0) {
    // Clear static caches (this requires adding a clear method)
    gc_collect_cycles();
}
```

2. **Use unset for large objects**:
```php
foreach ($largeDataset as $data) {
    $user = User::from($data);
    processUser($user);
    unset($user); // Explicitly free memory
}
```

## Debugging Techniques

### Enable Debug Mode

```php
// Add debugging to your objects
final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email
    ) {}
    
    public function debug(): array
    {
        return [
            'class' => static::class,
            'data' => $this->array(),
            'validation_rules' => RuleExtractor::extractRules(static::class)
        ];
    }
}

// Usage
$user = User::from($data);
var_dump($user->debug());
```

### Log Validation Errors

```php
try {
    $user = User::from($userData);
} catch (ValidationException $e) {
    error_log('Validation failed for ' . User::class);
    error_log('Data: ' . json_encode($userData));
    error_log('Errors: ' . json_encode($e->getErrors()));
    throw $e;
}
```

### Test Mapping Step by Step

```php
// Debug ObjectMapper step by step
$mapper = new AutoMapper();

echo "Source data:\n";
var_dump($sourceData);

echo "Mapping config:\n";
$mappings = $mapper->getMappingsForTypes(SourceClass::class, DestClass::class);
var_dump($mappings);

echo "Result:\n";
$result = $mapper->map($source, DestClass::class);
var_dump($result->array());
```

## Common Error Messages

### "Class not found"
```
Error: Class "App\DTO\UserResponse" not found
```
**Solution**: Check class autoloading and namespace.

### "Property does not exist"
```
Error: Property "nonExistentProperty" does not exist in class User
```
**Solution**: Check property names in mapping configuration.

### "Validation failed"
```
ValidationException: Validation failed for User
```
**Solution**: Use `$e->getErrors()` to see specific validation failures.

### "Cannot serialize property"
```
SerializationException: Cannot serialize property "resource" of type "resource"
```
**Solution**: Add `#[Hidden]` attribute or use a transformer.

### "Mapping configuration is sealed"
```
MappingException: Cannot modify mapping after it has been sealed
```
**Solution**: Configure mappings before calling `seal()`.

### "Circular reference detected"
```
Error: Maximum function nesting level reached
```
**Solution**: Break circular references using IDs or separate DTOs.

By following this troubleshooting guide, you should be able to resolve most common issues when working with Granite. If you encounter problems not covered here, consider checking the library's GitHub issues or creating a minimal reproduction case for further investigation.