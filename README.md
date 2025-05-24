# 🪨 Granite

[![Latest Version on Packagist](https://img.shields.io/packagist/v/diego-ninja/granite.svg?style=flat&color=blue)](https://packagist.org/packages/diego-ninja/granite)
[![Total Downloads](https://img.shields.io/packagist/dt/diego-ninja/granite.svg?style=flat&color=blue)](https://packagist.org/packages/diego-ninja/granite)
![PHP Version](https://img.shields.io/packagist/php-v/diego-ninja/granite.svg?style=flat&color=blue)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
![GitHub last commit](https://img.shields.io/github/last-commit/diego-ninja/granite?color=blue)
[![wakatime](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/455eea5b-8838-4d42-b60e-c79c75c63ca2.svg)](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/455eea5b-8838-4d42-b60e-c79c75c63ca2)

A powerful, zero-dependency PHP library for building **immutable**, **serializable** objects with **validation** and **mapping** capabilities. Perfect for DTOs, Value Objects, API responses, and domain modeling.

## ✨ Features

### 🔒 **Immutable Objects**
- Read-only DTOs and Value Objects
- Thread-safe by design
- Functional programming friendly

### ✅ **Comprehensive Validation**
- 25+ built-in validation rules
- Attribute-based validation (PHP 8+)
- Custom validation rules and callbacks
- Conditional and nested validation

### 🔄 **Powerful AutoMapper**
- Automatic property mapping between objects
- Convention-based mapping with multiple naming conventions
- Custom transformations and collection mapping
- Bidirectional mapping support

### 📦 **Smart Serialization**
- JSON/Array serialization with custom property names
- Hide sensitive properties automatically
- DateTime and Enum handling
- Nested object serialization

### ⚡ **Performance Optimized**
- Reflection caching for improved performance
- Memory-efficient object creation
- Lazy loading support

## 🚀 Quick Start

### Installation

```bash
composer require diego-ninja/granite
```

### Basic Usage

```php
<?php

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Serialization\Attributes\Hidden;

// Create a Value Object with validation
final readonly class User extends GraniteVO
{
    public function __construct(
        public ?int $id,
        
        #[Required]
        #[Min(2)]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Hidden] // Won't appear in JSON
        public ?string $password = null,
        
        #[SerializedName('created_at')]
        public DateTime $createdAt = new DateTime()
    ) {}
}

// Create and validate
$user = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);

// Immutable updates
$updatedUser = $user->with(['name' => 'Jane Doe']);

// Serialization
$json = $user->json();
// {"id":null,"name":"John Doe","email":"john@example.com","created_at":"2024-01-15T10:30:00+00:00"}

$array = $user->array();
// password is hidden, created_at uses custom name
```

## 📖 Documentation

### Core Concepts

- **[Validation](docs/validation.md)** - Comprehensive validation system with 25+ built-in rules
- **[Serialization](docs/serialization.md)** - Control how objects are converted to/from arrays and JSON
- **[AutoMapper](docs/automapper.md)** - Powerful object-to-object mapping with conventions
- **[Advanced Usage](docs/advanced_usage.md)** - Patterns for complex applications

### Guides

- **[Migration Guide](docs/migration_guide.md)** - Migrate from arrays, stdClass, Doctrine, Laravel
- **[Troubleshooting](docs/troubleshooting.md)** - Common issues and solutions

## 🎯 Use Cases

### API Development

```php
// Request validation
final readonly class CreateUserRequest extends GraniteVO
{
    public function __construct(
        #[Required]
        #[StringType]
        #[Min(2)]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Required]
        #[Min(8)]
        #[Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', 'Password must contain uppercase, lowercase, and number')]
        public string $password
    ) {}
}

// API Response
final readonly class UserResponse extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        
        #[SerializedName('member_since')]
        public DateTime $createdAt
    ) {}
    
    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            createdAt: $user->createdAt
        );
    }
}
```

### Domain Modeling

```php
// Value Objects
final readonly class Money extends GraniteVO
{
    public function __construct(
        #[Required]
        #[NumberType]
        #[Min(0)]
        public float $amount,
        
        #[Required]
        #[StringType]
        #[Min(3)]
        #[Max(3)]
        public string $currency
    ) {}
    
    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add different currencies');
        }
        
        return new Money($this->amount + $other->amount, $this->currency);
    }
}

// Aggregates
final readonly class Order extends GraniteVO
{
    public function __construct(
        public ?int $id,
        
        #[Required]
        public int $customerId,
        
        #[Required]
        #[ArrayType]
        #[Each(new Rules\Callback(fn($item) => OrderItem::from($item)))]
        public array $items,
        
        #[Required]
        public OrderStatus $status = OrderStatus::PENDING
    ) {}
    
    public function getTotal(): Money
    {
        $total = new Money(0.0, 'USD');
        foreach ($this->items as $item) {
            $total = $total->add($item->getTotal());
        }
        return $total;
    }
}
```

### Object Mapping

```php
use Ninja\Granite\Mapping\AutoMapper;
use Ninja\Granite\Mapping\Attributes\MapFrom;

// Source entity
final readonly class UserEntity extends GraniteDTO
{
    public function __construct(
        public int $userId,
        public string $fullName,
        public string $emailAddress,
        public DateTime $createdAt
    ) {}
}

// Destination DTO with mapping
final readonly class UserSummary extends GraniteDTO
{
    public function __construct(
        #[MapFrom('userId')]
        public int $id,
        
        #[MapFrom('fullName')]
        public string $name,
        
        #[MapFrom('emailAddress')]
        public string $email
    ) {}
}

// Automatic mapping
$mapper = new AutoMapper();
$summary = $mapper->map($userEntity, UserSummary::class);
```

## 🔥 Advanced Features

### Convention-Based Mapping

```php
// Automatically maps between different naming conventions
class SourceClass {
    public string $firstName;     // camelCase
    public string $email_address; // snake_case
    public string $UserID;        // PascalCase
}

class DestinationClass {
    public string $first_name;    // snake_case
    public string $emailAddress;  // camelCase  
    public string $user_id;       // snake_case
}

$mapper = new AutoMapper(useConventions: true);
$result = $mapper->map($source, DestinationClass::class);
// Properties automatically mapped based on naming conventions!
```

### Complex Validation

```php
final readonly class CreditCard extends GraniteVO
{
    public function __construct(
        #[Required]
        #[Regex('/^\d{4}\s?\d{4}\s?\d{4}\s?\d{4}$/', 'Invalid card number format')]
        public string $number,
        
        #[Required]
        #[Regex('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', 'Invalid expiry format (MM/YY)')]
        public string $expiry,
        
        #[Required]
        #[Regex('/^\d{3,4}$/', 'Invalid CVV')]
        public string $cvv,
        
        #[When(
            condition: fn($value, $data) => $data['type'] === 'business',
            rule: new Required()
        )]
        public ?string $companyName = null
    ) {}
    
    protected static function rules(): array
    {
        return [
            'number' => [
                new Callback(function($number) {
                    // Luhn algorithm validation
                    return $this->isValidLuhn(str_replace(' ', '', $number));
                }, 'Invalid credit card number')
            ]
        ];
    }
}
```

### Event Sourcing

```php
abstract readonly class DomainEvent extends GraniteDTO
{
    public function __construct(
        #[Required]
        public string $eventId,
        
        #[Required]
        public string $aggregateId,
        
        #[Required]
        #[SerializedName('event_type')]
        public string $eventType,
        
        #[Required]
        #[SerializedName('occurred_at')]
        public DateTime $occurredAt
    ) {}
}

final readonly class UserCreatedEvent extends DomainEvent
{
    public function __construct(
        string $eventId,
        string $aggregateId,
        DateTime $occurredAt,
        
        #[Required]
        public string $name,
        
        #[Required]
        public string $email
    ) {
        parent::__construct($eventId, $aggregateId, 'user_created', $occurredAt);
    }
}
```

## 🛠 Validation Rules

### Built-in Rules

| Rule | Description | Example |
|------|-------------|---------|
| `#[Required]` | Field must not be null | `#[Required('Name is required')]` |
| `#[Email]` | Valid email format | `#[Email('Invalid email')]` |
| `#[Min(5)]` | Minimum length/value | `#[Min(5, 'Too short')]` |
| `#[Max(100)]` | Maximum length/value | `#[Max(100, 'Too long')]` |
| `#[Regex('/pattern/')]` | Regular expression | `#[Regex('/^\d+$/', 'Numbers only')]` |
| `#[In(['a', 'b'])]` | Value in list | `#[In(['active', 'inactive'])]` |
| `#[Url]` | Valid URL format | `#[Url('Invalid URL')]` |
| `#[IpAddress]` | Valid IP address | `#[IpAddress('Invalid IP')]` |
| `#[StringType]` | Must be string | `#[StringType]` |
| `#[IntegerType]` | Must be integer | `#[IntegerType]` |
| `#[NumberType]` | Must be number | `#[NumberType]` |
| `#[BooleanType]` | Must be boolean | `#[BooleanType]` |
| `#[ArrayType]` | Must be array | `#[ArrayType]` |
| `#[EnumType]` | Valid enum value | `#[EnumType(Status::class)]` |
| `#[Each(...)]` | Validate array items | `#[Each(new Email())]` |
| `#[When(...)]` | Conditional validation | `#[When($condition, $rule)]` |

### Custom Rules

```php
class UniqueEmail extends AbstractRule
{
    public function __construct(private UserRepository $repo) {}
    
    public function validate(mixed $value, ?array $allData = null): bool
    {
        return $value === null || !$this->repo->existsByEmail($value);
    }
    
    protected function defaultMessage(string $property): string
    {
        return "{$property} must be unique";
    }
}

// Usage
#[Required]
#[Email]
public string $email;

protected static function rules(): array
{
    return [
        'email' => [new UniqueEmail(new UserRepository())]
    ];
}
```

## 🎨 Serialization Control

```php
final readonly class ApiUser extends GraniteDTO
{
    public function __construct(
        public int $id,
        
        #[SerializedName('display_name')]
        public string $name,
        
        #[SerializedName('email_address')]
        public string $email,
        
        #[SerializedName('avatar_url')]
        public ?string $avatarUrl,
        
        #[SerializedName('member_since')]
        public DateTime $createdAt,
        
        // Hidden from serialization
        #[Hidden]
        public ?string $passwordHash = null,
        
        #[Hidden]
        public ?array $permissions = null
    ) {}
}

$user = ApiUser::from($userData);
$json = $user->json();
// {
//   "id": 1,
//   "display_name": "John Doe", 
//   "email_address": "john@example.com",
//   "avatar_url": "https://example.com/avatar.jpg",
//   "member_since": "2024-01-15T10:30:00+00:00"
// }
// passwordHash and permissions are not included
```

## 🔄 AutoMapper Examples

### Basic Mapping

```php
$mapper = new AutoMapper();

// Simple mapping
$userDto = $mapper->map($userEntity, UserDto::class);

// Collection mapping  
$userDtos = $mapper->mapArray($userEntities, UserDto::class);
```

### Custom Transformations

```php
use Ninja\Granite\Mapping\MappingProfile;

class UserMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(UserEntity::class, UserResponse::class)
            ->forMember('fullName', fn($m) => 
                $m->using(function($value, $sourceData) {
                    return $sourceData['firstName'] . ' ' . $sourceData['lastName'];
                })
            )
            ->forMember('age', fn($m) => 
                $m->mapFrom('birthDate')
                  ->using(fn($birthDate) => (new DateTime())->diff($birthDate)->y)
            )
            ->seal();
    }
}

$mapper = new AutoMapper([new UserMappingProfile()]);
```

### Collection Transformations

```php
use Ninja\Granite\Mapping\Attributes\MapCollection;

final readonly class TeamResponse extends GraniteDTO
{
    public function __construct(
        public string $name,
        
        #[MapCollection(UserResponse::class)]
        public array $members,
        
        #[MapCollection(ProjectResponse::class, preserveKeys: true)]
        public array $projects
    ) {}
}
```

## 📈 Performance

Granite is optimized for performance with:

- **Reflection caching** - Class metadata cached automatically
- **Mapping cache** - AutoMapper configurations cached
- **Memory efficiency** - Immutable objects reduce memory overhead
- **Lazy loading** - Load related data only when needed

```php
// Use shared cache for web applications
$mapper = new AutoMapper(cacheType: CacheType::Shared);

// Preload mappings for better performance
MappingPreloader::preload($mapper, [
    [UserEntity::class, UserResponse::class],
    [ProductEntity::class, ProductResponse::class]
]);
```

## 🧪 Testing

Granite objects are perfect for testing due to their immutability and validation:

```php
class UserTest extends PHPUnit\Framework\TestCase
{
    public function testUserCreation(): void
    {
        $user = User::from([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }
    
    public function testUserValidation(): void
    {
        $this->expectException(ValidationException::class);
        
        User::from([
            'name' => 'X', // Too short
            'email' => 'invalid-email'
        ]);
    }
    
    public function testImmutability(): void
    {
        $user = User::from(['name' => 'John', 'email' => 'john@example.com']);
        $updated = $user->with(['name' => 'Jane']);
        
        // Original unchanged
        $this->assertEquals('John', $user->name);
        // New instance created
        $this->assertEquals('Jane', $updated->name);
    }
}
```

## 🔧 Requirements

- **PHP 8.3+** - Takes advantage of modern PHP features
- **No dependencies** - Zero external dependencies for maximum compatibility

## 📦 Installation & Setup

```bash
# Install via Composer
composer require diego-ninja/granite

# Optional: Configure cache directory for persistent mapping cache
mkdir cache/granite
chmod 755 cache/granite
```

## 🏗 Architecture

Granite follows clean architecture principles:

```
src/
├── Contracts/           # Core interfaces
├── Validation/          # Validation system
│   ├── Attributes/      # Validation attributes
│   ├── Rules/           # Validation rules
│   └── ...
├── Serialization/       # Serialization system
│   ├── Attributes/      # Serialization attributes
│   └── ...
├── Mapping/             # AutoMapper system
│   ├── Attributes/      # Mapping attributes
│   ├── Conventions/     # Naming conventions
│   ├── Transformers/    # Data transformers
│   └── ...
├── Support/             # Utilities (reflection cache, etc.)
├── Exceptions/          # Custom exceptions
└── Enums/              # System enums
```

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Credits

This project is developed and maintained by 🥷 [Diego Rin](https://diego.ninja) in his free time.

If you find this project useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs and issues
- 💡 Suggesting new features
- 🔧 Contributing code improvements

---

**Made with ❤️ for the PHP community**