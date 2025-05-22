# Serialization

Granite provides powerful serialization capabilities that allow you to control how your DTOs and Value Objects are converted to and from arrays and JSON. You can customize property names, hide sensitive data, and handle complex data types seamlessly.

## Table of Contents

- [Basic Serialization](#basic-serialization)
- [Custom Property Names](#custom-property-names)
- [Hiding Properties](#hiding-properties)
- [Type Conversion](#type-conversion)
- [Working with Enums](#working-with-enums)
- [Date and Time Handling](#date-and-time-handling)
- [Nested Objects](#nested-objects)
- [Advanced Serialization](#advanced-serialization)

## Basic Serialization

All Granite objects can be easily converted to arrays and JSON:

```php
<?php

use Ninja\Granite\GraniteDTO;

final readonly class User extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $age = null
    ) {}
}

$user = User::from([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Convert to array
$array = $user->array();
// Result: ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30]

// Convert to JSON
$json = $user->json();
// Result: {"id":1,"name":"John Doe","email":"john@example.com","age":30}
```

## Custom Property Names

### Using Attributes

Control how properties are named in the serialized output using the `#[SerializedName]` attribute:

```php
<?php

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class ApiUser extends GraniteDTO
{
    public function __construct(
        public int $id,
        
        #[SerializedName('first_name')]
        public string $firstName,
        
        #[SerializedName('last_name')]
        public string $lastName,
        
        #[SerializedName('email_address')]
        public string $email,
        
        #[SerializedName('phone_number')]
        public ?string $phoneNumber = null
    ) {}
}

$user = ApiUser::from([
    'id' => 1,
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'john@example.com'
]);

$serialized = $user->array();
// Result: [
//     'id' => 1,
//     'first_name' => 'John',
//     'last_name' => 'Doe',
//     'email_address' => 'john@example.com'
// ]
```

### Using Methods

Alternatively, define custom names using the `serializedNames()` method:

```php
<?php

final readonly class User extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $emailAddress
    ) {}
    
    protected static function serializedNames(): array
    {
        return [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'emailAddress' => 'email'
        ];
    }
}
```

### Bidirectional Mapping

Custom names work both for serialization and deserialization:

```php
<?php

// Input data with custom names
$inputData = [
    'id' => 1,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email_address' => 'john@example.com'
];

// Create object (deserialization)
$user = ApiUser::from($inputData);

// Access properties using PHP names
echo $user->firstName; // 'John'
echo $user->lastName;  // 'Doe'

// Serialize back to custom names
$output = $user->array();
// Result uses custom names: first_name, last_name, email_address
```

## Hiding Properties

### Using Attributes

Hide sensitive properties from serialization using the `#[Hidden]` attribute:

```php
<?php

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class UserWithCredentials extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        
        #[Hidden]
        public string $password,
        
        #[Hidden]
        public string $apiToken,
        
        #[Hidden]
        public array $internalFlags = []
    ) {}
}

$user = UserWithCredentials::from([
    'id' => 1,
    'username' => 'johndoe',
    'email' => 'john@example.com',
    'password' => 'secret123',
    'apiToken' => 'abc123xyz'
]);

$publicData = $user->array();
// Result: ['id' => 1, 'username' => 'johndoe', 'email' => 'john@example.com']
// password, apiToken, and internalFlags are excluded
```

### Using Methods

Hide properties using the `hiddenProperties()` method:

```php
<?php

final readonly class UserEntity extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public string $password,
        public string $apiToken
    ) {}
    
    protected static function hiddenProperties(): array
    {
        return ['password', 'apiToken'];
    }
}
```

### Context-based Hiding

Create different versions for different contexts:

```php
<?php

// Public API response
final readonly class PublicUser extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email
    ) {}
}

// Internal system response
final readonly class InternalUser extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $internalId,
        public array $permissions,
        
        #[Hidden] // Still hide from serialization
        public string $passwordHash
    ) {}
}
```

## Type Conversion

Granite automatically handles type conversion during serialization and deserialization:

### Basic Types

```php
<?php

final readonly class TypeExample extends GraniteDTO
{
    public function __construct(
        public int $id,
        public float $price,
        public bool $isActive,
        public array $tags,
        public ?string $description = null
    ) {}
}

// Input with mixed types
$data = [
    'id' => '123',        // String converted to int
    'price' => '99.99',   // String converted to float
    'isActive' => 1,      // Int converted to bool
    'tags' => 'tag1,tag2', // Depends on your conversion logic
    'description' => null
];

$object = TypeExample::from($data);
// Types are automatically converted to match property declarations
```

### Custom Type Conversion

Handle complex type conversion scenarios:

```php
<?php

final readonly class Product extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public Money $price,  // Custom value object
        public Category $category  // Enum or value object
    ) {}
}

// Granite will attempt to convert:
// - Arrays to custom objects using their ::from() method
// - Strings to enums using their cases
// - Complex data structures to matching types
```

## Working with Enums

Granite has built-in support for PHP 8.1+ enums:

### String Enums

```php
<?php

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
    case SUSPENDED = 'suspended';
}

final readonly class User extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public UserStatus $status
    ) {}
}

// Deserialization: string to enum
$user = User::from([
    'id' => 1,
    'name' => 'John',
    'status' => 'active'  // Converted to UserStatus::ACTIVE
]);

// Serialization: enum to string
$data = $user->array();
// Result: ['id' => 1, 'name' => 'John', 'status' => 'active']
```

### Integer Enums

```php
<?php

enum Priority: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;
    case URGENT = 4;
}

final readonly class Task extends GraniteDTO
{
    public function __construct(
        public string $title,
        public Priority $priority
    ) {}
}

$task = Task::from([
    'title' => 'Important task',
    'priority' => 3  // Converted to Priority::HIGH
]);
```

### Unit Enums

```php
<?php

enum Color
{
    case RED;
    case GREEN;
    case BLUE;
}

final readonly class Product extends GraniteDTO
{
    public function __construct(
        public string $name,
        public Color $color
    ) {}
}

$product = Product::from([
    'name' => 'T-Shirt',
    'color' => 'RED'  // Converted to Color::RED
]);

$data = $product->array();
// Result: ['name' => 'T-Shirt', 'color' => 'RED']
```

## Date and Time Handling

Granite provides seamless DateTime conversion:

### Basic DateTime

```php
<?php

use DateTimeInterface;
use DateTimeImmutable;

final readonly class Event extends GraniteDTO
{
    public function __construct(
        public string $name,
        public DateTimeInterface $startDate,
        public ?DateTimeInterface $endDate = null
    ) {}
}

// Input with string dates
$event = Event::from([
    'name' => 'Conference',
    'startDate' => '2024-06-15 09:00:00',
    'endDate' => '2024-06-15 17:00:00'
]);

// Serialization formats dates as ISO 8601
$data = $event->array();
// Result: [
//     'name' => 'Conference',
//     'startDate' => '2024-06-15T09:00:00+00:00',
//     'endDate' => '2024-06-15T17:00:00+00:00'
// ]
```

### Custom Date Formats

Control date formatting during serialization:

```php
<?php

final readonly class BlogPost extends GraniteDTO
{
    public function __construct(
        public string $title,
        public string $content,
        public DateTimeInterface $publishedAt,
        public DateTimeInterface $updatedAt
    ) {}
    
    public function array(): array
    {
        $data = parent::array();
        
        // Custom date formatting
        $data['publishedAt'] = $this->publishedAt->format('Y-m-d');
        $data['updatedAt'] = $this->updatedAt->format('c');
        
        return $data;
    }
}
```

## Nested Objects

Handle complex nested object structures:

### Simple Nesting

```php
<?php

final readonly class Address extends GraniteDTO
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public string $zipCode
    ) {}
}

final readonly class User extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public Address $address
    ) {}
}

// Nested input data
$userData = [
    'id' => 1,
    'name' => 'John Doe',
    'address' => [
        'street' => '123 Main St',
        'city' => 'New York',
        'country' => 'USA',
        'zipCode' => '10001'
    ]
];

$user = User::from($userData);

// Nested serialization
$output = $user->array();
// Result preserves nested structure
```

### Arrays of Objects

```php
<?php

final readonly class Order extends GraniteDTO
{
    public function __construct(
        public int $id,
        public array $items,  // Array of OrderItem objects
        public Address $shippingAddress
    ) {}
}

final readonly class OrderItem extends GraniteDTO
{
    public function __construct(
        public int $productId,
        public string $name,
        public int $quantity,
        public float $price
    ) {}
}

$orderData = [
    'id' => 1,
    'items' => [
        ['productId' => 1, 'name' => 'Item 1', 'quantity' => 2, 'price' => 10.00],
        ['productId' => 2, 'name' => 'Item 2', 'quantity' => 1, 'price' => 25.00]
    ],
    'shippingAddress' => [
        'street' => '456 Oak Ave',
        'city' => 'Los Angeles',
        'country' => 'USA',
        'zipCode' => '90210'
    ]
];

// Note: Manual conversion needed for arrays of objects
// Consider using AutoMapper for complex scenarios
```

## Advanced Serialization

### Custom Serialization Logic

Override serialization behavior for specific needs:

```php
<?php

final readonly class UserProfile extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public array $preferences,
        public DateTimeInterface $lastLogin
    ) {}
    
    public function array(): array
    {
        $data = parent::array();
        
        // Custom serialization logic
        $data['preferences'] = json_encode($this->preferences);
        $data['lastLoginFormatted'] = $this->lastLogin->format('M j, Y g:i A');
        $data['isRecentlyActive'] = $this->lastLogin > new DateTimeImmutable('-1 hour');
        
        return $data;
    }
}
```

### Conditional Serialization

Include properties based on conditions:

```php
<?php

final readonly class ApiResponse extends GraniteDTO
{
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $error = null,
        public ?array $debug = null
    ) {}
    
    public function array(): array
    {
        $result = ['success' => $this->success];
        
        if ($this->success && $this->data !== null) {
            $result['data'] = $this->data;
        }
        
        if (!$this->success && $this->error !== null) {
            $result['error'] = $this->error;
        }
        
        // Only include debug in development
        if ($this->debug !== null && app()->environment('local')) {
            $result['debug'] = $this->debug;
        }
        
        return $result;
    }
}
```

### Versioned Serialization

Handle different API versions:

```php
<?php

final readonly class UserV2 extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $metadata = []
    ) {}
    
    public function toV1(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email
            // Exclude metadata for v1 compatibility
        ];
    }
    
    public function toV2(): array
    {
        return $this->array();
    }
}
```

## Best Practices

1. **Use attributes for simple cases** - They're more declarative and easier to read
2. **Use methods for complex logic** - When you need dynamic behavior or complex transformations
3. **Be consistent with naming conventions** - Choose snake_case or camelCase and stick with it
4. **Hide sensitive data by default** - Use `#[Hidden]` for passwords, tokens, and internal data
5. **Handle null values gracefully** - Ensure your serialization logic handles optional properties
6. **Document custom serialization** - Make it clear when you're using custom logic
7. **Test serialization thoroughly** - Ensure both directions (to/from) work correctly
8. **Consider performance** - Cache expensive serialization operations when possible