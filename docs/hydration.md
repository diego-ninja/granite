# Granite Hydration Patterns

Granite's `from()` method has been enhanced to support multiple invocation patterns **transparently**, without requiring method overrides in child classes. This makes object creation flexible, intuitive, and perfect for modern PHP development.

## Table of Contents

- [Overview](#overview)
- [Supported Patterns](#supported-patterns)
- [Array Data](#array-data)
- [JSON String](#json-string)
- [Named Parameters](#named-parameters)
- [Mixed Usage](#mixed-usage)
- [UUId/ULID/Custom ID Conversion](#uuidulidcustom-id-conversion)
- [Granite Object Cloning](#granite-object-cloning)
- [Object Hydration](#object-hydration)
- [Custom Hydrators](#custom-hydrators)
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

### 3. Named Parameters
```php
$product = Product::from(
    name: 'Laptop',
    price: 999.99,
    description: 'High-performance laptop',
    category: 'Electronics'
);
```

### 4. Mixed Usage
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

### 6. Any Object (Laravel, Doctrine, etc.)
```php
// From Laravel Eloquent Model
$eloquentUser = User::find(1);
$graniteUser = UserDTO::from($eloquentUser);

// From Doctrine Entity
$entity = $entityManager->find(Product::class, 1);
$productDTO = ProductDTO::from($entity);

// From stdClass
$stdObject = json_decode($json);
$graniteObject = SomeDTO::from($stdObject);
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
Tries from() first (accepts any type)
Falls back to fromString() (string-specific)

#### Error Handling
If conversion fails, the original value is returned unchanged. Type errors will surface at PHP's type checking level.

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

## Object Hydration

Granite can now extract data from **any object**, not just Granite objects. This makes it incredibly easy to integrate with frameworks, libraries, and external APIs.

### Supported Object Types

Granite automatically extracts data using multiple strategies (in priority order):

1. **`toArray()` method** - Common in Laravel models, Doctrine entities, etc.
2. **`JsonSerializable` interface** - Calls `jsonSerialize()`
3. **Public properties** - Direct extraction using `get_object_vars()`

```php
<?php

use Ninja\Granite\Granite;

final readonly class User extends Granite
{
    public function __construct(
        public string $name,
        public string $email,
        public int $age
    ) {}
}
```

### From Laravel/Eloquent Models

```php
// Laravel Eloquent Model
$eloquentUser = \App\Models\User::find(1);
// Has toArray() method

// Automatically extracts data
$graniteUser = User::from($eloquentUser);

echo $graniteUser->name;  // Works!
echo $graniteUser->email; // Works!
```

### From Doctrine Entities

```php
// Doctrine Entity with toArray() method
class DoctrineUser
{
    private string $name;
    private string $email;
    private int $age;

    // ... getters and setters ...

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'age' => $this->age
        ];
    }
}

$doctrineUser = $entityManager->find(DoctrineUser::class, 1);
$graniteUser = User::from($doctrineUser);
```

### From Framework Request Objects

```php
// Symfony Request
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();
// Request implements ArrayAccess and has public properties

$user = User::from($request); // Extracts request parameters

// Laravel Request
use Illuminate\Http\Request;

class UserController
{
    public function store(Request $request)
    {
        // If Request had toArray() or public properties, it would work directly
        // But typically you'd extract the data first:
        $validated = $request->validate([...]);
        $user = User::from($validated);

        return $user->json();
    }
}
```

### From API Response Objects

```php
// Guzzle HTTP Response
$response = $client->get('https://api.example.com/users/1');
$data = json_decode($response->getBody(), true);

$user = User::from($data);

// Or with an API client that returns objects
class ApiUser implements JsonSerializable
{
    public function __construct(
        private array $data
    ) {}

    public function jsonSerialize(): array
    {
        return $this->data;
    }
}

$apiUser = $apiClient->getUser(1);
$graniteUser = User::from($apiUser); // Automatically extracts via jsonSerialize()
```

### From stdClass Objects

```php
// From JSON decode
$json = '{"name": "John Doe", "email": "john@example.com", "age": 30}';
$stdObject = json_decode($json);

// Extracts public properties
$user = User::from($stdObject);

echo $user->name; // "John Doe"
```

### From Custom Objects with Public Properties

```php
class LegacyUser
{
    public string $name;
    public string $email;
    public int $age;

    public function __construct()
    {
        $this->name = 'John Doe';
        $this->email = 'john@example.com';
        $this->age = 30;
    }
}

$legacyUser = new LegacyUser();
$modernUser = User::from($legacyUser); // Extracts public properties
```

### Mixed Object and Named Parameters

```php
// Combine object extraction with named parameter overrides
$eloquentUser = \App\Models\User::find(1);

$updatedUser = User::from(
    $eloquentUser,
    email: 'newemail@example.com',  // Override email
    age: 31                          // Override age
);
```

### Object Priority and Fallbacks

```php
class HybridObject implements JsonSerializable
{
    public string $publicProp = 'from public';

    public function toArray(): array
    {
        return ['source' => 'from toArray()'];
    }

    public function jsonSerialize(): array
    {
        return ['source' => 'from jsonSerialize()'];
    }
}

$obj = new HybridObject();
$result = TestClass::from($obj);

// Priority: toArray() > jsonSerialize() > public properties
// Result will use toArray() method
```

### Real-World Example: Data Migration

```php
// Migrating from old system to new system
class OldSystemUser
{
    public function toArray(): array
    {
        return [
            'full_name' => $this->getFullName(),
            'email_address' => $this->getEmail(),
            'registration_date' => $this->getCreatedAt()
        ];
    }
}

final readonly class NewSystemUser extends Granite
{
    public function __construct(
        public string $full_name,
        public string $email_address,
        public DateTime $registration_date
    ) {}
}

// Migration script
$oldUsers = $oldSystem->getAllUsers();
foreach ($oldUsers as $oldUser) {
    $newUser = NewSystemUser::from($oldUser); // Automatic conversion!
    $newSystem->saveUser($newUser);
}
```

### Real-World Example: Testing with Mocks

```php
class MockUser
{
    public string $name = 'Test User';
    public string $email = 'test@example.com';
    public int $age = 25;
}

class UserServiceTest extends TestCase
{
    public function testUserCreation(): void
    {
        $mock = new MockUser();
        $user = User::from($mock);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
    }
}
```

### Benefits of Object Hydration

1. **Framework Agnostic** - Works with Laravel, Symfony, Doctrine, and custom code
2. **Zero Configuration** - No mapping needed, works automatically
3. **Type Safe** - Maintains Granite's type conversion and validation
4. **Flexible** - Combines with all other `from()` patterns
5. **Migration Friendly** - Easy to migrate from legacy systems
6. **Test Friendly** - Simple mock objects work out of the box

### Limitations

- Only **public properties** are extracted when using property extraction
- Private/protected properties require `toArray()` or `jsonSerialize()`
- Object must have compatible property names

```php
class UserWithPrivateProps
{
    private string $name = 'John';  // Won't be extracted
    public string $email = 'john@example.com';  // Will be extracted
}

$source = new UserWithPrivateProps();
$user = User::from($source);

// Only email is extracted, name property remains uninitialized
```

## Custom Hydrators

Granite's hydration system is **fully extensible**. You can create custom hydrators to support any data source imaginable - databases, APIs, file formats, or even custom protocols.

### How Hydrators Work

Granite uses a **Chain of Responsibility** pattern with prioritized hydrators. Each hydrator:

1. Checks if it `supports()` the given data type
2. If yes, `hydrate()` extracts and returns normalized array data
3. Higher priority hydrators are tried first

### Built-in Hydrators

Granite includes these hydrators out of the box (in priority order):

| Priority | Hydrator | Handles |
|----------|----------|---------|
| 100 | `GraniteHydrator` | Granite objects → `array()` method |
| 90 | `JsonHydrator` | JSON strings → parsed array |
| 80 | `ArrayHydrator` | Arrays → pass through |
| 70 | `ObjectHydrator` | Objects → `toArray()`, `JsonSerializable`, public props |
| 60 | `GetterHydrator` | Objects → smart getter extraction |
| 10 | `StringHydrator` | Invalid strings → throws exception |

### Creating a Custom Hydrator

Implement the `Hydrator` interface:

```php
<?php

namespace App\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;

class XmlHydrator extends AbstractHydrator
{
    // Higher priority = tried first
    protected int $priority = 85;

    public function supports(mixed $data, string $targetClass): bool
    {
        // Check if this hydrator can handle the data
        return is_string($data) && str_starts_with(trim($data), '<?xml');
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        // Extract data and return as array
        $xml = simplexml_load_string($data);
        return json_decode(json_encode($xml), true);
    }
}
```

### Registering Custom Hydrators

Register your hydrator with the factory:

```php
<?php

use Ninja\Granite\Hydration\HydratorFactory;
use App\Hydrators\XmlHydrator;

// Register once at application bootstrap
HydratorFactory::getInstance()->register(new XmlHydrator());

// Now all Granite objects can hydrate from XML!
$xml = '<?xml version="1.0"?><user><name>John</name><email>john@example.com</email></user>';
$user = User::from($xml); // Automatically uses XmlHydrator
```

### Real-World Example: CSV Hydrator

```php
<?php

namespace App\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;

/**
 * Hydrates Granite objects from CSV strings.
 */
class CsvHydrator extends AbstractHydrator
{
    protected int $priority = 85;

    public function supports(mixed $data, string $targetClass): bool
    {
        // Support CSV strings (simplified detection)
        if (!is_string($data)) {
            return false;
        }

        // Check if it looks like CSV (has commas and no JSON/XML markers)
        return str_contains($data, ',') &&
               !str_starts_with(trim($data), '{') &&
               !str_starts_with(trim($data), '<?xml');
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        $lines = explode("\n", trim($data));

        if (count($lines) < 2) {
            return [];
        }

        // First line = headers
        $headers = str_getcsv($lines[0]);

        // Second line = values
        $values = str_getcsv($lines[1]);

        // Combine into associative array
        return array_combine($headers, $values) ?: [];
    }
}

// Register the hydrator
HydratorFactory::getInstance()->register(new CsvHydrator());

// Use it!
$csv = "name,email,age\nJohn Doe,john@example.com,30";
$user = User::from($csv); // Works!
```

### Real-World Example: Database Row Hydrator

```php
<?php

namespace App\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;
use PDOStatement;

/**
 * Hydrates from PDO statement results.
 */
class PdoHydrator extends AbstractHydrator
{
    protected int $priority = 75;

    public function supports(mixed $data, string $targetClass): bool
    {
        return $data instanceof PDOStatement;
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var PDOStatement $data */
        $row = $data->fetch(\PDO::FETCH_ASSOC);
        return $this->ensureArray($row);
    }
}

// Register the hydrator
HydratorFactory::getInstance()->register(new PdoHydrator());

// Use it!
$stmt = $pdo->query("SELECT * FROM users WHERE id = 1");
$user = User::from($stmt); // Hydrates directly from DB result!
```

### Real-World Example: API Response Hydrator

```php
<?php

namespace App\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;
use Psr\Http\Message\ResponseInterface;

/**
 * Hydrates from PSR-7 HTTP responses.
 */
class Psr7ResponseHydrator extends AbstractHydrator
{
    protected int $priority = 75;

    public function supports(mixed $data, string $targetClass): bool
    {
        return $data instanceof ResponseInterface;
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        /** @var ResponseInterface $data */
        $body = (string) $data->getBody();

        // Try to decode as JSON
        $decoded = json_decode($body, true);
        return $this->ensureArray($decoded);
    }
}

// Register the hydrator
HydratorFactory::getInstance()->register(new Psr7ResponseHydrator());

// Use it with Guzzle!
$response = $guzzle->get('https://api.example.com/users/1');
$user = User::from($response); // Direct hydration from HTTP response!
```

### Real-World Example: Encrypted Data Hydrator

```php
<?php

namespace App\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;
use App\Services\EncryptionService;

/**
 * Hydrates from encrypted JSON strings.
 */
class EncryptedJsonHydrator extends AbstractHydrator
{
    protected int $priority = 95; // Higher priority than regular JSON

    public function __construct(
        private EncryptionService $encryption
    ) {}

    public function supports(mixed $data, string $targetClass): bool
    {
        // Check for our encryption marker
        return is_string($data) && str_starts_with($data, 'ENC:');
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        // Remove marker and decrypt
        $encrypted = substr($data, 4);
        $decrypted = $this->encryption->decrypt($encrypted);

        // Parse as JSON
        $decoded = json_decode($decrypted, true);
        return $this->ensureArray($decoded);
    }
}

// Register with dependency
$encryptionService = new EncryptionService($key);
HydratorFactory::getInstance()->register(
    new EncryptedJsonHydrator($encryptionService)
);

// Use it!
$encrypted = 'ENC:' . $encryptionService->encrypt(json_encode(['name' => 'John']));
$user = User::from($encrypted); // Automatically decrypts and hydrates!
```

### Real-World Example: YAML Hydrator

```php
<?php

namespace App\Hydrators;

use Ninja\Granite\Hydration\AbstractHydrator;
use Symfony\Component\Yaml\Yaml;

/**
 * Hydrates from YAML strings.
 */
class YamlHydrator extends AbstractHydrator
{
    protected int $priority = 85;

    public function supports(mixed $data, string $targetClass): bool
    {
        if (!is_string($data)) {
            return false;
        }

        // Simple YAML detection (starts with --- or has : markers)
        $trimmed = trim($data);
        return str_starts_with($trimmed, '---') ||
               (str_contains($trimmed, ':') && str_contains($trimmed, "\n"));
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        $parsed = Yaml::parse($data);
        return $this->ensureArray($parsed);
    }
}

// Register the hydrator
HydratorFactory::getInstance()->register(new YamlHydrator());

// Use it!
$yaml = <<<YAML
name: John Doe
email: john@example.com
age: 30
YAML;

$user = User::from($yaml); // Works with YAML!
```

### Hydrator Priority System

Higher priority hydrators are checked first. This allows you to:

1. **Override built-in behavior** - Use priority > 100 to override Granite's defaults
2. **Add new formats** - Use priority 50-90 for new data formats
3. **Fallback handlers** - Use priority < 50 for catch-all logic

```php
class HighPriorityHydrator extends AbstractHydrator
{
    protected int $priority = 150; // Checked before everything else

    // ...
}
```

### Chain of Responsibility

For objects, Granite uses multiple hydrators in sequence:

1. `ObjectHydrator` extracts data via `toArray()`, `JsonSerializable`, or public props
2. `GetterHydrator` enriches the data by extracting via getters
3. Results are merged (public properties take precedence)

This allows objects with both public properties AND getters to have all data extracted automatically!

### Best Practices for Custom Hydrators

#### 1. Make `supports()` Fast

```php
// Good: Quick checks
public function supports(mixed $data, string $targetClass): bool
{
    return is_string($data) && str_starts_with($data, 'CSV:');
}

// Bad: Expensive parsing in supports()
public function supports(mixed $data, string $targetClass): bool
{
    try {
        $this->parse($data); // Don't do heavy work here!
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
```

#### 2. Use Appropriate Priority

```php
// Format-specific hydrators: 50-90
class CsvHydrator extends AbstractHydrator {
    protected int $priority = 85;
}

// Framework integration: 70-80
class EloquentHydrator extends AbstractHydrator {
    protected int $priority = 75;
}

// Catch-all/fallback: < 50
class GenericHydrator extends AbstractHydrator {
    protected int $priority = 20;
}
```

#### 3. Handle Errors Gracefully

```php
public function hydrate(mixed $data, string $targetClass): array
{
    try {
        return $this->parseData($data);
    } catch (\Exception $e) {
        // Log error but return empty array instead of throwing
        error_log("Hydration failed: " . $e->getMessage());
        return [];
    }
}
```

#### 4. Leverage `ensureArray()`

```php
public function hydrate(mixed $data, string $targetClass): array
{
    $result = $this->someOperation($data);

    // Safely ensure result is array
    return $this->ensureArray($result);
}
```

### Testing Custom Hydrators

```php
<?php

use PHPUnit\Framework\TestCase;
use App\Hydrators\XmlHydrator;
use App\DTOs\User;

class XmlHydratorTest extends TestCase
{
    private XmlHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new XmlHydrator();
    }

    public function test_supports_xml_strings(): void
    {
        $xml = '<?xml version="1.0"?><root></root>';
        $this->assertTrue($this->hydrator->supports($xml, User::class));
    }

    public function test_does_not_support_json(): void
    {
        $json = '{"name": "John"}';
        $this->assertFalse($this->hydrator->supports($json, User::class));
    }

    public function test_hydrates_xml_to_array(): void
    {
        $xml = '<?xml version="1.0"?><user><name>John</name></user>';
        $result = $this->hydrator->hydrate($xml, User::class);

        $this->assertIsArray($result);
        $this->assertEquals('John', $result['name']);
    }
}
```

### Integration Example: Bootstrap

Register all your custom hydrators at application bootstrap:

```php
<?php

// config/bootstrap.php

use Ninja\Granite\Hydration\HydratorFactory;
use App\Hydrators\{
    XmlHydrator,
    CsvHydrator,
    YamlHydrator,
    PdoHydrator,
    Psr7ResponseHydrator
};

$factory = HydratorFactory::getInstance();

// Register all custom hydrators
$factory->register(new XmlHydrator());
$factory->register(new CsvHydrator());
$factory->register(new YamlHydrator());
$factory->register(new PdoHydrator());
$factory->register(new Psr7ResponseHydrator(new EncryptionService()));

// Now all Granite objects support these formats automatically!
```

### Benefits of Custom Hydrators

1. **Separation of Concerns** - Hydration logic is isolated
2. **Reusable** - One hydrator works for all Granite objects
3. **Testable** - Easy to unit test in isolation
4. **Composable** - Multiple hydrators can work together
5. **Zero Coupling** - Granite classes don't need to know about custom formats

### Advanced: Context-Aware Hydration

You can make hydrators aware of the target class:

```php
class SmartHydrator extends AbstractHydrator
{
    protected int $priority = 75;

    public function supports(mixed $data, string $targetClass): bool
    {
        // Only support specific classes
        return $data instanceof SomeType &&
               is_subclass_of($targetClass, BaseDTO::class);
    }

    public function hydrate(mixed $data, string $targetClass): array
    {
        // Use reflection to adapt to target class structure
        $reflection = new \ReflectionClass($targetClass);
        $properties = $reflection->getProperties();

        // Custom mapping based on target class
        $result = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $result[$name] = $this->extractValue($data, $name);
        }

        return $result;
    }
}
```

Custom hydrators unlock infinite possibilities for data integration! 🚀

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