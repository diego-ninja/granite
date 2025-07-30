# Granite Hydration Patterns

Granite's `from()` method has been enhanced to support multiple invocation patterns **transparently**, without requiring method overrides in child classes. This makes object creation flexible, intuitive, and perfect for modern PHP development.

## Table of Contents

- [Overview](#overview)
- [Supported Patterns](#supported-patterns)
- [Array Data](#array-data)
- [JSON String](#json-string)
- [Named Parameters](#named-parameters)
- [Mixed Usage](#mixed-usage)
- [Granite Object Cloning](#granite-object-cloning)
- [Partial Data](#partial-data)
- [Type Safety](#type-safety)
- [Advanced Examples](#advanced-examples)
- [Best Practices](#best-practices)

## Overview

The enhanced `from()` method automatically detects the invocation pattern and handles object creation accordingly. All patterns work transparently without requiring any configuration or method overrides in your classes.

```php
<?php

use Ninja\Granite\Granite;

final readonly class Product extends Granite
{
    public function __construct(
        public string $name,
        public float $price,
        public ?string $description = null,
        public ?string $category = null
    ) {}
}

// All these patterns work automatically - no configuration needed!
```

## Supported Patterns

### 1. Array Data (Traditional)
```php
$product = Product::from([
    'name' => 'Laptop',
    'price' => 999.99,
    'description' => 'High-performance laptop',
    'category' => 'Electronics'
]);
```

### 2. JSON String
```php
$json = '{"name": "Laptop", "price": 999.99, "description": "High-performance laptop"}';
$product = Product::from($json);
```

### 3. Named Parameters ✨ NEW!
```php
$product = Product::from(
    name: 'Laptop',
    price: 999.99,
    description: 'High-performance laptop',
    category: 'Electronics'
);
```

### 4. Mixed Usage ✨ NEW!
```php
$defaults = ['name' => 'Generic Product', 'price' => 0.0];
$product = Product::from(
    $defaults,
    name: 'Laptop',        // Override name
    price: 999.99,         // Override price
    category: 'Electronics' // Add new property
);
```

### 5. Granite Object Cloning
```php
$originalProduct = Product::from(['name' => 'Laptop', 'price' => 999.99]);
$clonedProduct = Product::from($originalProduct);
```

## Array Data

The traditional approach using associative arrays:

```php
<?php

final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?DateTime $createdAt = null
    ) {}
}

// Simple array
$user = User::from([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'createdAt' => '2024-01-15T10:30:00Z'
]);

// Nested arrays for complex objects
final readonly class Order extends Granite
{
    public function __construct(
        public int $id,
        public User $customer,
        public array $items,
        public DateTime $createdAt
    ) {}
}

$order = Order::from([
    'id' => 123,
    'customer' => [
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ],
    'items' => [
        ['name' => 'Laptop', 'price' => 999.99],
        ['name' => 'Mouse', 'price' => 29.99]
    ],
    'createdAt' => '2024-01-15T10:30:00Z'
]);
```

## JSON String

Automatically detects and parses JSON strings:

```php
// Simple JSON
$userJson = '{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "createdAt": "2024-01-15T10:30:00Z"
}';

$user = User::from($userJson);

// Complex nested JSON
$orderJson = '{
    "id": 123,
    "customer": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com"
    },
    "items": [
        {"name": "Laptop", "price": 999.99},
        {"name": "Mouse", "price": 29.99}
    ],
    "createdAt": "2024-01-15T10:30:00Z"
}';

$order = Order::from($orderJson);

// Invalid JSON throws InvalidArgumentException
try {
    $invalid = User::from('{"invalid": json}');
} catch (InvalidArgumentException $e) {
    echo "Invalid JSON: " . $e->getMessage();
}
```

## Named Parameters

The most exciting new feature - use PHP 8+ named parameters for clean, self-documenting code:

```php
<?php

final readonly class Event extends Granite
{
    public function __construct(
        public string $title,
        public DateTime $startDate,
        public DateTime $endDate,
        public ?string $description = null,
        public ?string $location = null,
        public int $maxAttendees = 100
    ) {}
}

// Clean, readable object creation
$event = Event::from(
    title: 'Annual Conference',
    startDate: new DateTime('2024-12-25 09:00:00'),
    endDate: new DateTime('2024-12-25 17:00:00'),
    description: 'Our biggest event of the year',
    location: 'Convention Center',
    maxAttendees: 500
);

// Order doesn't matter with named parameters
$event2 = Event::from(
    maxAttendees: 250,
    title: 'Workshop',
    startDate: new DateTime('2024-11-15 10:00:00'),
    endDate: new DateTime('2024-11-15 16:00:00'),
    location: 'Training Room A'
    // description is optional and omitted
);

// Perfect for API endpoints
class EventController
{
    public function create(Request $request): Event
    {
        return Event::from(
            title: $request->get('title'),
            startDate: new DateTime($request->get('start_date')),
            endDate: new DateTime($request->get('end_date')),
            description: $request->get('description'),
            location: $request->get('location', 'TBD'),
            maxAttendees: $request->get('max_attendees', 100)
        );
    }
}
```

### Named Parameters Benefits

1. **Self-documenting** - Parameter names make the code clear
2. **Order-independent** - Arguments can be in any order
3. **IDE-friendly** - Full autocomplete and type hints
4. **Refactoring-safe** - Parameter names are explicit
5. **Optional parameters** - Skip optional parameters easily

## Mixed Usage

Combine base data with named parameter overrides - perfect for configuration management and API updates:

```php
<?php

final readonly class DatabaseConfig extends Granite
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public ?string $password = null,
        public array $options = [],
        public bool $ssl = false
    ) {}
}

// Base configuration
$defaults = [
    'host' => 'localhost',
    'port' => 5432,
    'database' => 'app_db',
    'username' => 'app_user',
    'options' => ['charset' => 'utf8'],
    'ssl' => false
];

// Environment-specific overrides
$prodConfig = DatabaseConfig::from(
    $defaults,
    host: 'prod-db.company.com',
    port: 5433,
    ssl: true,
    options: ['charset' => 'utf8', 'timeout' => 30]
);

$testConfig = DatabaseConfig::from(
    $defaults,
    database: 'test_db',
    host: 'test-db.internal'
);

// JSON + named overrides
$jsonConfig = '{"host": "api.example.com", "port": 5432, "database": "api_db"}';
$customConfig = DatabaseConfig::from(
    $jsonConfig,
    username: 'api_user',
    ssl: true,
    options: ['pool_size' => 10]
);

// Granite object + overrides
$baseConfig = DatabaseConfig::from($defaults);
$modifiedConfig = DatabaseConfig::from(
    $baseConfig,
    host: 'new-host.com',
    port: 3306
);
```

### Mixed Usage Patterns

```php
// 1. Array + Named Parameters
$product = Product::from(
    ['name' => 'Laptop', 'price' => 999.99],
    category: 'Electronics',
    description: 'Gaming laptop'
);

// 2. JSON + Named Parameters
$product = Product::from(
    '{"name": "Laptop", "price": 999.99}',
    category: 'Electronics',
    description: 'Gaming laptop'
);

// 3. Granite Object + Named Parameters
$baseProduct = Product::from(['name' => 'Laptop', 'price' => 999.99]);
$customProduct = Product::from(
    $baseProduct,
    description: 'High-end gaming laptop',
    category: 'Gaming'
);

// 4. Multiple data sources (arrays/JSON) + Named overrides
$inventory = ['price' => 899.99, 'stock' => 50];
$metadata = '{"brand": "TechCorp", "warranty": "2 years"}';
$product = Product::from(
    $inventory,
    $metadata,  // Multiple data sources are merged
    name: 'Gaming Laptop',
    category: 'Electronics'
);
```

## Granite Object Cloning

Create new instances from existing Granite objects:

```php
<?php

final readonly class CustomerProfile extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?Address $address = null,
        public DateTime $createdAt = new DateTime()
    ) {}
}

// Original customer
$customer = CustomerProfile::from([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'phone' => '+1-555-0123'
]);

// Clone to new instance
$clonedCustomer = CustomerProfile::from($customer);

// Clone with modifications using mixed pattern
$updatedCustomer = CustomerProfile::from(
    $customer,
    email: 'john.doe@newcompany.com',
    phone: '+1-555-9876'
);

// Useful for audit trails
final readonly class CustomerAudit extends Granite
{
    public function __construct(
        public CustomerProfile $original,
        public CustomerProfile $modified,
        public DateTime $modifiedAt,
        public string $modifiedBy
    ) {}
}

$audit = CustomerAudit::from([
    'original' => $customer,
    'modified' => $updatedCustomer,
    'modifiedAt' => new DateTime(),
    'modifiedBy' => 'admin'
]);
```

## Partial Data

Create objects with only some properties initialized - unspecified properties remain uninitialized (not null):

```php
<?php

final readonly class PartialUser extends Granite
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $phone = null,
        public ?DateTime $lastLogin = null
    ) {}
}

// Only provide some properties
$partialUser = PartialUser::from(
    name: 'John Doe',
    email: 'john@example.com'
    // phone and lastLogin are not provided
);

// Check property initialization
$reflection = new ReflectionClass($partialUser);
$phoneProperty = $reflection->getProperty('phone');
$lastLoginProperty = $reflection->getProperty('lastLogin');

echo $phoneProperty->isInitialized($partialUser) ? 'Phone is set' : 'Phone is uninitialized';
echo $lastLoginProperty->isInitialized($partialUser) ? 'Last login is set' : 'Last login is uninitialized';

// Serialization only includes initialized properties
$array = $partialUser->array();
// Result: ['name' => 'John Doe', 'email' => 'john@example.com']
// phone and lastLogin are not included because they're uninitialized
```

### Empty Object Creation

```php
// Create completely empty object
$emptyUser = PartialUser::from();

// All properties are uninitialized
$reflection = new ReflectionClass($emptyUser);
foreach ($reflection->getProperties() as $property) {
    $isInitialized = $property->isInitialized($emptyUser);
    echo "{$property->getName()}: " . ($isInitialized ? 'initialized' : 'uninitialized') . "\n";
}

// Useful for builder patterns
class UserBuilder
{
    private PartialUser $user;
    
    public function __construct()
    {
        $this->user = PartialUser::from(); // Start with empty object
    }
    
    public function withName(string $name): self
    {
        $this->user = PartialUser::from($this->user, name: $name);
        return $this;
    }
    
    public function withEmail(string $email): self
    {
        $this->user = PartialUser::from($this->user, email: $email);
        return $this;
    }
    
    public function build(): PartialUser
    {
        return $this->user;
    }
}

$user = (new UserBuilder())
    ->withName('John Doe')
    ->withEmail('john@example.com')
    ->build();
```

## Type Safety

The enhanced `from()` method maintains full type safety and PHPStan Level 10 compatibility:

```php
<?php

final readonly class TypeSafeProduct extends Granite
{
    public function __construct(
        public string $name,
        public float $price,
        public int $quantity,
        public bool $inStock,
        public ?DateTime $restockDate = null
    ) {}
}

// All these maintain type safety
$product1 = TypeSafeProduct::from([
    'name' => 'Laptop',           // string ✓
    'price' => 999.99,            // float ✓
    'quantity' => 10,             // int ✓
    'inStock' => true,            // bool ✓
    'restockDate' => '2024-12-01' // string converted to DateTime ✓
]);

$product2 = TypeSafeProduct::from(
    name: 'Laptop',               // string ✓
    price: 999.99,                // float ✓
    quantity: 10,                 // int ✓
    inStock: true,                // bool ✓
    restockDate: new DateTime('2024-12-01') // DateTime ✓
);

// Type conversion happens automatically
$product3 = TypeSafeProduct::from([
    'name' => 'Laptop',
    'price' => '999.99',          // string → float
    'quantity' => '10',           // string → int
    'inStock' => 'true',          // string → bool
    'restockDate' => '2024-12-01' // string → DateTime
]);

// PHPStan analysis works perfectly
/** @phpstan-assert TypeSafeProduct $product1 */
$product1->name;        // PHPStan knows this is string
$product1->price;       // PHPStan knows this is float
$product1->inStock;     // PHPStan knows this is bool
```

## Advanced Examples

### API Controller Integration

```php
<?php

class ProductController
{
    public function create(Request $request): JsonResponse
    {
        // Clean, readable object creation
        $product = Product::from(
            name: $request->string('name'),
            price: $request->float('price'),
            description: $request->string('description', ''),
            category: $request->string('category'),
            inStock: $request->boolean('in_stock', true)
        );
        
        $this->productService->save($product);
        
        return response()->json($product->array());
    }
    
    public function update(Request $request, int $id): JsonResponse
    {
        $existingProduct = $this->productService->find($id);
        
        // Update with named parameter overrides
        $updatedProduct = Product::from(
            $existingProduct,
            name: $request->string('name', $existingProduct->name),
            price: $request->float('price', $existingProduct->price),
            description: $request->string('description', $existingProduct->description)
        );
        
        $this->productService->save($updatedProduct);
        
        return response()->json($updatedProduct->array());
    }
}
```

### Configuration Management

```php
<?php

final readonly class AppConfig extends Granite
{
    public function __construct(
        public DatabaseConfig $database,
        public CacheConfig $cache,
        public array $apiKeys,
        public bool $debugMode = false,
        public string $environment = 'production',
        public ?string $version = null
    ) {}
}

class ConfigurationManager
{
    public function loadConfig(string $environment): AppConfig
    {
        // Base configuration
        $baseConfig = $this->loadBaseConfig();
        
        // Environment-specific overrides
        $envConfig = $this->loadEnvironmentConfig($environment);
        
        // Runtime overrides
        return AppConfig::from(
            $baseConfig,
            $envConfig,
            environment: $environment,
            debugMode: $environment === 'development',
            version: $this->getApplicationVersion()
        );
    }
    
    private function loadBaseConfig(): array
    {
        return [
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'app'
            ],
            'cache' => [
                'driver' => 'redis',
                'ttl' => 3600
            ],
            'apiKeys' => []
        ];
    }
}
```

### Data Transformation Pipeline

```php
<?php

final readonly class RawUserData extends Granite
{
    public function __construct(
        public string $fullName,
        public string $emailAddress,
        public string $phoneNumber,
        public string $birthDate,
        public array $preferences
    ) {}
}

final readonly class ProcessedUser extends Granite
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $phone,
        public DateTime $birthDate,
        public int $age,
        public UserPreferences $preferences
    ) {}
}

class UserDataProcessor
{
    public function process(array $rawData): ProcessedUser
    {
        // Start with raw data
        $raw = RawUserData::from($rawData);
        
        // Extract first and last name
        [$firstName, $lastName] = explode(' ', $raw->fullName, 2);
        
        // Transform to processed format
        return ProcessedUser::from(
            firstName: $firstName,
            lastName: $lastName ?? '',
            email: strtolower($raw->emailAddress),
            phone: $this->normalizePhone($raw->phoneNumber),
            birthDate: new DateTime($raw->birthDate),
            age: $this->calculateAge($raw->birthDate),
            preferences: UserPreferences::from($raw->preferences)
        );
    }
}
```

### Testing Utilities

```php
<?php

class ProductTestFactory
{
    public static function createDefault(): Product
    {
        return Product::from(
            name: 'Test Product',
            price: 99.99,
            description: 'A test product',
            category: 'Test Category',
            inStock: true
        );
    }
    
    public static function createWithOverrides(array $overrides): Product
    {
        $default = self::createDefault();
        
        return Product::from($default, ...$overrides);
    }
    
    public static function createFromTemplate(string $template, array $overrides = []): Product
    {
        $templates = [
            'laptop' => [
                'name' => 'Gaming Laptop',
                'price' => 1299.99,
                'category' => 'Electronics',
                'description' => 'High-performance gaming laptop'
            ],
            'book' => [
                'name' => 'Programming Guide',
                'price' => 49.99,
                'category' => 'Books',
                'description' => 'Comprehensive programming guide'
            ]
        ];
        
        return Product::from($templates[$template], ...$overrides);
    }
}

// Usage in tests
class ProductTest extends PHPUnit\Framework\TestCase
{
    public function testProductCreation(): void
    {
        $product = ProductTestFactory::createDefault();
        $this->assertEquals('Test Product', $product->name);
    }
    
    public function testProductWithOverrides(): void
    {
        $product = ProductTestFactory::createWithOverrides([
            'name' => 'Custom Product',
            'price' => 199.99
        ]);
        
        $this->assertEquals('Custom Product', $product->name);
        $this->assertEquals(199.99, $product->price);
    }
    
    public function testLaptopTemplate(): void
    {
        $laptop = ProductTestFactory::createFromTemplate('laptop', [
            'price' => 999.99
        ]);
        
        $this->assertEquals('Gaming Laptop', $laptop->name);
        $this->assertEquals(999.99, $laptop->price);
    }
}
```

## Best Practices

### 1. Use Named Parameters for Clarity

```php
// Good: Self-documenting and clear
$event = Event::from(
    title: 'Annual Conference',
    startDate: new DateTime('2024-12-25'),
    endDate: new DateTime('2024-12-26'),
    maxAttendees: 500
);

// Less clear: Hard to understand without looking at the constructor
$event = Event::from([
    'Annual Conference',
    new DateTime('2024-12-25'),
    new DateTime('2024-12-26'),
    500
]);
```

### 2. Prefer Named Parameters for API Controllers

```php
// Good: Parameter names match API fields
public function createUser(Request $request): User
{
    return User::from(
        name: $request->get('name'),
        email: $request->get('email'),
        birthDate: new DateTime($request->get('birth_date')),
        isActive: $request->boolean('is_active', true)
    );
}

// Less maintainable: Array mapping
public function createUser(Request $request): User
{
    return User::from([
        'name' => $request->get('name'),
        'email' => $request->get('email'),
        'birthDate' => new DateTime($request->get('birth_date')),
        'isActive' => $request->boolean('is_active', true)
    ]);
}
```

### 3. Use Mixed Patterns for Configuration

```php
// Good: Base config + environment overrides
$config = AppConfig::from(
    $baseConfig,
    environment: 'production',
    debugMode: false,
    cacheEnabled: true
);

// Less flexible: Manual array merging
$envConfig = array_merge($baseConfig, [
    'environment' => 'production',
    'debugMode' => false,
    'cacheEnabled' => true
]);
$config = AppConfig::from($envConfig);
```

### 4. Leverage Type Safety

```php
// Good: Let Granite handle type conversion
$product = Product::from(
    name: $request->get('name'),      // string
    price: $request->get('price'),    // string → float
    quantity: $request->get('qty'),   // string → int
    inStock: $request->get('stock')   // string → bool
);

// Unnecessary: Manual type conversion
$product = Product::from([
    'name' => (string) $request->get('name'),
    'price' => (float) $request->get('price'),
    'quantity' => (int) $request->get('qty'),
    'inStock' => (bool) $request->get('stock')
]);
```

### 5. Use Partial Objects for Builders

```php
// Good: Builder pattern with partial objects
class UserBuilder
{
    private User $user;
    
    public function __construct()
    {
        $this->user = User::from(); // Empty object
    }
    
    public function withName(string $name): self
    {
        $this->user = User::from($this->user, name: $name);
        return $this;
    }
    
    public function withEmail(string $email): self
    {
        $this->user = User::from($this->user, email: $email);
        return $this;
    }
    
    public function build(): User
    {
        return $this->user;
    }
}
```

### 6. Test All Patterns

```php
class ProductTest extends PHPUnit\Framework\TestCase
{
    public function testFromArray(): void
    {
        $product = Product::from(['name' => 'Test', 'price' => 99.99]);
        $this->assertEquals('Test', $product->name);
    }
    
    public function testFromJson(): void
    {
        $product = Product::from('{"name": "Test", "price": 99.99}');
        $this->assertEquals('Test', $product->name);
    }
    
    public function testFromNamedParameters(): void
    {
        $product = Product::from(name: 'Test', price: 99.99);
        $this->assertEquals('Test', $product->name);
    }
    
    public function testFromMixed(): void
    {
        $base = ['name' => 'Base'];
        $product = Product::from($base, price: 99.99);
        $this->assertEquals('Base', $product->name);
        $this->assertEquals(99.99, $product->price);
    }
}
```

The enhanced `from()` method makes Granite objects more flexible and developer-friendly while maintaining type safety and performance. Choose the pattern that best fits your use case and coding style!