# AutoMapper

Granite includes a powerful AutoMapper that allows you to automatically map data between different object structures. This is particularly useful when converting between DTOs, entities, API responses, and other data models.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Mapping with Attributes](#mapping-with-attributes)
- [Mapping Profiles](#mapping-profiles)
- [Custom Transformers](#custom-transformers)
- [Nested Property Mapping](#nested-property-mapping)
- [Collection Mapping](#collection-mapping)
- [Bidirectional Mapping](#bidirectional-mapping)
- [Conditional Mapping](#conditional-mapping)
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
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapWhen;
use Ninja\Granite\Mapping\Attributes\MapCollection;
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
        
        #[MapDefault('Guest')]
        public string $role,
        
        #[MapWhen('isActive')]
        public bool $enabled,
        
        #[MapCollection(destinationType: PostDTO::class)]
        public array $posts,
        
        #[Ignore]
        public array $internalData = []
    ) {}
}
```

### Available Mapping Attributes

- **`#[MapFrom('sourceProperty')]`** - Maps from a different source property name
- **`#[MapWith(transformer)]`** - Applies a transformation to the value
- **`#[Ignore]`** - Excludes the property from mapping
- **`#[MapBidirectional]`** - Enables automatic bidirectional mapping between classes
- **`#[MapCollection(destinationType: Class::class, preserveKeys: false, recursive: false)]`** - Maps arrays or collections of objects to a typed collection
- **`#[MapDefault(value)]`** - Sets a default value when source property is null
- **`#[MapWhen(condition)]`** - Only maps the property when the condition is true

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
            ->forMember('joinDate', fn($mapping) => 
                $mapping->mapFrom('createdAt')
                        ->using(new DateTimeTransformer('Y-m-d'))
            )
            ->forMember('role', fn($mapping) => 
                $mapping->defaultValue('Guest')
            )
            ->forMember('enabled', fn($mapping) => 
                $mapping->onlyIf(fn($source) => $source['isActive'] === true)
            )
            ->forMember('posts', fn($mapping) => 
                $mapping->asCollection(PostDTO::class)
            );
    }
}

// Register the profile with the mapper
$mapper = new AutoMapper([new UserMappingProfile()]);
```

## Custom Transformers

You can create custom transformers by implementing the `Transformer` interface:

```php
<?php

use Ninja\Granite\Mapping\Contracts\Transformer;

class MoneyTransformer implements Transformer
{
    public function __construct(
        private string $currencySymbol = '$'
    ) {}
    
    public function transform(mixed $value, array $source = []): string
    {
        if ($value === null) {
            return $this->currencySymbol . '0.00';
        }
        
        return $this->currencySymbol . number_format((float)$value, 2);
    }
}

// Use in attribute
#[MapWith(new MoneyTransformer('€'))]
public string $formattedPrice;

// Or in profile
->forMember('formattedPrice', fn($mapping) => 
    $mapping->mapFrom('price')
            ->using(new MoneyTransformer('€'))
)
```

## Nested Property Mapping

You can map from nested properties using dot notation:

```php
<?php

// Source object
$order = new Order([
    'id' => 1,
    'customer' => [
        'id' => 100,
        'name' => 'John Doe',
        'contactInfo' => [
            'email' => 'john@example.com',
            'phone' => '555-1234'
        ]
    ]
]);

// In attribute
#[MapFrom('customer.contactInfo.email')]
public string $customerEmail;

// Or in profile
->forMember('customerEmail', fn($mapping) => 
    $mapping->mapFrom('customer.contactInfo.email')
)
```

## Collection Mapping

Map arrays or collections of objects:

```php
<?php

// Using attribute
#[MapCollection(destinationType: PostDTO::class, preserveKeys: true, recursive: false)]
public array $posts;

// Or in profile
->forMember('posts', fn($mapping) => 
    $mapping->asCollection(
        itemType: PostDTO::class,
        preserveKeys: true,
        recursive: false
    )
)

// Map arrays of objects
$postDtos = $mapper->mapArray($postEntities, PostDTO::class);
```

The `MapCollection` attribute supports:
- `destinationType`: Target class for collection items
- `preserveKeys`: Whether to preserve array keys (default: false)
- `recursive`: Whether to handle nested collections (default: false)
- `itemTransformer`: Optional transformer for collection items

## Bidirectional Mapping

Configure two-way mapping between classes:

```php
<?php

use Ninja\Granite\Mapping\Attributes\MapBidirectional;

class UserMappingProfile extends MappingProfile
{
    #[MapBidirectional]
    protected function configureUserMapping(): void
    {
        // Create bidirectional mapping
        $this->createMap(UserEntity::class, UserDTO::class)
            ->reverseMap();
            
        // Or with explicit property pairs
        $this->createBidirectionalMap(OrderEntity::class, OrderDTO::class)
            ->forMembers('id', 'orderId')
            ->forMembers('customerName', 'client')
            ->forMemberPairs([
                'totalAmount' => 'price',
                'createdAt' => 'orderDate'
            ]);
    }
}
```

With bidirectional mapping, you can easily convert objects in both directions:

```php
$userDto = $mapper->map($userEntity, UserDTO::class);
$userEntity = $mapper->map($userDto, UserEntity::class);
```

## Conditional Mapping

Map properties conditionally:

```php
<?php

// Using attribute
#[MapWhen('isActive')]
public bool $enabled;

// Or in profile
->forMember('enabled', fn($mapping) => 
    $mapping->onlyIf(fn($source) => $source['isActive'] === true)
)

// With default value when condition fails
->forMember('status', fn($mapping) => 
    $mapping->onlyIf(fn($source) => $source['isVerified'])
            ->defaultValue('Pending')
)
```

The `MapWhen` attribute accepts:
- A property name in the source object
- A callable that receives the source object and returns a boolean

## Built-in Transformers

Granite includes several built-in transformers:

- **DateTimeTransformer**: Converts between DateTime objects and formatted strings
- **CollectionTransformer**: Maps arrays of objects to typed collections
- **EnumTransformer**: Converts between enum values and their representations

```php
<?php

// DateTime transformation
#[MapWith(new DateTimeTransformer('Y-m-d'))]
public string $formattedDate;

// Enum transformation
#[MapWith(new EnumTransformer(StatusEnum::class))]
public StatusEnum $status;
```

## Mapping to Existing Objects

Map to an existing object instance:

```php
<?php

$existingDto = new UserDTO();
$mapper->mapTo($userEntity, $existingDto);
```

## Advanced Features

### Type Mapping Configuration

Configure complex type mappings:

```php
<?php

$mapper->createMap(UserEntity::class, UserDTO::class)
    ->forMember('fullName', fn($mapping) => 
        $mapping->using(fn($_, $src) => $src['firstName'] . ' ' . $src['lastName'])
    )
    ->seal(); // Validate and finalize the mapping
```

### Custom Value Resolvers

Resolve values with custom logic:

```php
<?php

->forMember('status', fn($mapping) => 
    $mapping->using(function($value, $source) {
        if ($source['isVerified']) {
            return 'Verified';
        } elseif ($source['isActive']) {
            return 'Active';
        } else {
            return 'Inactive';
        }
    })
)
```

## Performance Considerations

### Caching

AutoMapper caches mapping configurations for better performance:

```php
<?php

// Configure with cache
$mapper = new AutoMapper([
    new UserMappingProfile(),
    new OrderMappingProfile(),
], [
    'cache.enabled' => true,
    'cache.backend' => 'file', // 'file', 'redis', 'apcu'
    'cache.path' => '/path/to/cache',
]);
```

### Preloading Mappings

Preload mapping configurations for better performance:

```php
<?php

use Ninja\Granite\Mapping\MappingPreloader;

// Preload specific mappings
MappingPreloader::preload($mapper, [
    [UserEntity::class, UserDTO::class],
    [OrderEntity::class, OrderDTO::class],
]);

// Preload from namespace
MappingPreloader::preloadFromNamespace($mapper, 'App\\Domain', ['Entity', 'DTO']);

// Or use the mapper's preload method
$mapper->preloadMappings([
    [UserEntity::class, UserDTO::class],
    [OrderEntity::class, OrderDTO::class],
]);
```

### Warmup Cache

Warm up the mapping cache during deployment:

```php
<?php

$mapper = new AutoMapper([
    new UserMappingProfile(),
    new OrderMappingProfile(),
], [
    'cache.enabled' => true,
    'cache.warmup' => true,
]);
```

## Error Handling

AutoMapper provides clear error messages for common issues:

- Missing source or destination properties
- Type conversion errors
- Invalid mapping configurations

```php
<?php

try {
    $result = $mapper->map($source, Destination::class);
} catch (MappingException $e) {
    // Handle mapping errors
    echo $e->getMessage();
}