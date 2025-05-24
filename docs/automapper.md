# AutoMapper

Granite's AutoMapper provides powerful object-to-object mapping capabilities with automatic property matching, custom transformations, and convention-based mapping. It's perfect for converting between DTOs, entities, API responses, and other data structures.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Mapping Configuration](#mapping-configuration)
- [Property Attributes](#property-attributes)
- [Custom Transformations](#custom-transformations)
- [Collection Mapping](#collection-mapping)
- [Convention-Based Mapping](#convention-based-mapping)
- [Mapping Profiles](#mapping-profiles)
- [Bidirectional Mapping](#bidirectional-mapping)
- [Advanced Scenarios](#advanced-scenarios)
- [Performance Optimization](#performance-optimization)

## Basic Usage

### Simple Mapping

```php
<?php

use Ninja\Granite\Mapping\AutoMapper;
use Ninja\Granite\GraniteDTO;

// Source DTO
final readonly class UserEntity extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public DateTime $createdAt
    ) {}
}

// Destination DTO
final readonly class UserResponse extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email
    ) {}
}

// Basic mapping - properties match by name
$mapper = new AutoMapper();
$userEntity = UserEntity::from([
    'id' => 1,
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'john@example.com',
    'createdAt' => '2023-01-15T10:30:00Z'
]);

$userResponse = $mapper->map($userEntity, UserResponse::class);
// Result: UserResponse with id, firstName, lastName, email
// createdAt is omitted since it doesn't exist in destination
```

### Array to Object Mapping

```php
$userData = [
    'id' => 1,
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'john@example.com'
];

$userResponse = $mapper->map($userData, UserResponse::class);
```

### Collection Mapping

```php
$userEntities = [
    UserEntity::from(['id' => 1, 'firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com']),
    UserEntity::from(['id' => 2, 'firstName' => 'Jane', 'lastName' => 'Smith', 'email' => 'jane@example.com'])
];

$userResponses = $mapper->mapArray($userEntities, UserResponse::class);
// Returns array of UserResponse objects
```

## Mapping Configuration

### Explicit Property Mapping

Configure custom property mappings when names don't match:

```php
final readonly class UserEntity extends GraniteDTO
{
    public function __construct(
        public int $userId,
        public string $fullName,
        public string $emailAddress,
        public string $phoneNumber
    ) {}
}

final readonly class UserDto extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $phone
    ) {}
}

// Configure explicit mapping
$mapper = new AutoMapper();
$mapping = $mapper->createMap(UserEntity::class, UserDto::class)
    ->forMember('id', fn($m) => $m->mapFrom('userId'))
    ->forMember('name', fn($m) => $m->mapFrom('fullName'))
    ->forMember('email', fn($m) => $m->mapFrom('emailAddress'))
    ->forMember('phone', fn($m) => $m->mapFrom('phoneNumber'))
    ->seal();

$userEntity = UserEntity::from([
    'userId' => 1,
    'fullName' => 'John Doe',
    'emailAddress' => 'john@example.com',
    'phoneNumber' => '+1-555-0123'
]);

$userDto = $mapper->map($userEntity, UserDto::class);
```

### Ignoring Properties

```php
$mapping = $mapper->createMap(UserEntity::class, UserDto::class)
    ->forMember('sensitiveData', fn($m) => $m->ignore())
    ->seal();
```

### Default Values

```php
$mapping = $mapper->createMap(UserEntity::class, UserDto::class)
    ->forMember('status', fn($m) => $m->defaultValue('active'))
    ->forMember('role', fn($m) => $m->defaultValue('user'))
    ->seal();
```

### Conditional Mapping

```php
$mapping = $mapper->createMap(UserEntity::class, UserDto::class)
    ->forMember('adminData', fn($m) => 
        $m->mapFrom('internalData')
          ->onlyIf(fn($sourceData) => $sourceData['userType'] === 'admin')
    )
    ->seal();
```

## Property Attributes

Use attributes for simple, declarative mapping configuration:

### Basic Mapping Attributes

```php
use Ninja\Granite\Mapping\Attributes\MapFrom;
use Ninja\Granite\Mapping\Attributes\Ignore;
use Ninja\Granite\Mapping\Attributes\MapDefault;
use Ninja\Granite\Mapping\Attributes\MapWhen;

final readonly class UserResponse extends GraniteDTO
{
    public function __construct(
        // Map from different property name
        #[MapFrom('userId')]
        public int $id,
        
        #[MapFrom('fullName')]
        public string $name,
        
        // Ignore during mapping
        #[Ignore]
        public ?string $internalId = null,
        
        // Use default value
        #[MapDefault('active')]
        public string $status,
        
        // Conditional mapping
        #[MapWhen(fn($data) => isset($data['isAdmin']) && $data['isAdmin'])]
        #[MapFrom('adminEmail')]
        public ?string $email = null
    ) {}
}
```

### Collection Mapping

```php
use Ninja\Granite\Mapping\Attributes\MapCollection;

final readonly class TeamResponse extends GraniteDTO
{
    public function __construct(
        public string $name,
        
        // Map array of objects
        #[MapCollection(UserResponse::class)]
        public array $members,
        
        // Map collection with preserved keys
        #[MapCollection(ProjectResponse::class, preserveKeys: true)]
        public array $projects
    ) {}
}
```

### Nested Object Mapping

```php
final readonly class CompanyResponse extends GraniteDTO
{
    public function __construct(
        public string $name,
        
        // Nested object mapping
        #[MapFrom('primaryAddress')]
        public AddressResponse $address,
        
        #[MapFrom('contactPerson')]
        public UserResponse $contact
    ) {}
}
```

## Custom Transformations

### Using Transformers

Create custom transformation logic:

```php
use Ninja\Granite\Mapping\Contracts\Transformer;

class FullNameTransformer implements Transformer
{
    public function transform(mixed $value, array $sourceData = []): mixed
    {
        if (isset($sourceData['firstName']) && isset($sourceData['lastName'])) {
            return $sourceData['firstName'] . ' ' . $sourceData['lastName'];
        }
        
        return $value;
    }
}

// Use in mapping configuration
$mapping = $mapper->createMap(UserEntity::class, UserResponse::class)
    ->forMember('fullName', fn($m) => 
        $m->mapFrom('firstName')->using(new FullNameTransformer())
    )
    ->seal();
```

### Using Callbacks

```php
$mapping = $mapper->createMap(UserEntity::class, UserResponse::class)
    ->forMember('displayName', fn($m) => 
        $m->using(function($value, $sourceData) {
            $first = $sourceData['firstName'] ?? '';
            $last = $sourceData['lastName'] ?? '';
            return trim($first . ' ' . $last);
        })
    )
    ->forMember('age', fn($m) => 
        $m->mapFrom('birthDate')
          ->using(function($birthDate) {
              return $birthDate ? (new DateTime())->diff($birthDate)->y : null;
          })
    )
    ->seal();
```

### Static Method Transformers

```php
class DateTransformers
{
    public static function toAge(DateTime $birthDate): int
    {
        return (new DateTime())->diff($birthDate)->y;
    }
    
    public static function formatDate(DateTime $date): string
    {
        return $date->format('Y-m-d');
    }
}

$mapping = $mapper->createMap(UserEntity::class, UserResponse::class)
    ->forMember('age', fn($m) => 
        $m->mapFrom('birthDate')->using([DateTransformers::class, 'toAge'])
    )
    ->forMember('joinDate', fn($m) => 
        $m->mapFrom('createdAt')->using([DateTransformers::class, 'formatDate'])
    )
    ->seal();
```

### Attribute-based Transformers

```php
use Ninja\Granite\Mapping\Attributes\MapWith;

final readonly class UserResponse extends GraniteDTO
{
    public function __construct(
        public int $id,
        
        #[MapWith(new FullNameTransformer())]
        public string $fullName,
        
        #[MapWith([DateTransformers::class, 'toAge'])]
        #[MapFrom('birthDate')]
        public int $age
    ) {}
}
```

## Collection Mapping

### Simple Collections

```php
// Array of scalars
$mapping = $mapper->createMap(SourceClass::class, DestinationClass::class)
    ->forMember('tags', fn($m) => $m->mapFrom('labels'))
    ->seal();

// Array of objects
$mapping = $mapper->createMap(TeamEntity::class, TeamResponse::class)
    ->forMember('members', fn($m) => 
        $m->mapFrom('users')->asCollection(UserResponse::class)
    )
    ->seal();
```

### Advanced Collection Mapping

```php
use Ninja\Granite\Mapping\Transformers\CollectionTransformer;

$mapping = $mapper->createMap(ProjectEntity::class, ProjectResponse::class)
    ->forMember('tasks', fn($m) => 
        $m->using(new CollectionTransformer(
            mapper: $mapper,
            destinationType: TaskResponse::class,
            preserveKeys: true,
            recursive: true,  // Handle nested collections
            itemTransformer: function($task) {
                // Custom transformation for each item
                $task['priority'] = strtoupper($task['priority']);
                return $task;
            }
        ))
    )
    ->seal();
```

### Filtered Collections

```php
$mapping = $mapper->createMap(UserEntity::class, UserResponse::class)
    ->forMember('activeProjects', fn($m) => 
        $m->mapFrom('projects')->using(function($projects) use ($mapper) {
            $activeProjects = array_filter($projects, 
                fn($project) => $project['status'] === 'active'
            );
            return $mapper->mapArray($activeProjects, ProjectResponse::class);
        })
    )
    ->seal();
```

## Convention-Based Mapping

AutoMapper can automatically discover property mappings based on naming conventions:

### Enabling Conventions

```php
// Enable convention-based mapping (enabled by default)
$mapper = new AutoMapper(useConventions: true);

// Or enable later
$mapper->useConventions(true);

// Set confidence threshold (default: 0.8)
$mapper->setConventionConfidenceThreshold(0.7);
```

### Supported Conventions

```php
// These will be automatically mapped:
class SourceClass {
    public string $firstName;      // Maps to first_name, FirstName, first-name
    public string $email_address;  // Maps to emailAddress, EmailAddress
    public string $userID;         // Maps to userId, user_id
}

class DestinationClass {
    public string $first_name;
    public string $emailAddress;
    public string $user_id;
}

// Convention mapping happens automatically
$result = $mapper->map($source, DestinationClass::class);
```

### Custom Conventions

```php
use Ninja\Granite\Mapping\Contracts\NamingConvention;

class CustomConvention implements NamingConvention
{
    public function getName(): string
    {
        return 'custom';
    }
    
    public function matches(string $name): bool
    {
        return str_starts_with($name, 'custom_');
    }
    
    public function normalize(string $name): string
    {
        return str_replace('custom_', '', $name);
    }
    
    public function denormalize(string $normalized): string
    {
        return 'custom_' . $normalized;
    }
    
    public function calculateMatchConfidence(string $sourceName, string $destinationName): float
    {
        // Implementation for confidence calculation
        return 0.8;
    }
}

// Register custom convention
$mapper->registerConvention(new CustomConvention());
```

## Mapping Profiles

Group related mappings in profiles for better organization:

```php
use Ninja\Granite\Mapping\MappingProfile;

class UserMappingProfile extends MappingProfile
{
    protected function configure(): void
    {
        // User Entity to Response
        $this->createMap(UserEntity::class, UserResponse::class)
            ->forMember('id', fn($m) => $m->mapFrom('userId'))
            ->forMember('name', fn($m) => $m->mapFrom('fullName'))
            ->seal();
            
        // User Entity to Admin Response (more fields)
        $this->createMap(UserEntity::class, AdminUserResponse::class)
            ->forMember('id', fn($m) => $m->mapFrom('userId'))
            ->forMember('name', fn($m) => $m->mapFrom('fullName'))
            ->forMember('permissions', fn($m) => $m->mapFrom('rolePermissions'))
            ->seal();
            
        // Bidirectional mapping
        $this->createMapBidirectional(UserEntity::class, UserDto::class)
            ->forMembers('userId', 'id')
            ->forMembers('fullName', 'name')
            ->seal();
    }
}

// Use profile
$mapper = new AutoMapper([
    new UserMappingProfile(),
    new ProjectMappingProfile(),
    new CompanyMappingProfile()
]);
```

### Profile Inheritance

```php
class BaseEntityProfile extends MappingProfile
{
    protected function configure(): void
    {
        // Common mappings for all entities
        $this->createMap('*', '*')
            ->forMember('id', fn($m) => $m->mapFrom('entityId'))
            ->forMember('createdAt', fn($m) => $m->mapFrom('created_timestamp'))
            ->seal();
    }
}

class UserProfile extends BaseEntityProfile
{
    protected function configure(): void
    {
        parent::configure(); // Apply base mappings
        
        // User-specific mappings
        $this->createMap(UserEntity::class, UserResponse::class)
            ->forMember('name', fn($m) => $m->mapFrom('fullName'))
            ->seal();
    }
}
```

## Bidirectional Mapping

Create mappings that work in both directions:

```php
$bidirectionalMapping = $mapper->createMapBidirectional(UserEntity::class, UserDto::class)
    ->forMembers('userId', 'id')           // userId <-> id
    ->forMembers('fullName', 'name')       // fullName <-> name
    ->forMembers('emailAddress', 'email')  // emailAddress <-> email
    ->seal();

// Now both directions work
$userDto = $mapper->map($userEntity, UserDto::class);
$userEntity = $mapper->map($userDto, UserEntity::class);
```

### Asymmetric Bidirectional Mapping

```php
$bidirectionalMapping = $mapper->createMapBidirectional(UserEntity::class, UserDto::class)
    ->forMembers('userId', 'id')
    ->forMembers('fullName', 'name')
    // Forward-only: entity to DTO
    ->forForwardMember('summary', fn($m) => 
        $m->using(function($value, $data) {
            return $data['fullName'] . ' (' . $data['emailAddress'] . ')';
        })
    )
    // Reverse-only: DTO to entity
    ->forReverseMember('internalId', fn($m) => 
        $m->defaultValue(Uuid::generate())
    )
    ->seal();
```

## Advanced Scenarios

### Nested Property Mapping

```php
// Source has nested structure
$sourceData = [
    'user' => [
        'profile' => [
            'personal' => [
                'firstName' => 'John',
                'lastName' => 'Doe'
            ],
            'contact' => [
                'email' => 'john@example.com'
            ]
        ]
    ]
];

$mapping = $mapper->createMap('array', UserResponse::class)
    ->forMember('firstName', fn($m) => $m->mapFrom('user.profile.personal.firstName'))
    ->forMember('lastName', fn($m) => $m->mapFrom('user.profile.personal.lastName'))
    ->forMember('email', fn($m) => $m->mapFrom('user.profile.contact.email'))
    ->seal();

$userResponse = $mapper->map($sourceData, UserResponse::class);
```

### Dynamic Property Mapping

```php
class DynamicMappingProfile extends MappingProfile
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        parent::__construct();
    }
    
    protected function configure(): void
    {
        foreach ($this->config['mappings'] as $mapping) {
            $typeMapping = $this->createMap($mapping['source'], $mapping['destination']);
            
            foreach ($mapping['properties'] as $destProp => $sourceProp) {
                $typeMapping->forMember($destProp, fn($m) => $m->mapFrom($sourceProp));
            }
            
            $typeMapping->seal();
        }
    }
}

// Load configuration from file/database
$config = [
    'mappings' => [
        [
            'source' => UserEntity::class,
            'destination' => UserResponse::class,
            'properties' => [
                'id' => 'userId',
                'name' => 'fullName',
                'email' => 'emailAddress'
            ]
        ]
    ]
];

$mapper = new AutoMapper([new DynamicMappingProfile($config)]);
```

### Conditional Object Creation

```php
$mapping = $mapper->createMap(PaymentData::class, PaymentResponse::class)
    ->forMember('processingDetails', fn($m) => 
        $m->using(function($value, $sourceData) use ($mapper) {
            switch ($sourceData['paymentType']) {
                case 'credit_card':
                    return $mapper->map($sourceData['details'], CreditCardDetails::class);
                case 'paypal':
                    return $mapper->map($sourceData['details'], PaypalDetails::class);
                case 'bank_transfer':
                    return $mapper->map($sourceData['details'], BankTransferDetails::class);
                default:
                    return null;
            }
        })
    )
    ->seal();
```

### Validation During Mapping

```php
use Ninja\Granite\GraniteVO; // VOs have validation

$mapping = $mapper->createMap(UserData::class, ValidatedUser::class)
    ->forMember('email', fn($m) => 
        $m->using(function($email) {
            // Transform and validate
            $email = strtolower(trim($email));
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email format');
            }
            
            return $email;
        })
    )
    ->seal();

// Create as VO for automatic validation
final readonly class ValidatedUser extends GraniteVO
{
    public function __construct(
        #[Required]
        #[Email]
        public string $email,
        
        #[Required]
        #[Min(2)]
        public string $name
    ) {}
}
```

## Performance Optimization

### Caching Strategies

```php
use Ninja\Granite\Enums\CacheType;

// Use shared cache across requests
$mapper = new AutoMapper(
    cacheType: CacheType::Shared
);

// Use persistent file-based cache
$mapper = new AutoMapper(
    cacheType: CacheType::Persistent
);

// Disable cache warming for faster startup
$mapper = new AutoMapper(
    warmupCache: false
);
```

### Preloading Mappings

```php
use Ninja\Granite\Mapping\MappingPreloader;

// Preload common mappings
$typePairs = [
    [UserEntity::class, UserResponse::class],
    [ProductEntity::class, ProductResponse::class],
    [OrderEntity::class, OrderResponse::class]
];

$preloaded = MappingPreloader::preload($mapper, $typePairs);
echo "Preloaded {$preloaded} mappings";

// Preload from namespace
$preloaded = MappingPreloader::preloadFromNamespace(
    $mapper, 
    'App\\Entities',
    ['Entity', 'Response']
);
```

### Batch Mapping

```php
// More efficient than mapping individually
$users = $mapper->mapArray($userEntities, UserResponse::class);

// For very large collections, consider chunking
$allUsers = [];
$chunks = array_chunk($userEntities, 1000);

foreach ($chunks as $chunk) {
    $mappedChunk = $mapper->mapArray($chunk, UserResponse::class);
    $allUsers = array_merge($allUsers, $mappedChunk);
}
```

### Memory Management

```php
// Clear cache periodically for long-running processes
$mapper->clearCache();

// Clear convention cache
$mapper->clearConventionCache();

// Create new mapper instance for isolated operations
$temporaryMapper = new AutoMapper(
    profiles: [],
    cacheType: CacheType::Memory,
    warmupCache: false
);
```

## Error Handling

```php
use Ninja\Granite\Mapping\Exceptions\MappingException;

try {
    $result = $mapper->map($source, DestinationType::class);
} catch (MappingException $e) {
    echo "Mapping failed: " . $e->getMessage();
    echo "Source type: " . $e->getSourceType();
    echo "Destination type: " . $e->getDestinationType();
    echo "Property: " . $e->getPropertyName();
    
    // Log additional context
    $context = $e->getContext();
    error_log("Mapping error context: " . json_encode($context));
}
```

## Best Practices

### 1. Use Profiles for Organization

```php
// Good: Organized by domain
class UserMappingProfile extends MappingProfile { /* ... */ }
class ProductMappingProfile extends MappingProfile { /* ... */ }
class OrderMappingProfile extends MappingProfile { /* ... */ }

// Avoid: Everything in one place
$mapper->createMap(UserEntity::class, UserResponse::class)/* ... */;
$mapper->createMap(ProductEntity::class, ProductResponse::class)/* ... */;
// ... hundreds of mappings
```

### 2. Seal Your Mappings

```php
// Good: Always seal mappings
$mapping = $mapper->createMap(Source::class, Destination::class)
    ->forMember('prop', fn($m) => $m->mapFrom('other'))
    ->seal(); // Important!

// Bad: Forgetting to seal
$mapping = $mapper->createMap(Source::class, Destination::class)
    ->forMember('prop', fn($m) => $m->mapFrom('other'));
// Mapping may not work correctly
```

### 3. Use Appropriate Cache Strategy

```php
// For web applications
$mapper = new AutoMapper(cacheType: CacheType::Shared);

// For CLI tools/scripts
$mapper = new AutoMapper(cacheType: CacheType::Memory);

// For high-performance scenarios
$mapper = new AutoMapper(
    cacheType: CacheType::Persistent,
    warmupCache: true
);
```

### 4. Handle Edge Cases

```php
$mapping = $mapper->createMap(UserEntity::class, UserResponse::class)
    ->forMember('fullName', fn($m) => 
        $m->using(function($value, $sourceData) {
            $first = $sourceData['firstName'] ?? '';
            $last = $sourceData['lastName'] ?? '';
            
            // Handle edge cases
            if (empty($first) && empty($last)) {
                return 'Unknown User';
            }
            
            return trim($first . ' ' . $last);
        })
    )
    ->seal();
```

AutoMapper provides a flexible and powerful way to handle object transformations while maintaining type safety and performance. Use it to keep your mapping logic organized, testable, and maintainable.