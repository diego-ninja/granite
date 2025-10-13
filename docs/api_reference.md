# API Reference

Complete API reference for Granite classes, methods, and attributes.

## Table of Contents

- [Core Classes](#core-classes)
- [Validation Attributes](#validation-attributes)
- [Validation Rules](#validation-rules)
- [Serialization Attributes](#serialization-attributes)
- [Mapping Attributes](#mapping-attributes)
- [AutoMapper Classes](#automapper-classes)
- [Exception Classes](#exception-classes)
- [Utility Classes](#utility-classes)

## Core Classes

### GraniteDTO

Base class for immutable Data Transfer Objects.

```php
abstract readonly class GraniteDTO implements GraniteObject
```

#### Static Methods

##### `from(mixed ...$args): static` ✨ ENHANCED
Creates a new instance from various data sources with multiple invocation patterns.

**Parameters:**
- `...$args` - Variable arguments supporting multiple patterns:
  - Array data: `from(['key' => 'value'])`
  - JSON string: `from('{"key": "value"}')`
  - Granite object: `from($graniteObject)`
  - Named parameters: `from(key: 'value', another: 'value')`
  - Mixed usage: `from($baseData, key: 'override')`

**Returns:** New instance of the DTO

**Throws:**
- `ValidationException` - If validation fails (GraniteVO only)
- `SerializationException` - If deserialization fails
- `InvalidArgumentException` - If invalid JSON provided

```php
// Array data
$user = UserDTO::from([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// JSON string
$user = UserDTO::from('{"id":1,"name":"John Doe","email":"john@example.com"}');

// Named parameters (NEW!)
$user = UserDTO::from(
    id: 1,
    name: 'John Doe',
    email: 'john@example.com'
);

// Mixed usage (NEW!)
$defaults = ['name' => 'Default', 'email' => 'default@example.com'];
$user = UserDTO::from($defaults, id: 1, name: 'John Doe');

// From another Granite object
$user = UserDTO::from($otherUser);

// Empty object (partial initialization)
$user = UserDTO::from();
```

#### Instance Methods

##### `array(): array`
Converts the object to an array.

**Returns:** Array representation with serialization rules applied

```php
$array = $user->array();
// ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com']
```

##### `json(): string`
Converts the object to JSON.

**Returns:** JSON string representation

```php
$json = $user->json();
// '{"id":1,"name":"John Doe","email":"john@example.com"}'
```

#### Protected Methods

##### `serializedNames(): array`
Override to define custom property names for serialization.

**Returns:** Array mapping PHP property names to serialized names

```php
protected static function serializedNames(): array
{
    return [
        'createdAt' => 'created_at',
        'firstName' => 'first_name'
    ];
}
```

##### `hiddenProperties(): array`
Override to define properties that should be hidden during serialization.

**Returns:** Array of property names to hide

```php
protected static function hiddenProperties(): array
{
    return ['password', 'apiKey'];
}
```

---

### GraniteVO

Base class for immutable Value Objects with validation.

```php
abstract readonly class GraniteVO extends GraniteDTO
```

Inherits all methods from `GraniteDTO` and adds validation capabilities.

#### Additional Methods

##### `equals(Granite $other): bool` ✨ UPDATED
Compares this Granite object with another for deep equality.

**Parameters:**
- `$other` - Another Granite object to compare against (must be same type)

**Returns:** `true` if all initialized public properties are equal, `false` otherwise

**Throws:**
- `ReflectionException` - If reflection operation fails

**Comparison behavior:**
- Only compares **public properties**
- Skips uninitialized properties
- Performs **deep comparison** of nested Granite objects
- Compares arrays **recursively** without JSON encoding
- DateTime comparison includes **timezone** check
- Enums compared by value (BackedEnum) or name (UnitEnum)
- Objects of different types always return `false`

```php
$user1 = User::from(['name' => 'John', 'email' => 'john@example.com']);
$user2 = User::from(['name' => 'John', 'email' => 'john@example.com']);
$user3 = User::from(['name' => 'Jane', 'email' => 'jane@example.com']);

$user1->equals($user2); // true - all properties match
$user1->equals($user3); // false - different values

// Works with nested objects
$post1 = Post::from(['title' => 'My Post', 'author' => $user1]);
$post2 = Post::from(['title' => 'My Post', 'author' => $user2]);
$post1->equals($post2); // true - nested author is also compared

// DateTime with timezone awareness
$event1 = Event::from([
    'name' => 'Conference',
    'date' => new DateTime('2024-01-15 10:00:00', new DateTimeZone('UTC'))
]);
$event2 = Event::from([
    'name' => 'Conference',
    'date' => new DateTime('2024-01-15 11:00:00', new DateTimeZone('Europe/Madrid'))
]);
$event1->equals($event2); // false - different timezone
```

---

##### `differs(Granite $other): array` ✨ NEW
Returns detailed differences between this object and another.

**Parameters:**
- `$other` - Another Granite object to compare against (must be same type)

**Returns:**
- Empty array if objects are equal
- Associative array with differences in format:
  ```php
  [
      'property_name' => [
          'current' => <current_value>,
          'new' => <new_value>
      ]
  ]
  ```
- For nested Granite objects, returns hierarchical differences

**Throws:**
- `ComparisonException` - If objects are of different types
- `ReflectionException` - If reflection operation fails
- `SerializationException` - If value cannot be serialized for comparison

**Value formatting:**
- `null` → `null`
- Scalars → unchanged
- `DateTime` → formatted with microseconds and timezone (e.g., `'2024-01-15 10:30:45.123456 +00:00'`)
- Enums → scalar value (BackedEnum) or name (UnitEnum)
- Granite objects → recursive array representation
- Arrays → recursive value conversion
- Other objects → `__toString()` if available, otherwise class name

```php
$user1 = User::from(['id' => 1, 'name' => 'John', 'email' => 'john@example.com']);
$user2 = User::from(['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com']);

$differences = $user1->differs($user2);
// [
//     'name' => ['current' => 'John', 'new' => 'Jane'],
//     'email' => ['current' => 'john@example.com', 'new' => 'jane@example.com']
// ]

// Nested differences
$company1 = Company::from([
    'name' => 'Acme Inc',
    'address' => ['street' => '123 Main St', 'city' => 'New York']
]);
$company2 = Company::from([
    'name' => 'Acme Inc',
    'address' => ['street' => '456 Oak Ave', 'city' => 'New York']
]);

$differences = $company1->differs($company2);
// [
//     'address' => [
//         'street' => ['current' => '123 Main St', 'new' => '456 Oak Ave']
//     ]
// ]

// Type mismatch throws exception
try {
    $user->differs($admin); // Different classes
} catch (ComparisonException $e) {
    echo $e->getMessage();
    // "Cannot compare objects of different types: expected User, got Admin"
}
```

##### `with(array $modifications): static`
Creates a new instance with some properties modified.

**Parameters:**
- `$modifications` - Array of property names and new values

**Returns:** New instance with modifications applied

**Throws:**
- `ValidationException` - If the modified data fails validation

```php
$updatedUser = $user->with(['name' => 'Jane Doe']);
```

#### Protected Methods

##### `rules(): array`
Override to define validation rules using method-based configuration.

**Returns:** Array of validation rules by property name

```php
protected static function rules(): array
{
    return [
        'email' => 'required|email',
        'age' => 'integer|min:18|max:120'
    ];
}
```

## Validation Attributes

### Basic Validation

#### `#[Required(?string $message = null)]`
Ensures the field is not null.

```php
#[Required]
public string $name;

#[Required('Name is mandatory')]
public string $name;
```

#### `#[StringType(?string $message = null)]`
Validates that the value is a string.

```php
#[StringType]
public ?string $description;

#[StringType('Must be text')]
public ?string $description;
```

#### `#[IntegerType(?string $message = null)]`
Validates that the value is an integer.

```php
#[IntegerType]
public ?int $count;
```

#### `#[NumberType(?string $message = null)]`
Validates that the value is a number (int or float).

```php
#[NumberType]
public ?float $price;
```

#### `#[BooleanType(?string $message = null)]`
Validates that the value is a boolean.

```php
#[BooleanType]
public ?bool $active;
```

#### `#[ArrayType(?string $message = null)]`
Validates that the value is an array.

```php
#[ArrayType]
public ?array $tags;
```

### Range and Length Validation

#### `#[Min(int|float $min, ?string $message = null)]`
Sets minimum value for numbers or minimum length for strings/arrays.

```php
#[Min(1)]
public int $quantity;

#[Min(3, 'Name too short')]
public string $username;
```

#### `#[Max(int|float $max, ?string $message = null)]`
Sets maximum value for numbers or maximum length for strings/arrays.

```php
#[Max(100)]
public int $percentage;

#[Max(255, 'Title too long')]
public string $title;
```

### Format Validation

#### `#[Email(?string $message = null)]`
Validates email address format.

```php
#[Email]
public string $email;

#[Email('Invalid email format')]
public string $email;
```

#### `#[Url(?string $message = null)]`
Validates URL format.

```php
#[Url]
public string $website;
```

#### `#[IpAddress(?string $message = null)]`
Validates IP address format.

```php
#[IpAddress]
public string $serverIp;
```

#### `#[Regex(string $pattern, ?string $message = null)]`
Validates against a regular expression pattern.

```php
#[Regex('/^[A-Z]{2,3}-\d{4}$/')]
public string $productCode;

#[Regex('/^\d{5}$/', 'Invalid ZIP code')]
public string $zipCode;
```

### Choice Validation

#### `#[In(array $values, ?string $message = null)]`
Validates that the value is in a list of allowed values.

```php
#[In(['active', 'inactive', 'pending'])]
public string $status;

#[In([1, 2, 3, 4, 5], 'Rating must be 1-5')]
public int $rating;
```

#### `#[EnumType(?string $enumClass = null, ?string $message = null)]`
Validates enum values.

```php
#[EnumType(Status::class)]
public Status $status;

#[EnumType] // Auto-detect enum type
public Status $status;
```

### Advanced Validation

#### `#[Each(ValidationRule|ValidationRule[] $rules, ?string $message = null)]`
Validates each item in an array.

```php
use Ninja\Granite\Validation\Rules\Email;

#[Each(new Email())]
public array $emails;

#[Each([new StringType(), new Min(3)])]
public array $names;
```

#### `#[When(callable $condition, ValidationRule $rule, ?string $message = null)]`
Applies validation only when a condition is met.

```php
#[When(
    condition: fn($value, $data) => $data['type'] === 'premium',
    rule: new Min(100)
)]
public ?float $premiumAmount;
```

#### `#[Callback(callable $callback, ?string $message = null)]`
Uses a custom callback for validation.

```php
#[Callback(
    callback: function($value) {
        return $value !== null && $value % 2 === 0;
    },
    message: 'Value must be an even number'
)]
public ?int $evenNumber;
```

## Validation Rules

All validation rules extend `AbstractRule` and implement `ValidationRule`.

### AbstractRule

Base class for all validation rules.

```php
abstract class AbstractRule implements ValidationRule
```

#### Methods

##### `withMessage(string $message): static`
Sets a custom error message for the rule.

```php
$rule = new Required();
$rule->withMessage('This field is mandatory');
```

##### `message(string $property): string`
Gets the error message for this rule.

##### `validate(mixed $value, ?array $allData = null): bool`
Abstract method that performs the validation.

### Built-in Rules

#### Basic Type Rules
- `Required` - Value must not be null
- `StringType` - Value must be a string
- `IntegerType` - Value must be an integer
- `NumberType` - Value must be a number
- `BooleanType` - Value must be a boolean
- `ArrayType` - Value must be an array

#### Range Rules
- `Min(int|float $min)` - Minimum value/length
- `Max(int|float $max)` - Maximum value/length

#### Format Rules
- `Email` - Valid email format
- `Url` - Valid URL format
- `IpAddress` - Valid IP address
- `Regex(string $pattern)` - Match regex pattern

#### Choice Rules
- `In(array $values)` - Value in allowed list
- `EnumType(?string $enumClass)` - Valid enum value

#### Advanced Rules
- `Each(ValidationRule|array $rules)` - Validate array items
- `When(callable $condition, ValidationRule $rule)` - Conditional validation
- `Callback(callable $callback)` - Custom validation function

### 📅 Carbon Date Validation Rules ✨ NEW

#### `Age(int $min, int $max, ?string $message = null)`
Validates that a Carbon date represents an age within the specified range.

```php
use Ninja\Granite\Validation\Rules\Carbon\Age;

#[Age(min: 18, max: 65)]
public Carbon $birthDate;

#[Age(min: 21, message: 'Must be at least 21 years old')]
public Carbon $dateOfBirth;
```

#### `BusinessDay(?string $message = null)`
Validates that a Carbon date falls on a business day (Monday-Friday).

```php
use Ninja\Granite\Validation\Rules\Carbon\BusinessDay;

#[BusinessDay]
public Carbon $meetingDate;

#[BusinessDay(message: 'Events can only be scheduled on business days')]
public Carbon $eventDate;
```

#### `Future(?string $message = null)`
Validates that a Carbon date is in the future.

```php
use Ninja\Granite\Validation\Rules\Carbon\Future;

#[Future]
public Carbon $eventDate;

#[Future(message: 'Event must be scheduled for a future date')]
public Carbon $scheduledAt;
```

#### `Past(?string $message = null)`
Validates that a Carbon date is in the past.

```php
use Ninja\Granite\Validation\Rules\Carbon\Past;

#[Past]
public Carbon $birthDate;

#[Past(message: 'Birth date must be in the past')]
public Carbon $dateOfBirth;
```

#### `Range(string $min, string $max, ?string $message = null)`
Validates that a Carbon date falls within the specified range.

```php
use Ninja\Granite\Validation\Rules\Carbon\Range;

#[Range(min: 'now', max: '+1 year')]
public Carbon $eventDate;

#[Range(min: '2024-01-01', max: '2024-12-31')]
public Carbon $fiscalDate;

#[Range(min: 'today', max: 'next month', message: 'Date must be within the next month')]
public Carbon $deadline;
```

#### `Weekend(?string $message = null)`
Validates that a Carbon date falls on a weekend (Saturday or Sunday).

```php
use Ninja\Granite\Validation\Rules\Carbon\Weekend;

#[Weekend]
public Carbon $leisureDate;

#[Weekend(message: 'Personal events must be scheduled on weekends')]
public Carbon $personalEvent;
```

#### Usage Examples

```php
<?php

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Rules\Carbon\Age;
use Ninja\Granite\Validation\Rules\Carbon\BusinessDay;
use Ninja\Granite\Validation\Rules\Carbon\Future;
use Ninja\Granite\Validation\Rules\Carbon\Range;
use Carbon\Carbon;

final readonly class EventRegistration extends GraniteVO
{
    public function __construct(
        public string $name,
        
        #[Age(min: 18, max: 99, message: 'Participant must be between 18 and 99 years old')]
        public Carbon $birthDate,
        
        #[Future(message: 'Event must be scheduled for the future')]
        #[BusinessDay(message: 'Events can only be on business days')]
        public Carbon $eventDate,
        
        #[Range(min: 'now', max: '+6 months', message: 'Registration must be within 6 months')]
        public Carbon $registrationDeadline
    ) {}
}

// Valid registration
$registration = EventRegistration::from(
    name: 'John Doe',
    birthDate: '1990-05-15',           // Age validation passes
    eventDate: 'next Monday at 9am',   // Future + BusinessDay validation passes
    registrationDeadline: '+3 months'  // Range validation passes
);

// Invalid registration - throws ValidationException
try {
    $invalid = EventRegistration::from(
        name: 'Jane Doe',
        birthDate: '2010-01-01',       // Age validation fails (too young)
        eventDate: 'next Saturday',    // BusinessDay validation fails
        registrationDeadline: '+1 year' // Range validation fails (too far)
    );
} catch (ValidationException $e) {
    foreach ($e->getErrors() as $property => $errors) {
        echo "$property: " . implode(', ', $errors) . "\n";
    }
}
```

## Serialization Attributes

### `#[SerializedName(string $name)]`
Specifies a custom name for a property when serialized.

```php
#[SerializedName('full_name')]
public string $name;

#[SerializedName('email_address')]
public string $email;
```

### `#[Hidden]`
Hides a property during serialization.

```php
#[Hidden]
public string $password;

#[SerializedName('api_key')]
#[Hidden]
public ?string $apiKey;
```

### `#[SerializationConvention(string|object $convention)]`
Applies a naming convention to all properties in a class.

```php
use Ninja\Granite\Mapping\Conventions\SnakeCaseConvention;

#[SerializationConvention(SnakeCaseConvention::class)]
final readonly class User extends GraniteDTO
{
    public function __construct(
        public string $firstName,    // serialized as "first_name"
        public string $lastName,     // serialized as "last_name"
        public string $emailAddress  // serialized as "email_address"
    ) {}
}
```

### 📅 Carbon Date Attributes

### `#[CarbonDate(string $format, ?string $timezone = null, bool $preserveTimezone = false)]` ✨ NEW
Controls Carbon date serialization format and timezone handling.

**Parameters:**
- `$format` - PHP date format string (e.g., 'Y-m-d H:i:s', 'c', 'd/m/Y')
- `$timezone` - Target timezone for serialization (e.g., 'UTC', 'America/New_York')
- `$preserveTimezone` - Whether to preserve the original timezone

```php
use Carbon\Carbon;

#[CarbonDate(format: 'Y-m-d H:i:s')]
public Carbon $createdAt;

#[CarbonDate(format: 'c', timezone: 'UTC')]
public Carbon $publishedAt;

#[CarbonDate(format: 'd/m/Y')]
public Carbon $eventDate;

#[CarbonDate(format: 'Y-m-d H:i T', timezone: 'America/New_York')]
public Carbon $easternTime;
```

### `#[CarbonRelative]` ✨ NEW
Enables relative date parsing for Carbon properties.

```php
#[CarbonRelative]
public ?Carbon $dueDate;

// Accepts: 'tomorrow', 'next week', '2 hours ago', 'first day of next month'
$task = Task::from(['dueDate' => 'next Friday at 5pm']);
```

### `#[CarbonRange(string $min, string $max, ?string $message = null)]` ✨ NEW
Validates that Carbon dates fall within a specified range.

**Parameters:**
- `$min` - Minimum date (Carbon parseable string like 'now', '2024-01-01')
- `$max` - Maximum date (Carbon parseable string like '+1 year', '2024-12-31')
- `$message` - Custom validation message

```php
#[CarbonRange(min: 'now', max: '+1 year')]
public Carbon $eventDate;

#[CarbonRange(min: '2024-01-01', max: '2024-12-31', message: 'Date must be within 2024')]
public Carbon $fiscalDate;
```

### `#[DateTimeProvider(string $defaultTimezone, string $defaultFormat, array $parseFormats)]` ✨ NEW
Configures default Carbon behavior at the class level.

**Parameters:**
- `$defaultTimezone` - Default timezone for all Carbon properties
- `$defaultFormat` - Default serialization format
- `$parseFormats` - Array of acceptable input formats for parsing

```php
#[DateTimeProvider(
    defaultTimezone: 'UTC',
    defaultFormat: 'Y-m-d H:i:s',
    parseFormats: ['Y-m-d H:i:s', 'Y-m-d\TH:i:s\Z', 'Y-m-d']
)]
final readonly class Event extends GraniteDTO
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

## Mapping Attributes

### `#[MapFrom(string $source)]`
Specifies the source property name for mapping.

```php
#[MapFrom('userId')]
public int $id;

#[MapFrom('fullName')]
public string $name;
```

### `#[MapWith(mixed $transformer)]`
Specifies a transformer for the property.

```php
#[MapWith(new FullNameTransformer())]
public string $fullName;

#[MapWith([DateTransformers::class, 'toAge'])]
public int $age;
```

### `#[MapWhen(mixed $condition)]`
Applies a condition to property mapping.

```php
#[MapWhen(fn($data) => $data['isAdmin'])]
public ?string $adminData;
```

### `#[MapDefault(mixed $value)]`
Specifies a default value for mapping.

```php
#[MapDefault('active')]
public string $status;

#[MapDefault([])]
public array $tags;
```

### `#[MapCollection(string $destinationType, bool $preserveKeys = false, bool $recursive = false, mixed $itemTransformer = null)]`
Configures collection mapping.

```php
#[MapCollection(UserResponse::class)]
public array $users;

#[MapCollection(TaskResponse::class, preserveKeys: true)]
public array $tasks;
```

### `#[Ignore]`
Ignores the property during mapping.

```php
#[Ignore]
public ?string $internalData;
```

## AutoMapper Classes

### AutoMapper

Main mapper class for object-to-object mapping.

```php
class AutoMapper implements Mapper, MappingStorage
```

#### Constructor

```php
public function __construct(
    array $profiles = [],
    CacheType $cacheType = CacheType::Shared,
    bool $warmupCache = true,
    bool $useConventions = true
)
```

#### Mapping Methods

##### `map(mixed $source, string $destinationType): object`
Maps from source to destination type.

```php
$userDto = $mapper->map($userEntity, UserDto::class);
```

##### `mapTo(mixed $source, object $destination): object`
Maps to an existing destination object.

```php
$updatedUser = $mapper->mapTo($sourceData, $existingUser);
```

##### `mapArray(array $source, string $destinationType): array`
Maps an array of objects.

```php
$userDtos = $mapper->mapArray($userEntities, UserDto::class);
```

#### Configuration Methods

##### `createMap(string $sourceType, string $destinationType): TypeMapping`
Creates a new mapping configuration.

```php
$mapping = $mapper->createMap(UserEntity::class, UserDto::class)
    ->forMember('id', fn($m) => $m->mapFrom('userId'))
    ->seal();
```

##### `createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping`
Creates bidirectional mappings.

```php
$mapping = $mapper->createMapBidirectional(UserEntity::class, UserDto::class)
    ->forMembers('userId', 'id')
    ->seal();
```

##### `addProfile(MappingProfile $profile): self`
Adds a mapping profile.

```php
$mapper->addProfile(new UserMappingProfile());
```

#### Convention Methods

##### `useConventions(bool $useConventions = true): self`
Enables/disables convention-based mapping.

```php
$mapper->useConventions(true);
```

##### `setConventionConfidenceThreshold(float $threshold): self`
Sets the confidence threshold for convention matching.

```php
$mapper->setConventionConfidenceThreshold(0.8);
```

##### `registerConvention(NamingConvention $convention): self`
Registers a custom naming convention.

```php
$mapper->registerConvention(new CustomConvention());
```

#### Cache Methods

##### `clearCache(): self`
Clears the mapping cache.

```php
$mapper->clearCache();
```

##### `getCache(): MappingCache`
Gets the current cache instance.

```php
$cache = $mapper->getCache();
```

### TypeMapping

Configures mapping between two specific types.

```php
class TypeMapping
```

#### Methods

##### `forMember(string $destinationProperty, callable $configuration): self`
Configures mapping for a specific property.

```php
$mapping->forMember('fullName', fn($m) => 
    $m->mapFrom('name')->using($transformer)
);
```

##### `seal(): self`
Validates and finalizes the mapping configuration.

```php
$mapping->seal();
```

### PropertyMapping

Configures mapping for a single property.

```php
class PropertyMapping
```

#### Methods

##### `mapFrom(string $sourceProperty): self`
Specifies the source property.

```php
$mapping->mapFrom('userId');
```

##### `using(callable|Transformer $transformer): self`
Specifies a transformer.

```php
$mapping->using(function($value) {
    return strtoupper($value);
});
```

##### `ignore(): self`
Ignores the property during mapping.

```php
$mapping->ignore();
```

##### `onlyIf(callable $condition): self`
Adds a condition for mapping.

```php
$mapping->onlyIf(fn($data) => $data['isActive']);
```

##### `defaultValue(mixed $value): self`
Sets a default value.

```php
$mapping->defaultValue('unknown');
```

### MappingProfile

Base class for grouping related mappings.

```php
abstract class MappingProfile implements MappingStorage
```

#### Methods

##### `configure(): void`
Abstract method to configure mappings.

```php
protected function configure(): void
{
    $this->createMap(UserEntity::class, UserDto::class)
        ->forMember('id', fn($m) => $m->mapFrom('userId'))
        ->seal();
}
```

##### `createMap(string $sourceType, string $destinationType): TypeMapping`
Creates a new type mapping.

##### `createMapBidirectional(string $typeA, string $typeB): BidirectionalTypeMapping`
Creates bidirectional mappings.

## Exception Classes

### ValidationException

Thrown when validation fails.

```php
class ValidationException extends GraniteException
```

#### Methods

##### `getErrors(): array`
Gets validation errors by field.

```php
$errors = $e->getErrors();
// ['name' => ['Name is required'], 'email' => ['Invalid email']]
```

##### `getFieldErrors(string $field): array`
Gets errors for a specific field.

```php
$nameErrors = $e->getFieldErrors('name');
```

##### `hasFieldErrors(string $field): bool`
Checks if a field has errors.

```php
if ($e->hasFieldErrors('email')) {
    // Handle email errors
}
```

##### `getAllMessages(): array`
Gets all error messages as flat array.

```php
$messages = $e->getAllMessages();
// ['Name is required', 'Invalid email']
```

##### `getFormattedMessage(): string`
Gets formatted error message for display.

```php
echo $e->getFormattedMessage();
```

### SerializationException

Thrown when serialization/deserialization fails.

```php
class SerializationException extends GraniteException
```

#### Methods

##### `getObjectType(): string`
Gets the object type that failed.

##### `getOperation(): string`
Gets the operation that failed (serialization/deserialization).

##### `getPropertyName(): ?string`
Gets the property name that caused the error.

### MappingException

Thrown when AutoMapper operations fail.

```php
class MappingException extends GraniteException
```

#### Methods

##### `getSourceType(): string`
Gets the source type.

##### `getDestinationType(): string`
Gets the destination type.

##### `getPropertyName(): ?string`
Gets the property name that caused the error.

### ReflectionException

Thrown when reflection operations fail.

```php
class ReflectionException extends GraniteException
```

#### Methods

##### `getClassName(): string`
Gets the class name that caused the error.

##### `getOperation(): string`
Gets the reflection operation that failed.

---

### ComparisonException ✨ NEW

Thrown when object comparison operations fail.

```php
class ComparisonException extends GraniteException
```

#### Static Factory Methods

##### `typeMismatch(string $expectedType, string $actualType): self`
Creates exception for comparing objects of different types.

**Parameters:**
- `$expectedType` - The expected class name
- `$actualType` - The actual class name provided

**Returns:** New `ComparisonException` instance

```php
try {
    $user = User::from(['id' => 1, 'name' => 'John']);
    $admin = Admin::from(['id' => 1, 'name' => 'John']);

    $user->differs($admin);
} catch (ComparisonException $e) {
    echo $e->getMessage();
    // "Cannot compare objects of different types: expected User, got Admin"

    $context = $e->getContext();
    // ['expected_type' => 'User', 'actual_type' => 'Admin']
}
```

##### `uncomparableValue(string $propertyName, mixed $value): self`
Creates exception for values that cannot be compared.

**Parameters:**
- `$propertyName` - The property name
- `$value` - The uncomparable value

**Returns:** New `ComparisonException` instance

```php
try {
    // Attempting to compare with an uncomparable resource
    $obj->differs($otherObj);
} catch (ComparisonException $e) {
    echo $e->getMessage();
    // "Property 'handle' contains uncomparable value of type 'resource'"

    $context = $e->getContext();
    // ['property_name' => 'handle', 'value_type' => 'resource']
}
```

## Utility Classes

### ReflectionCache

Caches reflection objects for improved performance.

```php
final class ReflectionCache
```

#### Static Methods

##### `getClass(string $class): ReflectionClass`
Gets a cached ReflectionClass instance.

```php
$reflection = ReflectionCache::getClass(User::class);
```

##### `getPublicProperties(string $class): ReflectionProperty[]`
Gets cached public properties for a class.

```php
$properties = ReflectionCache::getPublicProperties(User::class);
```

### RuleExtractor

Extracts validation rules from property attributes.

```php
class RuleExtractor
```

#### Static Methods

##### `extractRules(string $class): array`
Extracts validation rules from a class.

```php
$rules = RuleExtractor::extractRules(User::class);
// ['name' => [RequiredRule, StringTypeRule], 'email' => [EmailRule]]
```

### GraniteValidator

Main validator class for validating data against rules.

```php
final class GraniteValidator
```

#### Constructor

```php
public function __construct(RuleCollection|array $collections = [])
```

#### Methods

##### `addRule(string $property, ValidationRule $rule): self`
Adds a single validation rule for a property.

```php
$validator->addRule('email', new Email());
```

##### `validate(array $data, string $objectName = 'Object'): void`
Validates data against all rules.

**Throws:** `ValidationException` if validation fails

```php
try {
    $validator->validate($data, 'User');
} catch (ValidationException $e) {
    // Handle validation errors
}
```

##### `fromArray(array $rulesArray): self`
Creates a validator from array of rule definitions.

```php
$validator = GraniteValidator::fromArray([
    'name' => 'required|string|min:2',
    'email' => 'required|email'
]);
```

This API reference provides complete documentation for all public classes and methods in Granite. Use it as a quick reference when working with the library.