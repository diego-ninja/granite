# Migration Guide

This guide helps you migrate from other libraries and patterns to Granite, providing step-by-step instructions and common migration scenarios.

## Table of Contents

- [From Plain Arrays](#from-plain-arrays)
- [From stdClass Objects](#from-stdclass-objects)
- [From Doctrine Entities](#from-doctrine-entities)
- [From Laravel Eloquent](#from-laravel-eloquent)
- [From Custom DTOs](#from-custom-dtos)
- [Migration Strategies](#migration-strategies)
- [Common Challenges](#common-challenges)

## From Plain Arrays

### Before: Using Arrays

```php
// Old approach with arrays
function createUser(array $userData): array
{
    // No validation, no type safety
    $user = [
        'id' => $userData['id'] ?? null,
        'name' => $userData['name'] ?? '',
        'email' => $userData['email'] ?? '',
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Manual validation
    if (empty($user['name'])) {
        throw new InvalidArgumentException('Name is required');
    }

    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email');
    }

    return $user;
}
```

### After: Using Granite

```php
use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\StringType;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class User extends Granite
{
    public function __construct(
        public ?int $id,

        #[Required]
        #[StringType]
        public string $name,

        #[Required]
        #[Email]
        public string $email,

        #[SerializedName('member_since')]
        public DateTime $createdAt = new DateTime()
    ) {}
}

function createUser(array $userData): User
{
    // Automatic validation and type conversion
    return User::from($userData);
}

function serializeUser(User $user): string
{
    // Automatic serialization with custom property names
    return $user->json();
}
```

### Migration Steps

1. **Identify Data Structures**: List all arrays that represent data entities
2. **Create Granite Classes**: Convert each array structure to a Granite object
3. **Add Validation**: Replace manual validation with attributes
4. **Update Function Signatures**: Change array parameters to Granite types
5. **Test Thoroughly**: Ensure all validation and serialization works

## From stdClass Objects

### Before: Using stdClass

```php
function processApiResponse(string $json): stdClass
{
    $data = json_decode($json);

    // No type safety, no validation
    if (!isset($data->user_id) || !is_int($data->user_id)) {
        throw new InvalidArgumentException('Invalid user ID');
    }

    // Manual property access with null checks
    $processed = new stdClass();
    $processed->id = $data->user_id;
    $processed->name = $data->full_name ?? 'Unknown';
    $processed->email = $data->email_address ?? '';

    return $processed;
}
```

### After: Using Granite

```php
use Ninja\Granite\Granite;
use Ninja\Granite\Serialization\Attributes\SerializedName;

final readonly class ApiUserResponse extends Granite
{
    public function __construct(
        #[SerializedName('user_id')]
        public int $id,

        #[SerializedName('full_name')]
        public string $name,

        #[SerializedName('email_address')]
        public string $email
    ) {}
}

function processApiResponse(string $json): ApiUserResponse
{
    // Automatic parsing, validation, and type safety
    return ApiUserResponse::from($json);
}
```

## From Doctrine Entities

### Before: Doctrine Entity

```php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    // Getters and setters...
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    // ... more boilerplate
}
```

### After: Granite Entity + Repository Pattern

```php
// Domain Entity (immutable)
use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;

final readonly class User extends Granite
{
    public function __construct(
        public ?int $id,

        #[Required]
        public string $name,

        #[Required]
        #[Email]
        public string $email,

        public DateTime $createdAt = new DateTime()
    ) {}
}

// Repository handles persistence
final readonly class UserRepository
{
    public function __construct(private PDO $pdo) {}

    public function save(User $user): User
    {
        if ($user->id === null) {
            // Insert
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $user->name,
                $user->email,
                $user->createdAt->format('Y-m-d H:i:s')
            ]);

            return $user->with(['id' => $this->pdo->lastInsertId()]);
        }

        // Update
        $stmt = $this->pdo->prepare(
            'UPDATE users SET name = ?, email = ? WHERE id = ?'
        );
        $stmt->execute([$user->name, $user->email, $user->id]);

        return $user;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? User::from($data) : null;
    }
}
```

### Migration Strategy

1. **Create Granite Entities**: Convert Doctrine entities to immutable Granite objects
2. **Extract Repository Logic**: Move persistence logic to repository classes
3. **Update Service Layer**: Change services to work with immutable objects
4. **Replace Getters/Setters**: Use public readonly properties
5. **Add Validation**: Replace Doctrine validation with Granite attributes

## From Laravel Eloquent

### Before: Eloquent Model

```php
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

// Usage
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();
```

### After: Granite with Repository

```php
use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Serialization\Attributes\Hidden;

// Domain Entity
final readonly class User extends Granite
{
    public function __construct(
        public ?int $id,

        #[Required]
        public string $name,

        #[Required]
        #[Email]
        public string $email,

        #[Required]
        #[Hidden]
        public string $password,

        public ?DateTime $emailVerifiedAt = null,
        public DateTime $createdAt = new DateTime()
    ) {}
}

// Repository handles database operations
final readonly class UserRepository
{
    public function __construct(
        private PDO $pdo,
        private OrderRepository $orderRepository
    ) {}

    public function save(User $user): User
    {
        // Implementation similar to Doctrine example
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? User::from($data) : null;
    }

    public function getOrdersForUser(int $userId): array
    {
        return $this->orderRepository->findByUserId($userId);
    }
}
```

## From Custom DTOs

### Before: Custom DTO Implementation

```php
class UserDto
{
    private int $id;
    private string $name;
    private string $email;

    public function __construct(int $id, string $name, string $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? throw new InvalidArgumentException('ID required'),
            $data['name'] ?? throw new InvalidArgumentException('Name required'),
            $data['email'] ?? throw new InvalidArgumentException('Email required')
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
```

### After: Granite DTO

```php
use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;

final readonly class UserDto extends Granite
{
    public function __construct(
        #[Required]
        public int $id,

        #[Required]
        public string $name,

        #[Required]
        #[Email]
        public string $email
    ) {}

    // toArray(), fromArray(), and toJson() are inherited
    // Validation is automatic
    // Immutability is guaranteed
}
```

## Migration Strategies

### Gradual Migration

```php
// Step 1: Create Granite versions alongside existing code
class LegacyUserService
{
    public function createUser(array $userData): array
    {
        // Legacy implementation
    }
}

class ModernUserService
{
    public function createUser(CreateUserRequest $request): User
    {
        // Granite implementation
    }
}

// Step 2: Create adapter/bridge
class UserServiceAdapter
{
    public function __construct(
        private LegacyUserService $legacyService,
        private ModernUserService $modernService,
        private bool $useModern = false
    ) {}

    public function createUser(array $userData): array
    {
        if ($this->useModern) {
            $request = CreateUserRequest::from($userData);
            $user = $this->modernService->createUser($request);
            return $user->array();
        }

        return $this->legacyService->createUser($userData);
    }
}

// Step 3: Feature flag migration
class FeatureFlaggedUserService
{
    public function createUser(array $userData): array
    {
        if ($this->featureFlags->isEnabled('granite_users')) {
            return $this->modernService->createUser(
                CreateUserRequest::from($userData)
            )->array();
        }

        return $this->legacyService->createUser($userData);
    }
}
```

### Parallel Implementation

```php
// Run both implementations and compare results
class VerifyingUserService
{
    public function createUser(array $userData): array
    {
        $legacyResult = $this->legacyService->createUser($userData);

        try {
            $modernResult = $this->modernService->createUser(
                CreateUserRequest::from($userData)
            )->array();

            // Compare results and log differences
            $this->compareResults($legacyResult, $modernResult);

        } catch (Exception $e) {
            // Log modern implementation errors
            $this->logger->error('Modern implementation failed', [
                'error' => $e->getMessage(),
                'data' => $userData
            ]);
        }

        return $legacyResult; // Return legacy result for now
    }
}
```

### Database Migration

```php
// Migrate database structure to work with Granite objects
class DatabaseMigration
{
    public function migrateUserTable(): void
    {
        // Add columns that Granite expects
        $this->addColumn('users', 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
        $this->addColumn('users', 'updated_at', 'TIMESTAMP NULL');

        // Convert existing data
        $users = $this->selectAll('users');
        foreach ($users as $userData) {
            try {
                // Validate existing data with Granite
                $user = User::from($userData);
                // If validation passes, data is good
            } catch (ValidationException $e) {
                // Fix invalid data
                $this->fixUserData($userData['id'], $e->getErrors());
            }
        }
    }
}
```

## Common Challenges

### Challenge 1: Nullable Properties

**Problem**: Legacy code has many nullable fields that should be required.

```php
// Legacy: Everything nullable
$user = [
    'id' => $data['id'] ?? null,
    'name' => $data['name'] ?? null,
    'email' => $data['email'] ?? null
];

// Granite: Explicit about requirements
final readonly class User extends Granite
{
    public function __construct(
        public ?int $id, // OK to be null (new users)

        #[Required] // This will fail if null
        public string $name,

        #[Required]
        public string $email
    ) {}
}
```

**Solution**: Use default values and data cleaning.

```php
final readonly class UserFactory
{
    public static function fromLegacyData(array $data): User
    {
        return User::from([
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? 'Unknown User',
            'email' => $data['email'] ?? 'unknown@example.com'
        ]);
    }
}
```

### Challenge 2: Dynamic Properties

**Problem**: Legacy code adds properties dynamically.

```php
// Legacy: Dynamic properties
$user = new stdClass();
$user->id = 1;
$user->name = 'John';

if ($includeEmail) {
    $user->email = 'john@example.com';
}
```

**Solution**: Use different Granite classes for different contexts.

```php
// Base user
final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name
    ) {}
}

// User with email
final readonly class UserWithEmail extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email
    ) {}
}

// Factory to create appropriate type
class UserFactory
{
    public static function create(array $data, bool $includeEmail = false): Granite
    {
        if ($includeEmail) {
            return UserWithEmail::from($data);
        }

        return User::from($data);
    }
}
```

### Challenge 3: Circular References

**Problem**: Objects reference each other.

```php
// Legacy: Circular references
class User
{
    public array $orders = [];
}

class Order
{
    public User $user;
}
```

**Solution**: Use IDs instead of full objects, or create specific DTOs.

```php
// Use IDs to break cycles
final readonly class User extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public array $orderIds = [] // Just IDs, not full objects
    ) {}
}

final readonly class Order extends Granite
{
    public function __construct(
        public int $id,
        public int $userId, // Just ID, not full user object
        public array $items
    ) {}
}

// Or create view-specific DTOs
final readonly class UserWithOrders extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var OrderSummary[] */
        public array $orders
    ) {}
}

final readonly class OrderSummary extends Granite
{
    public function __construct(
        public int $id,
        public float $total,
        public DateTime $createdAt
    ) {}
}
```

### Challenge 4: Performance Impact

**Problem**: Creating many objects impacts performance.

**Solution**: Use lazy loading and caching.

```php
// Lazy loading
final readonly class UserWithProfile extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        private ?Closure $profileLoader = null
    ) {}

    public function getProfile(): ?UserProfile
    {
        if ($this->profileLoader !== null) {
            return ($this->profileLoader)();
        }
        return null;
    }
}

// Use Granite's reflection caching
// Metadata is automatically cached for better performance
```

This migration guide provides a comprehensive roadmap for adopting Granite in existing projects while minimizing risk and maintaining functionality during the transition.
