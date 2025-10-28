# Serialization

Granite provides powerful serialization capabilities that allow you to control how your objects are converted to and from arrays and JSON. This is essential for APIs, data persistence, and data exchange between different systems.

## Table of Contents

- [Basic Serialization](#basic-serialization)
- [Property Name Mapping](#property-name-mapping)
- [Class-Level Naming Conventions](#class-level-naming-conventions)
- [Hiding Properties](#hiding-properties)
- [Method-based Configuration](#method-based-configuration)
- [Custom Serialization](#custom-serialization)
- [DateTime Handling](#datetime-handling)
- [Enum Serialization](#enum-serialization)
- [Nested Objects](#nested-objects)
- [Advanced Scenarios](#advanced-scenarios)

## Basic Serialization

All Granite objects (DTOs and VOs) can be easily converted to arrays and JSON:

```php
<?php

use Ninja\Granite\Granite;

final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public DateTime $createdAt
    ) {}
}

$user = User::from([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'createdAt' => '2023-01-15T10:30:00Z'
]);

// Convert to array
$array = $user->array();
// Result: ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'createdAt' => '2023-01-15T10:30:00+00:00']

// Convert to JSON
$json = $user->json();
// Result: {"id":1,"name":"John Doe","email":"john@example.com","createdAt":"2023-01-15T10:30:00+00:00"}
```

## Property Name Mapping

Use the `#[SerializedName]` attribute to customize property names in the serialized output:

```php
<?php

use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class ApiUser extends Granite
{
    public function __construct(
        public int $id,
        
        #[SerializedName('full_name')]
        public string $name,
        
        #[SerializedName('email_address')]
        public string $email,
        
        #[SerializedName('profile_image')]
        public ?string $avatarUrl = null,
        
        #[SerializedName('created_at')]
        public DateTime $createdAt
    ) {}
}

$user = ApiUser::from([
    'id' => 1,
    'full_name' => 'John Doe',
    'email_address' => 'john@example.com',
    'created_at' => '2023-01-15T10:30:00Z'
]);

$array = $user->array();
// Result: {
//   'id' => 1,
//   'full_name' => 'John Doe',
//   'email_address' => 'john@example.com',
//   'profile_image' => null,
//   'created_at' => '2023-01-15T10:30:00+00:00'
// }
```

### Deserialization with Custom Names

Granite automatically handles both the PHP property name and the serialized name during deserialization:

```php
// Both of these work:
$user1 = ApiUser::from([
    'id' => 1,
    'name' => 'John Doe',  // PHP property name
    'email' => 'john@example.com'
]);

$user2 = ApiUser::from([
    'id' => 1,
    'full_name' => 'John Doe',  // Serialized name
    'email_address' => 'john@example.com'
]);
```

## Class-Level Naming Conventions

The `#[SerializationConvention]` attribute allows you to apply a naming convention to all properties in a class during serialization. This is particularly useful when you need consistent naming across all properties without having to specify `#[SerializedName]` for each one:

```php
<?php

use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;
use Ninja\Granite\Mapping\Conventions\CamelCaseConvention;
use Ninja\Granite\Mapping\Conventions\PascalCaseConvention;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class UserProfile extends Granite
{
    public function __construct(
        public int $id,
        public string $firstName,      // serialized as "first_name"
        public string $lastName,       // serialized as "last_name"
        public string $emailAddress,   // serialized as "email_address"
        public DateTime $createdAt,    // serialized as "created_at"
        public DateTime $lastLoginAt,  // serialized as "last_login_at"
        
        // Explicit SerializedName takes precedence over convention
        #[SerializedName('user_id')]
        public int $userId
    ) {}
}

$profile = UserProfile::from([
    'id' => 1,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email_address' => 'john@example.com',
    'created_at' => '2023-01-15T10:30:00Z',
    'last_login_at' => '2023-01-20T15:45:00Z',
    'user_id' => 123
]);

$array = $profile->array();
// Result: {
//   'id' => 1,
//   'first_name' => 'John',
//   'last_name' => 'Doe',
//   'email_address' => 'john@example.com',
//   'created_at' => '2023-01-15T10:30:00+00:00',
//   'last_login_at' => '2023-01-20T15:45:00+00:00',
//   'user_id' => 123
// }
```

### Available Naming Conventions

Granite provides several built-in naming conventions:

```php
// Snake case: camelCase -> snake_case
#[SerializationConvention(SnakeCaseConvention::class)]

// Camel case: snake_case -> camelCase
#[SerializationConvention(CamelCaseConvention::class)]

// Pascal case: camelCase -> PascalCase
#[SerializationConvention(PascalCaseConvention::class)]
```

### Bidirectional Conventions

By default, conventions are applied bidirectionally (both serialization and deserialization). You can control this behavior:

```php
#[SerializationConvention(
    convention: SnakeCaseConvention::class,
    bidirectional: true  // Default: applies to both directions
)]
final readonly class ApiResponse extends Granite
{
    public function __construct(
        public string $userId,        // accepts both "userId" and "user_id"
        public string $displayName    // accepts both "displayName" and "display_name"
    ) {}
}

// Both of these work during deserialization:
$response1 = ApiResponse::from([
    'userId' => '123',
    'displayName' => 'John Doe'
]);

$response2 = ApiResponse::from([
    'user_id' => '123',
    'display_name' => 'John Doe'
]);

// But serialization always uses the convention:
$array = $response1->array();
// Result: { 'user_id' => '123', 'display_name' => 'John Doe' }
```

### Using Convention Instances

You can also pass a convention instance instead of a class string:

```php
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;

#[SerializationConvention(new SnakeCaseConvention())]
final readonly class ConfiguredClass extends Granite
{
    // ...
}
```

### Precedence Rules

When multiple serialization configurations are present, they follow this precedence order:

1. **`#[SerializedName]` attribute** - Always takes highest precedence
2. **`#[SerializationConvention]` attribute** - Applied to properties without explicit names
3. **Method-based configuration** - Lowest precedence

```php
#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class MixedConfig extends Granite
{
    public function __construct(
        public string $firstName,           // uses convention: "first_name"
        
        #[SerializedName('custom_name')]    // explicit name wins
        public string $lastName,
        
        public string $emailAddress         // uses convention: "email_address"
    ) {}
    
    protected static function serializedNames(): array
    {
        return [
            'firstName' => 'method_name',   // ignored due to convention
            'emailAddress' => 'method_email' // ignored due to convention
        ];
    }
}
```

## Hiding Properties

Use the `#[Hidden]` attribute to exclude sensitive properties from serialization:

```php
<?php

use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class UserAccount extends Granite
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        
        #[Hidden]
        public string $password,
        
        #[Hidden]
        public ?string $apiKey = null,
        
        #[SerializedName('auth_token')]
        #[Hidden]
        public ?string $authToken = null,
        
        public DateTime $lastLogin
    ) {}
}

$account = UserAccount::from([
    'id' => 1,
    'username' => 'johndoe',
    'email' => 'john@example.com',
    'password' => 'secret123',
    'apiKey' => 'key_12345',
    'lastLogin' => '2023-01-15T10:30:00Z'
]);

$safeData = $account->array();
// Result: {
//   'id' => 1,
//   'username' => 'johndoe',
//   'email' => 'john@example.com',
//   'lastLogin' => '2023-01-15T10:30:00+00:00'
// }
// password, apiKey, and authToken are NOT included
```

## Method-based Configuration

For complex scenarios or when you prefer method-based configuration, override the static methods:

```php
<?php

use Ninja\Granite\Granite;

final readonly class LegacyUser extends Granite
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public ?string $socialSecurityNumber = null,
        public ?string $internalNotes = null
    ) {}

    /**
     * Define custom property names for serialization
     */
    protected static function serializedNames(): array
    {
        return [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'socialSecurityNumber' => 'ssn'
        ];
    }

    /**
     * Define properties to hide during serialization
     */
    protected static function hiddenProperties(): array
    {
        return [
            'password',
            'socialSecurityNumber',
            'internalNotes'
        ];
    }
}

$user = LegacyUser::from([
    'id' => 1,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => 'john@example.com',
    'password' => 'secret',
    'ssn' => '123-45-6789',
    'internalNotes' => 'VIP customer'
]);

$publicData = $user->array();
// Result: {
//   'id' => 1,
//   'first_name' => 'John',
//   'last_name' => 'Doe',
//   'email' => 'john@example.com'
// }
```

### Combining Attributes and Methods

Attributes take precedence over method-based configuration:

```php
final readonly class HybridUser extends Granite
{
    public function __construct(
        public int $id,
        
        #[SerializedName('user_name')]  // This overrides method config
        public string $username,
        
        public string $email,
        
        #[Hidden]  // This overrides method config
        public string $password
    ) {}

    protected static function serializedNames(): array
    {
        return [
            'username' => 'login_name',  // Ignored due to attribute
            'email' => 'email_address'   // This will be used
        ];
    }

    protected static function hiddenProperties(): array
    {
        return [
            'email'  // Ignored because email doesn't have #[Hidden] attribute
        ];
    }
}
```

## Custom Serialization

### DateTime Handling

DateTime objects are automatically serialized to ISO 8601 format:

```php
final readonly class Event extends Granite
{
    public function __construct(
        public string $name,
        public DateTime $startDate,
        public ?DateTime $endDate = null,
        public DateTimeImmutable $createdAt
    ) {}
}

$event = Event::from([
    'name' => 'Conference 2024',
    'startDate' => '2024-03-15T09:00:00Z',
    'endDate' => '2024-03-15T17:00:00Z',
    'createdAt' => '2023-12-01T10:30:00Z'
]);

$array = $event->array();
// Result: {
//   'name' => 'Conference 2024',
//   'startDate' => '2024-03-15T09:00:00+00:00',
//   'endDate' => '2024-03-15T17:00:00+00:00',
//   'createdAt' => '2023-12-01T10:30:00+00:00'
// }
```

### 📅 Carbon Date Support

Granite includes comprehensive support for Carbon dates with specialized attributes and behaviors:

```php
<?php

use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\CarbonDate;
use Ninja\Granite\Serialization\Attributes\CarbonRange;
use Ninja\Granite\Serialization\Attributes\CarbonRelative;
use Carbon\Carbon;

final readonly class EventSchedule extends Granite
{
    public function __construct(
        public string $title,
        
        // Custom format for serialization
        #[CarbonDate(format: 'd/m/Y H:i')]
        public Carbon $startDate,
        
        // ISO format with timezone
        #[CarbonDate(format: 'c', timezone: 'UTC')]
        public Carbon $endDate,
        
        // Date only format
        #[CarbonDate(format: 'Y-m-d')]
        public Carbon $publishDate,
        
        // Relative date parsing support
        #[CarbonRelative]
        public ?Carbon $reminderDate = null,
        
        // Range validation during deserialization
        #[CarbonRange(min: 'now', max: '+1 year')]
        public Carbon $deadline
    ) {}
}

// Multiple ways to create with Carbon support
$event = EventSchedule::from([
    'title' => 'Annual Conference',
    'startDate' => '2024-12-25 09:00:00',        // Standard format
    'endDate' => Carbon::parse('2024-12-25 17:00:00'),  // Carbon object
    'publishDate' => '2024-12-01',               // Date only
    'reminderDate' => 'tomorrow at 9am',         // Relative parsing
    'deadline' => '+6 months'                    // Relative format
]);

// Serialization uses the specified formats
$array = $event->array();
// Result: {
//   'title' => 'Annual Conference',
//   'startDate' => '25/12/2024 09:00',        // Custom format
//   'endDate' => '2024-12-25T17:00:00+00:00', // ISO with timezone
//   'publishDate' => '2024-12-01',             // Date only
//   'reminderDate' => '2024-07-31T09:00:00+00:00',
//   'deadline' => '2025-01-30T00:00:00+00:00'
// }
```

#### Carbon Date Attributes

**`#[CarbonDate]`** - Control Carbon serialization format
```php
#[CarbonDate(format: 'Y-m-d H:i:s')]
public Carbon $createdAt;

#[CarbonDate(format: 'c', timezone: 'UTC')]
public Carbon $publishedAt;

#[CarbonDate(format: 'd/m/Y')]  // European date format
public Carbon $eventDate;
```

**`#[CarbonRelative]`** - Enable relative date parsing
```php
#[CarbonRelative]
public ?Carbon $dueDate;

// Accepts: 'tomorrow', 'next week', '2 hours ago', 'first day of next month'
$task = Task::from(['dueDate' => 'next Friday at 5pm']);
```

**`#[CarbonRange]`** - Validate date ranges
```php
#[CarbonRange(min: 'now', max: '+1 year')]
public Carbon $eventDate;  // Must be between now and next year

#[CarbonRange(min: '2024-01-01', max: '2024-12-31')]
public Carbon $fiscalYear;  // Must be within 2024
```

#### Carbon with Multiple Formats

```php
final readonly class FlexibleEvent extends Granite
{
    public function __construct(
        public string $name,
        
        // Accepts multiple input formats, outputs in specific format
        #[CarbonDate(format: 'Y-m-d\TH:i:s\Z')]
        public Carbon $startTime,
        
        // Business hours only
        #[CarbonDate(format: 'H:i')]
        public Carbon $businessHours,
        
        // Week of year
        #[CarbonDate(format: 'W')]
        public Carbon $weekNumber
    ) {}
}

// All these input formats work for startTime
$event1 = FlexibleEvent::from(['startTime' => '2024-12-25 10:30:00']);
$event2 = FlexibleEvent::from(['startTime' => '2024-12-25T10:30:00Z']);
$event3 = FlexibleEvent::from(['startTime' => Carbon::now()]);

// All serialize to: "2024-12-25T10:30:00Z"
```

#### Carbon Provider Configuration

Configure default Carbon behavior at the class level:

```php
<?php

use Ninja\Granite\Serialization\Attributes\DateTimeProvider;
use Carbon\Carbon;

#[DateTimeProvider(
    defaultTimezone: 'UTC',
    defaultFormat: 'Y-m-d H:i:s',
    parseFormats: ['Y-m-d H:i:s', 'Y-m-d\TH:i:s\Z', 'Y-m-d']
)]
final readonly class GlobalEvent extends Granite
{
    public function __construct(
        public string $title,
        public Carbon $startDate,  // Uses class defaults
        public Carbon $endDate,
        
        #[CarbonDate(format: 'd/m/Y')]  // Override for specific property
        public Carbon $publishDate
    ) {}
}
```

#### Carbon Timezone Handling

```php
final readonly class TimezoneEvent extends Granite
{
    public function __construct(
        public string $name,
        
        // Store in UTC, display in local timezone
        #[CarbonDate(format: 'c', timezone: 'UTC')]
        public Carbon $utcTime,
        
        // Preserve original timezone
        #[CarbonDate(format: 'c', preserveTimezone: true)]
        public Carbon $localTime,
        
        // Convert to specific timezone for serialization
        #[CarbonDate(format: 'Y-m-d H:i T', timezone: 'America/New_York')]
        public Carbon $easternTime
    ) {}
}

$event = TimezoneEvent::from([
    'name' => 'Global Meeting',
    'utcTime' => '2024-12-25 15:30:00+02:00',
    'localTime' => '2024-12-25 15:30:00+02:00', 
    'easternTime' => '2024-12-25 15:30:00+02:00'
]);

$array = $event->array();
// Result: {
//   'name' => 'Global Meeting',
//   'utcTime' => '2024-12-25T13:30:00+00:00',      // Converted to UTC
//   'localTime' => '2024-12-25T15:30:00+02:00',    // Original timezone preserved
//   'easternTime' => '2024-12-25 08:30 EST'        // Converted to Eastern time
// }
```

### Enum Serialization

Enums are automatically handled based on their type:

```php
<?php

// Backed enum (with values)
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}

// Unit enum (without values)
enum Priority {
    case LOW;
    case MEDIUM;
    case HIGH;
}

final readonly class Task extends Granite
{
    public function __construct(
        public string $title,
        public Status $status,
        public Priority $priority
    ) {}
}

$task = Task::from([
    'title' => 'Complete project',
    'status' => 'active',    // String value for backed enum
    'priority' => 'HIGH'     // Case name for unit enum
]);

$array = $task->array();
// Result: {
//   'title' => 'Complete project',
//   'status' => 'active',    // Backed enum uses value
//   'priority' => 'HIGH'     // Unit enum uses name
// }
```

## Nested Objects

Granite automatically handles nested objects:

```php
<?php

final readonly class Address extends Granite
{
    public function __construct(
        public string $street,
        public string $city,
        public string $state,
        
        #[SerializedName('zip_code')]
        public string $zipCode
    ) {}
}

final readonly class Company extends Granite
{
    public function __construct(
        public string $name,
        public Address $address,
        
        #[SerializedName('contact_email')]
        public string $email,
        
        #[Hidden]
        public ?string $taxId = null
    ) {}
}

$company = Company::from([
    'name' => 'Acme Corp',
    'address' => [
        'street' => '123 Main St',
        'city' => 'Anytown',
        'state' => 'CA',
        'zip_code' => '12345'
    ],
    'contact_email' => 'info@acme.com',
    'taxId' => 'TAX123456'
]);

$array = $company->array();
// Result: {
//   'name' => 'Acme Corp',
//   'address' => {
//     'street' => '123 Main St',
//     'city' => 'Anytown',
//     'state' => 'CA',
//     'zip_code' => '12345'
//   },
//   'contact_email' => 'info@acme.com'
//   // taxId is hidden
// }
```

### Arrays of Objects

```php
final readonly class Team extends Granite
{
    public function __construct(
        public string $name,
        
        /** @var User[] */
        public array $members,
        
        public Address $office
    ) {}
}

$team = Team::from([
    'name' => 'Development Team',
    'members' => [
        ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
    ],
    'office' => [
        'street' => '456 Tech Blvd',
        'city' => 'San Francisco',
        'state' => 'CA',
        'zip_code' => '94105'
    ]
]);

// members array will contain User objects
// office will be an Address object
$array = $team->array();
```

## Advanced Scenarios

### API Response DTOs

Create DTOs specifically designed for API responses:

```php
<?php

use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Serialization\Attributes\SerializationConvention;
use Ninja\Granite\Serialization\Attributes\Hidden;
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class UserProfileResponse extends Granite
{
    public function __construct(
        public int $id,                        // convention: "id" (no change)
        
        #[SerializedName('display_name')]       // explicit name overrides convention
        public string $name,
        
        public string $email,                   // convention: "email" (no change)
        
        public ?string $avatarUrl,              // convention: "avatar_url"
        
        #[SerializedName('member_since')]       // explicit name overrides convention
        public DateTime $createdAt,
        
        public ?DateTime $lastLoginAt,          // convention: "last_login_at"
        
        public bool $isProfileComplete,         // convention: "is_profile_complete"
        
        // Internal fields - hidden from API
        #[Hidden]
        public ?string $internalId = null,
        
        #[Hidden]
        public ?array $permissions = null
    ) {}

    /**
     * Create from user entity
     */
    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->getId(),
            name: $user->getFullName(),
            email: $user->getEmail(),
            avatarUrl: $user->getAvatarUrl(),
            createdAt: $user->getCreatedAt(),
            lastLoginAt: $user->getLastLoginAt(),
            isProfileComplete: $user->isProfileComplete(),
            internalId: $user->getInternalId(),
            permissions: $user->getPermissions()
        );
    }
}

// Usage
$user = $userRepository->find(123);
$response = UserProfileResponse::fromUser($user);

// Clean API response without sensitive data
$apiData = $response->array();
// {
//   "id": 123,
//   "display_name": "John Doe",           // explicit SerializedName
//   "email": "john@example.com",           // no change needed
//   "avatar_url": "https://example.com/avatars/123.jpg",  // convention applied
//   "member_since": "2023-01-15T10:30:00+00:00",         // explicit SerializedName
//   "last_login_at": "2024-01-10T15:45:00+00:00",        // convention applied
//   "is_profile_complete": true                           // convention applied
// }
```

### Database Entity Serialization

Handle database entities with computed properties:

```php
final readonly class ProductSummary extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public float $price,
        
        #[SerializedName('image_url')]
        public ?string $imageUrl,
        
        #[SerializedName('in_stock')]
        public bool $inStock,
        
        #[SerializedName('rating_average')]
        public ?float $averageRating,
        
        #[SerializedName('review_count')]
        public int $reviewCount,
        
        #[SerializedName('created_at')]
        public DateTime $createdAt,
        
        // Internal database fields
        #[Hidden]
        public ?int $categoryId = null,
        
        #[Hidden]
        public ?string $sku = null
    ) {}

    public static function fromEntity(Product $product): self
    {
        return new self(
            id: $product->getId(),
            name: $product->getName(),
            description: $product->getDescription(),
            price: $product->getPrice(),
            imageUrl: $product->getMainImageUrl(),
            inStock: $product->getStockQuantity() > 0,
            averageRating: $product->calculateAverageRating(),
            reviewCount: $product->getReviewCount(),
            createdAt: $product->getCreatedAt(),
            categoryId: $product->getCategoryId(),
            sku: $product->getSku()
        );
    }
}
```

### Configuration Objects

Create configuration objects that can be serialized to files:

```php
final readonly class AppConfig extends Granite
{
    public function __construct(
        #[SerializedName('app_name')]
        public string $applicationName,
        
        #[SerializedName('debug_mode')]
        public bool $debugEnabled,
        
        #[SerializedName('database')]
        public DatabaseConfig $databaseConfig,
        
        #[SerializedName('cache')]
        public CacheConfig $cacheConfig,
        
        #[SerializedName('api_keys')]
        public array $apiKeys,
        
        // Sensitive data - not serialized to config files
        #[Hidden]
        public ?string $encryptionKey = null
    ) {}

    public function saveToFile(string $path): void
    {
        $config = $this->array();
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));
    }

    public static function loadFromFile(string $path): self
    {
        $config = json_decode(file_get_contents($path), true);
        return self::from($config);
    }
}
```

## Error Handling

### Serialization Exceptions

Granite throws `SerializationException` when it encounters unsupported types:

```php
use Ninja\Granite\Exceptions\SerializationException;

final readonly class InvalidExample extends Granite
{
    public function __construct(
        public string $name,
        public resource $fileHandle  // This will cause SerializationException
    ) {}
}

try {
    $example = new InvalidExample('test', fopen('php://memory', 'r'));
    $array = $example->array();  // Throws SerializationException
} catch (SerializationException $e) {
    echo "Cannot serialize: " . $e->getMessage();
    echo "Property: " . $e->getPropertyName();
    echo "Object type: " . $e->getObjectType();
}
```

## Best Practices

### 1. Use Meaningful Serialized Names

```php
// Good: Clear API-friendly names
#[SerializedName('created_at')]
public DateTime $createdAt;

#[SerializedName('is_active')]
public bool $active;

// Avoid: Unclear abbreviations
#[SerializedName('ca')]
public DateTime $createdAt;
```

### 2. Always Hide Sensitive Data

```php
// Good: Explicit about what's hidden
#[Hidden]
public string $password;

#[Hidden]
public ?string $apiSecret;

#[Hidden]
public ?array $internalMetadata;

// Bad: Exposing sensitive data
public string $password;  // Will be serialized!
```

### 3. Use DTOs for Different Contexts

```php
// Internal entity
final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public array $permissions,
        public DateTime $createdAt
    ) {}
}

// Public API response
final readonly class PublicUser extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        #[SerializedName('member_since')]
        public DateTime $createdAt
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            createdAt: $user->createdAt
        );
    }
}

// Admin response
final readonly class AdminUser extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $permissions,
        #[SerializedName('created_at')]
        public DateTime $createdAt,
        // Still hide password hash
        #[Hidden]
        public ?string $passwordHash = null
    ) {}
}
```

### 4. Document Serialization Behavior

```php
/**
 * User profile data for API responses.
 * 
 * Serialization behavior:
 * - firstName/lastName are combined into display_name
 * - email is included for authenticated users only
 * - Internal IDs and permissions are hidden
 * - Dates are in ISO 8601 format
 */
final readonly class UserProfile extends Granite
{
    // ... implementation
}
```

This serialization system gives you complete control over how your objects are represented in different contexts while maintaining type safety and clear data contracts.