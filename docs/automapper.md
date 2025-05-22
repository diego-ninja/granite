# AutoMapper

Granite includes a powerful AutoMapper that allows you to automatically map data between different object structures. This is particularly useful when converting between DTOs, entities, API responses, and other data models.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Mapping with Attributes](#mapping-with-attributes)
- [Mapping Profiles](#mapping-profiles)
- [Custom Transformers](#custom-transformers)
- [Nested Property Mapping](#nested-property-mapping)
- [Built-in Transformers](#built-in-transformers)
- [Mapping to Existing Objects](#mapping-to-existing-objects)
- [Advanced Features](#advanced-features)
- [Performance Considerations](#performance-considerations)

## Basic Usage

```php
<?php

use Ninja\Granite\Mapping\AutoMapper;

// Source and destination objects
$userEntity = UserEntity::from([
    'id' => 1,
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'john@example.com'
]);

$mapper = new AutoMapper();

// Simple mapping (properties with same names are mapped automatically)
$userDto = $mapper->map($userEntity, UserDTO::class);

// Map arrays
$userDtos = $mapper->mapArray($userEntities, UserDTO::class);
```

## Mapping with Attributes

You can control mapping behavior using attributes on destination properties:

```php
<?php

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\MapWith;
use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Transformers\DateTimeTransformer;

final readonly class UserResponse extends GraniteDTO
{
    public function __construct(
        public int $id,
        
        #[MapFrom('firstName')]
        public string $name,
        
        #[MapFrom('lastName')]  
        public string $surname,
        
        public string $email,
        
        #[MapWith(new DateTimeTransformer('Y-m-d'))]
        #[MapFrom('createdAt')]
        public string $joinDate,
        
        #[Ignore]
        public array $internalData = []
    ) {}
}
```

### Available Mapping Attributes

- **`#[MapFrom('sourceProperty')]`** - Maps from a different source property name
- **`#[MapWith(transformer)]`** - Applies a transformation to the value
- **`#[Ignore]`** - Excludes the property from mapping

## Mapping Profiles

For complex mapping scenarios, you can create mapping profiles:

```php
<?php

use Ninja\Granite\Mapping\MappingProfile;

class UserMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(UserEntity::class, UserResponse::class)
            ->forMember('name', fn($mapping) => 
                $mapping->mapFrom('firstName')
            )
            ->forMember('surname', fn($mapping) => 
                $mapping->mapFrom('lastName')
            )
            ->forMember('fullName', fn($mapping) => 
                $mapping->using(fn($value, $source) => 
                    $source['firstName'] . ' ' . $source['lastName']
                )
            )
            ->forMember('joinDate', fn($mapping) => 
                $mapping->mapFrom('createdAt')
                        ->using(fn($value) => date('Y-m-d', strtotime($value)))
            );
    }
}

// Use the profile
$mapper = new AutoMapper([new UserMappingProfile()]);
$userResponse = $mapper->map($userEntity, UserResponse::class);
```

### Profile Configuration Methods

- **`mapFrom(string $sourceProperty)`** - Specify source property name
- **`using(callable|Transformer $transformer)`** - Apply transformation
- **`ignore()`** - Skip property during mapping

## Custom Transformers

Create reusable transformers for common conversion logic:

```php
<?php

use Ninja\Granite\Mapping\Transformer;

class FullNameTransformer implements Transformer
{
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        return ($sourceData['firstName'] ?? '') . ' ' . ($sourceData['lastName'] ?? '');
    }
}

class PriceFormatterTransformer implements Transformer
{
    public function __construct(private string $currency = 'USD') {}
    
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        return $this->currency . ' ' . number_format($value, 2);
    }
}

// Use in mapping profile
$mapping->using(new FullNameTransformer())

// Or use closures for simple transformations
$mapping->using(fn($value) => strtoupper($value))
```

## Nested Property Mapping

Support for dot notation to access nested properties:

```php
<?php

class OrderMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(OrderEntity::class, OrderSummary::class)
            ->forMember('customerName', fn($mapping) => 
                $mapping->mapFrom('customer.firstName')
            )
            ->forMember('customerEmail', fn($mapping) => 
                $mapping->mapFrom('customer.contactInfo.email')
            )
            ->forMember('shippingCity', fn($mapping) => 
                $mapping->mapFrom('shipping.address.city')
            )
            ->forMember('totalWithTax', fn($mapping) => 
                $mapping->mapFrom('pricing.total')
                        ->using(fn($value, $source) => 
                            $value + ($source['pricing']['tax'] ?? 0)
                        )
            );
    }
}
```

## Built-in Transformers

Granite provides several built-in transformers:

### DateTimeTransformer

Handles conversion between DateTime objects and strings:

```php
<?php

use Ninja\Granite\Mapping\Transformers\DateTimeTransformer;

// Transform DateTime to custom string format
#[MapWith(new DateTimeTransformer('Y-m-d H:i:s'))]
public string $createdAt;

// Transform string to DateTime (uses default ATOM format)
#[MapWith(new DateTimeTransformer())]
public DateTimeInterface $updatedAt;

// Custom format for parsing
#[MapWith(new DateTimeTransformer('d/m/Y'))]
public DateTimeInterface $birthDate;
```

### ArrayTransformer

Transforms arrays of objects to arrays of DTOs:

```php
<?php

use Ninja\Granite\Mapping\Transformers\ArrayTransformer;

final readonly class TeamResponse extends GraniteDTO
{
    public function __construct(
        public string $name,
        
        #[MapWith(new ArrayTransformer($mapper, UserDTO::class))]
        public array $members,
        
        #[MapWith(new ArrayTransformer($mapper, ProjectDTO::class))]
        public array $projects
    ) {}
}
```

## Mapping to Existing Objects

You can also map to existing object instances:

```php
<?php

$existingUser = new UserResponse(
    id: 1,
    name: 'Old Name',
    surname: 'Old Surname',
    email: 'old@example.com',
    joinDate: '2020-01-01'
);

$newData = [
    'name' => 'New Name',
    'email' => 'new@example.com'
];

$updatedUser = $mapper->mapTo($newData, $existingUser);
// Only specified properties are updated
```

## Advanced Features

### Conditional Mapping

Apply mapping logic based on conditions:

```php
<?php

class ConditionalMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(UserEntity::class, UserDTO::class)
            ->forMember('displayName', fn($mapping) => 
                $mapping->using(function($value, $source) {
                    if (!empty($source['nickname'])) {
                        return $source['nickname'];
                    }
                    return $source['firstName'] . ' ' . $source['lastName'];
                })
            );
    }
}
```

### Type Conversion

AutoMapper handles basic type conversions automatically:

```php
<?php

// String to integer
$source = ['age' => '25'];
$result = $mapper->map($source, UserDTO::class);
// $result->age will be integer 25

// Array to object
$source = ['settings' => ['theme' => 'dark', 'lang' => 'en']];
$result = $mapper->map($source, UserPreferences::class);
```

### Collection Mapping

Handle complex collection scenarios:

```php
<?php

class BlogMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        $this->createMap(BlogEntity::class, BlogSummary::class)
            ->forMember('authorNames', fn($mapping) => 
                $mapping->mapFrom('authors')
                        ->using(fn($authors) => 
                            array_map(fn($author) => $author['name'], $authors)
                        )
            )
            ->forMember('tagList', fn($mapping) => 
                $mapping->mapFrom('tags')
                        ->using(fn($tags) => implode(', ', $tags))
            );
    }
}
```

## Performance Considerations

### Caching

AutoMapper automatically caches mapping configurations for improved performance:

```php
<?php

// First call builds and caches the mapping configuration
$result1 = $mapper->map($source1, DestinationType::class);

// Subsequent calls use cached configuration
$result2 = $mapper->map($source2, DestinationType::class); // Faster
```

### Profile Reuse

Reuse mapping profiles across different mapper instances:

```php
<?php

$profile = new UserMappingProfile();

$mapper1 = new AutoMapper([$profile]);
$mapper2 = new AutoMapper([$profile]); // Reuses the same profile
```

### Bulk Operations

For large datasets, use `mapArray()` for better performance:

```php
<?php

// Efficient for large arrays
$results = $mapper->mapArray($largeDataset, TargetType::class);

// Less efficient
$results = [];
foreach ($largeDataset as $item) {
    $results[] = $mapper->map($item, TargetType::class);
}
```

## Error Handling

AutoMapper provides clear error messages for common issues:

```php
<?php

try {
    $result = $mapper->map($source, NonExistentClass::class);
} catch (InvalidArgumentException $e) {
    // Handle mapping errors
    echo "Mapping error: " . $e->getMessage();
}
```

## Best Practices

1. **Use profiles for complex mappings** - Keep attribute-based mapping for simple scenarios
2. **Create reusable transformers** - Don't repeat transformation logic
3. **Leverage caching** - Let AutoMapper cache configurations for performance
4. **Test mappings thoroughly** - Ensure data integrity across transformations
5. **Document complex transformations** - Make your mapping logic clear and maintainable