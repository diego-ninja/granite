# Convention Mapping in Granite AutoMapper

The convention mapping system automates the mapping between properties with different naming styles, reducing the need for manual configuration.

## Key Features

- Automatic detection of matches between properties with different naming styles
- Support for multiple naming conventions (camelCase, snake_case, PascalCase, etc.)
- Extensible with custom conventions
- Configurable through confidence thresholds
- Transparent integration with the existing mapping system

## Included Conventions

The system includes support for the following naming conventions:

- **CamelCase**: `firstName`, `lastName`, `emailAddress`
- **PascalCase**: `FirstName`, `LastName`, `EmailAddress`
- **snake_case**: `first_name`, `last_name`, `email_address`
- **kebab-case**: `first-name`, `last-name`, `email-address`
- **Prefixes**: Mapping properties with common prefixes (`getUserName` → `userName`)
- **Abbreviations**: Expansion of common abbreviations (`dob` → `dateOfBirth`, `addr` → `address`)
- **Hungarian Notation**: Type prefixes like `strName`, `nCount`, `bIsActive`

## Basic Usage

```php
// Create an AutoMapper instance with convention mapping enabled
$mapper = new AutoMapper([], 'memory', true, true);

// Apply conventions between two types
$mapper->applyConventions(UserDto::class, UserEntity::class);

// Perform the mapping
$userEntity = $mapper->map($userDto, UserEntity::class);
```

## Configuration

### Enable/disable convention mapping

```php
// Enable conventions
$mapper->useConventions(true);

// Disable conventions
$mapper->useConventions(false);
```

### Adjust the confidence threshold

```php
// Set a stricter confidence threshold (0.0-1.0)
$mapper->setConventionConfidenceThreshold(0.9);
```

### Register custom conventions

```php
// Register a custom convention
$mapper->registerConvention(new MyCustomConvention());
```

## Creating Custom Conventions

To create a custom convention, implement the `NamingConvention` interface:

```php
class MyCustomConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'my-convention';
    }
    
    public function matches(string $name): bool
    {
        // Determine if a name follows this convention
    }
    
    public function normalize(string $name): string
    {
        // Convert a name from this convention to the normalized form
    }
    
    public function denormalize(string $normalized): string
    {
        // Convert a normalized name to this convention
    }
    
    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        // Calculate the confidence that two names represent the same property
    }
}
```

## Behavior with Explicit Mappings

Explicit mappings defined using the `createMap()` and `forMember()` methods always take precedence over convention-based mappings.

## Performance

The convention mapping system is optimized to minimize performance impact:

1. Convention detection results are cached
2. Convention mapping is only applied when there is no explicit mapping
3. Conventions use fast methods for detection and matching
