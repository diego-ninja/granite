# 🪨 Granite

[![Latest Version on Packagist](https://img.shields.io/packagist/v/diego-ninja/granite.svg?style=flat&color=blue)](https://packagist.org/packages/diego-ninja/granite)
[![Total Downloads](https://img.shields.io/packagist/dt/diego-ninja/granite.svg?style=flat&color=blue)](https://packagist.org/packages/diego-ninja/granite)
![PHP Version](https://img.shields.io/packagist/php-v/diego-ninja/granite.svg?style=flat&color=blue)
[![Code Style](https://img.shields.io/badge/code%20style-PER-blue.svg?style=flat)](https://www.php-fig.org/per/)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](https://opensource.org/licenses/MIT)
![GitHub last commit](https://img.shields.io/github/last-commit/diego-ninja/granite?color=blue)
[![wakatime](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/455eea5b-8838-4d42-b60e-c79c75c63ca2.svg)](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/455eea5b-8838-4d42-b60e-c79c75c63ca2)

[![Tests](https://github.com/diego-ninja/granite/actions/workflows/tests.yml/badge.svg)](https://github.com/diego-ninja/granite/actions/workflows/tests.yml)
[![Static Analysis](https://github.com/diego-ninja/granite/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/diego-ninja/granite/actions/workflows/static-analysis.yml)
[![Code Style](https://github.com/diego-ninja/granite/actions/workflows/code-style.yml/badge.svg)](https://github.com/diego-ninja/granite/actions/workflows/code-style.yml)
[![Coverage](https://img.shields.io/coveralls/github/diego-ninja/granite?style=flat)](https://coveralls.io/github/diego-ninja/granite)

A powerful, zero-dependency PHP library for building **immutable**, **serializable** objects with **validation** and **mapping** capabilities. Perfect for DTOs, Value Objects, API responses, and domain modeling.

This documentation has been generated almost in its entirety using 🦠 Claude 4 Sonnet based on source code analysis. Some sections may be incomplete, outdated or may contain documentation for planned or not-released features. For the most accurate information, please refer to the source code or open an issue on the package repository.

## ✨ Features

### 🔒 **Immutable Objects**
- Read-only DTOs and Value Objects
- Thread-safe by design
- Functional programming friendly

### 🎯 **Enhanced Object Creation**
- **Multiple `from()` patterns** - Array, JSON, named parameters, mixed usage
- **Transparent operation** - No method overrides needed in child classes
- **Type-safe** - Full PHPStan Level 10 compatibility
- **Flexible usage** - Perfect for APIs, domain modeling, and clean code

### ✅ **Comprehensive Validation**
- 30+ built-in validation rules including Carbon date validation
- Attribute-based validation (PHP 8+)
- Custom validation rules and callbacks
- Conditional and nested validation
- **Carbon-specific rules** - Age, BusinessDay, Future, Past, Range, Weekend

### 🔄 **Powerful ObjectMapper**
- Automatic property mapping between objects
- Convention-based mapping with multiple naming conventions
- Custom transformations and collection mapping
- Bidirectional mapping support

### 📦 **Smart Serialization**
- JSON/Array serialization with custom property names
- Class-level naming conventions with `SerializationConvention` attribute
- Hide sensitive properties automatically
- **Carbon date handling** - Custom formats, relative parsing, timezone support
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
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Carbon\Carbon;

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
        #[CarbonDate(format: 'Y-m-d H:i:s')]
        public Carbon $createdAt = new Carbon()
    ) {}
}

// Multiple ways to create objects - all work transparently!

// 1. From array (traditional)
$user = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);

// 2. From JSON string
$user = User::from('{"name": "John Doe", "email": "john@example.com", "password": "secret123"}');

// 3. Named parameters (NEW!) - Works transparently without method overrides
$user = User::from(
    name: 'John Doe',
    email: 'john@example.com',
    password: 'secret123'
);

// 4. Mixed - base data with named overrides (NEW!)
$baseData = ['name' => 'John', 'email' => 'john@example.com'];
$user = User::from($baseData, name: 'John Doe', password: 'secret123');

// 5. From another Granite object
$anotherUser = User::from($user);

// Immutable updates
$updatedUser = $user->with(['name' => 'Jane Doe']);

// Serialization with Carbon support
$json = $user->json();
// {"id":null,"name":"John Doe","email":"john@example.com","created_at":"2024-01-15 10:30:00"}

$array = $user->array();
// password is hidden, created_at uses custom Carbon format
```

### 🎯 Enhanced `from()` Method

Granite's `from()` method supports multiple invocation patterns **transparently** - no need to override methods in child classes!

```php
final readonly class Product extends GraniteDTO
{
    public function __construct(
        public string $name,
        public float $price,
        public ?string $description = null,
        #[CarbonDate] 
        public Carbon $createdAt = new Carbon()
    ) {}
}

// All these patterns work automatically:

// Array data
$product = Product::from(['name' => 'Laptop', 'price' => 999.99]);

// JSON string  
$product = Product::from('{"name": "Laptop", "price": 999.99}');

// Named parameters - perfect for APIs and clean code
$product = Product::from(
    name: 'Laptop',
    price: 999.99,
    description: 'High-performance laptop'
);

// Mixed patterns - base data + overrides
$defaults = ['name' => 'Generic Product', 'price' => 0.0];
$product = Product::from($defaults, name: 'Laptop', price: 999.99);

// From another Granite object
$clonedProduct = Product::from($product);

// Partial data - unspecified properties remain uninitialized
$partial = Product::from(name: 'Laptop', price: 999.99);
// $partial->description is uninitialized, not null
```

### 📅 Carbon Date Support

Granite includes comprehensive support for Carbon dates with specialized attributes:

```php
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Ninja\Granite\Validation\Rules\Carbon\Age;
use Ninja\Granite\Validation\Rules\Carbon\Future;
use Ninja\Granite\Validation\Rules\Carbon\BusinessDay;

final readonly class Event extends GraniteVO  
{
    public function __construct(
        public string $title,
        
        // Custom date format for serialization
        #[CarbonDate(format: 'd/m/Y H:i')]
        public Carbon $startDate,
        
        // Date range validation
        #[CarbonRange(min: 'now', max: '+1 year')]
        public Carbon $endDate,
        
        // Relative date parsing ('tomorrow', '2 weeks ago', etc.)
        #[CarbonRelative]
        public ?Carbon $reminderDate = null,
        
        // Business logic validation
        #[Future(message: 'Event must be in the future')]
        #[BusinessDay(message: 'Event must be on a business day')]
        public Carbon $publishDate
    ) {}
}

// Create with various date formats
$event = Event::from(
    title: 'Conference',
    startDate: '2024-12-25 09:00:00',     // Standard format
    endDate: Carbon::parse('+3 days'),     // Carbon object
    reminderDate: 'tomorrow at 9am',       // Relative format
    publishDate: '2024-12-01'              // Date only
);

// Serialization uses custom formats
$json = $event->json();
// {"title":"Conference","startDate":"25/12/2024 09:00",...}
```

### Serialization Conventions

Apply naming conventions to all properties in a class during serialization:

```php
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class UserProfile extends GraniteVO
{
    public function __construct(
        public string $firstName,      // serialized as "first_name"
        public string $lastName,       // serialized as "last_name"
        public string $emailAddress,   // serialized as "email_address"
        
        #[SerializedName('user_id')]   // explicit name takes precedence
        public int $id
    ) {}
}

$profile = new UserProfile('John', 'Doe', 'john@example.com', 123);
$json = $profile->json();
// {"first_name":"John","last_name":"Doe","email_address":"john@example.com","user_id":123}
```

## 📖 Documentation

### Core Concepts

- **[Enhanced from() Method](docs/hydration.md)** - Multiple invocation patterns for flexible object creation ✨ NEW
- **[Validation](docs/validation.md)** - Comprehensive validation system with 30+ built-in rules including Carbon
- **[Serialization](docs/serialization.md)** - Control how objects are converted to/from arrays and JSON with Carbon support
- **[ObjectMapper](docs/automapper.md)** - Powerful object-to-object mapping with conventions
- **[Advanced Usage](docs/advanced_usage.md)** - Patterns for complex applications
- **[API Reference](docs/api_reference.md)** - Complete API documentation with new Carbon features


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

### Object Mapping with ObjectMapper

```php
use Ninja\Granite\Mapping\ObjectMapper;
use Ninja\Granite\Mapping\ObjectMapperConfig;
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

// Destination DTO with mapping attributes
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

// Create ObjectMapper with clean configuration
$mapper = new ObjectMapper(
    ObjectMapperConfig::forProduction()
        ->withConventions(true, 0.8)
        ->withSharedCache()
);

// Automatic mapping
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

$mapper = new ObjectMapper(
    ObjectMapperConfig::create()
        ->withConventions(true, 0.8)
);

$result = $mapper->map($source, DestinationClass::class);
// Properties automatically mapped based on naming conventions!
```

### Advanced ObjectMapper Configuration

```php
use Ninja\Granite\Mapping\ObjectMapperConfig;
use Ninja\Granite\Mapping\MappingProfile;

// Fluent configuration with builder pattern
$mapper = new ObjectMapper(
    ObjectMapperConfig::forProduction()
        ->withSharedCache()
        ->withConventions(true, 0.8)
        ->withProfile(new UserMappingProfile())
        ->withProfile(new ProductMappingProfile())
        ->withWarmup()
);

// Predefined configurations
$devMapper = new ObjectMapper(ObjectMapperConfig::forDevelopment());
$prodMapper = new ObjectMapper(ObjectMapperConfig::forProduction());
$testMapper = new ObjectMapper(ObjectMapperConfig::forTesting());
```

### Custom Mapping Profiles

```php
use Ninja\Granite\Mapping\MappingProfile;

class UserMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        // Complex transformations
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
            ->forMember('isActive', fn($m) => 
                $m->mapFrom('status')
                  ->using(fn($status) => $status === 'active')
            )
            ->seal();

        // Bidirectional mapping
        $this->createMapBidirectional(UserEntity::class, UserDto::class)
            ->forMembers('userId', 'id')
            ->forMembers('fullName', 'name')
            ->forMembers('emailAddress', 'email')
            ->seal();
    }
}
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

### Global ObjectMapper Configuration

```php
// Configure once at application startup
ObjectMapper::configure(function(ObjectMapperConfig $config) {
    $config->withSharedCache()
           ->withConventions(true, 0.8)
           ->withProfiles([
               new UserMappingProfile(),
               new ProductMappingProfile(),
               new OrderMappingProfile()
           ])
           ->withWarmup();
});

// Use anywhere in your application
$mapper = ObjectMapper::getInstance();
$userDto = $mapper->map($userEntity, UserDto::class);
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

### 📅 Carbon Date Validation Rules

| Rule | Description | Example |
|------|-------------|---------|
| `#[Age(min: 18, max: 65)]` | Validate age range | `#[Age(min: 18, message: 'Must be adult')]` |
| `#[BusinessDay]` | Must be a business day | `#[BusinessDay('Only weekdays allowed')]` |
| `#[Future]` | Date must be in the future | `#[Future('Event must be upcoming')]` |
| `#[Past]` | Date must be in the past | `#[Past('Birth date must be past')]` |
| `#[Range(min: 'now', max: '+1 year')]` | Date within range | `#[Range(min: 'today', max: 'next month')]` |
| `#[Weekend]` | Must be weekend | `#[Weekend('Event only on weekends')]` |

## 📈 Performance

Granite is optimized for performance with:

- **Reflection caching** - Class metadata cached automatically
- **Mapping cache** - ObjectMapper configurations cached
- **Memory efficiency** - Immutable objects reduce memory overhead
- **Lazy loading** - Load related data only when needed
- **Specialized components** - Refactored architecture with focused responsibilities

```php
// Use shared cache for web applications
$mapper = new ObjectMapper(
    ObjectMapperConfig::forProduction()
        ->withSharedCache()
        ->withWarmup()  // Preload configurations
);

// Preload mappings for better performance
use Ninja\Granite\Mapping\MappingPreloader;

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
    
    public function testObjectMapping(): void
    {
        $mapper = new ObjectMapper(ObjectMapperConfig::forTesting());
        
        $entity = new UserEntity(1, 'John Doe', 'john@example.com', new DateTime());
        $dto = $mapper->map($entity, UserDto::class);
        
        $this->assertInstanceOf(UserDto::class, $dto);
        $this->assertEquals(1, $dto->id);
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