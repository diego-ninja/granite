# Advanced Usage

This guide covers advanced patterns, best practices, and architectural considerations when using Granite in complex applications.

## ⚠️ Note

All examples in this document use the `Granite` base class. As of version 2.0.0, the legacy `GraniteDTO` and `GraniteVO` classes are deprecated and will be removed in v3.0.0.

## Table of Contents

- [Architectural Patterns](#architectural-patterns)
- [Domain-Driven Design](#domain-driven-design)
- [API Design Patterns](#api-design-patterns)
- [Performance Considerations](#performance-considerations)
- [Testing Strategies](#testing-strategies)
- [Error Handling Strategies](#error-handling-strategies)
- [Integration Patterns](#integration-patterns)
- [Migration Strategies](#migration-strategies)

## Architectural Patterns

### Repository Pattern with DTOs

```php
<?php

interface UserRepositoryInterface
{
    public function findById(int $id): ?UserEntity;
    public function findByEmail(string $email): ?UserEntity;
    public function save(UserEntity $user): UserEntity;
    public function delete(int $id): bool;
}

final readonly class UserEntity extends Granite
{
    public function __construct(
        public ?int $id,
        
        #[Required]
        #[StringType]
        #[Min(2)]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Required]
        public DateTime $createdAt,
        
        public ?DateTime $updatedAt = null
    ) {}
}

final readonly class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private AutoMapper $mapper
    ) {}
    
    public function findById(int $id): ?UserEntity
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? UserEntity::from($data) : null;
    }
    
    public function save(UserEntity $user): UserEntity
    {
        $data = $user->array();
        
        if ($user->id === null) {
            // Insert
            unset($data['id']);
            $stmt = $this->pdo->prepare('INSERT INTO users (name, email, created_at) VALUES (?, ?, ?)');
            $stmt->execute([$data['name'], $data['email'], $data['createdAt']]);
            
            return $user->with(['id' => $this->pdo->lastInsertId()]);
        } else {
            // Update
            $stmt = $this->pdo->prepare('UPDATE users SET name = ?, email = ?, updated_at = ? WHERE id = ?');
            $stmt->execute([$data['name'], $data['email'], date('Y-m-d H:i:s'), $user->id]);
            
            return $user->with(['updatedAt' => new DateTime()]);
        }
    }
}
```

### Service Layer Pattern

```php
<?php

final readonly class CreateUserRequest extends Granite
{
    public function __construct(
        #[Required]
        #[StringType]
        #[Min(2)]
        #[Max(100)]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Required]
        #[StringType]
        #[Min(8)]
        #[Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', 'Password must contain uppercase, lowercase, and number')]
        public string $password,
        
        #[ArrayType]
        public array $roles = ['user']
    ) {}
}

final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasher $passwordHasher,
        private EventDispatcher $eventDispatcher,
        private AutoMapper $mapper
    ) {}
    
    public function createUser(CreateUserRequest $request): UserEntity
    {
        // Check if user already exists
        if ($this->userRepository->findByEmail($request->email)) {
            throw new DomainException('User with this email already exists');
        }
        
        // Create user entity
        $user = UserEntity::from([
            'id' => null,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $this->passwordHasher->hash($request->password),
            'roles' => $request->roles,
            'createdAt' => new DateTime(),
            'updatedAt' => null
        ]);
        
        // Save user
        $savedUser = $this->userRepository->save($user);
        
        // Dispatch event
        $this->eventDispatcher->dispatch(new UserCreatedEvent($savedUser));
        
        return $savedUser;
    }
    
    public function updateUser(int $id, UpdateUserRequest $request): UserEntity
    {
        $user = $this->userRepository->findById($id);
        if (!$user) {
            throw new NotFoundException('User not found');
        }
        
        // Update with new data
        $updatedUser = $user->with([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'updatedAt' => new DateTime()
        ]);
        
        return $this->userRepository->save($updatedUser);
    }
}
```

### CQRS Pattern

```php
<?php

// Commands
final readonly class CreateUserCommand extends Granite
{
    public function __construct(
        #[Required]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Required]
        public string $password
    ) {}
}

final readonly class UpdateUserCommand extends Granite
{
    public function __construct(
        #[Required]
        public int $id,
        
        public ?string $name = null,
        
        #[Email]
        public ?string $email = null
    ) {}
}

// Command Handlers
final readonly class CreateUserCommandHandler
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private PasswordHasher $passwordHasher
    ) {}
    
    public function handle(CreateUserCommand $command): UserEntity
    {
        $user = UserEntity::from([
            'id' => null,
            'name' => $command->name,
            'email' => $command->email,
            'password' => $this->passwordHasher->hash($command->password),
            'createdAt' => new DateTime()
        ]);
        
        return $this->repository->save($user);
    }
}

// Queries
final readonly class GetUserQuery extends Granite
{
    public function __construct(
        #[Required]
        public int $id
    ) {}
}

final readonly class GetUsersByRoleQuery extends Granite
{
    public function __construct(
        #[Required]
        public string $role,
        
        public int $page = 1,
        public int $limit = 20
    ) {}
}

// Query Handlers
final readonly class GetUserQueryHandler
{
    public function __construct(private UserRepositoryInterface $repository) {}
    
    public function handle(GetUserQuery $query): ?UserEntity
    {
        return $this->repository->findById($query->id);
    }
}
```

## Domain-Driven Design

### Value Objects

```php
<?php

final readonly class Email extends Granite
{
    public function __construct(
        #[Required]
        #[Email]
        public string $value
    ) {}
    
    public function getDomain(): string
    {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
    
    public function getLocalPart(): string
    {
        return substr($this->value, 0, strpos($this->value, '@'));
    }
    
    public function isBusinessEmail(): bool
    {
        $personalDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        return !in_array($this->getDomain(), $personalDomains);
    }
}

final readonly class Money extends Granite
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
    
    public function multiply(float $factor): Money
    {
        return new Money($this->amount * $factor, $this->currency);
    }
    
    public function format(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }
}

final readonly class Address extends Granite
{
    public function __construct(
        #[Required]
        #[StringType]
        public string $street,
        
        #[Required]
        #[StringType]
        public string $city,
        
        #[Required]
        #[StringType]
        public string $state,
        
        #[Required]
        #[Regex('/^\d{5}(-\d{4})?$/')]
        public string $zipCode,
        
        #[Required]
        #[StringType]
        #[Min(2)]
        #[Max(2)]
        public string $country = 'US'
    ) {}
    
    public function getFullAddress(): string
    {
        return "{$this->street}, {$this->city}, {$this->state} {$this->zipCode}, {$this->country}";
    }
    
    public function isInternational(): bool
    {
        return $this->country !== 'US';
    }
}
```

### Aggregates

```php
<?php

final readonly class Customer extends Granite
{
    public function __construct(
        public ?int $id,
        
        #[Required]
        #[StringType]
        public string $name,
        
        #[Required]
        public Email $email,
        
        public ?Address $billingAddress = null,
        public ?Address $shippingAddress = null,
        
        /** @var Order[] */
        public array $orders = [],
        
        public DateTime $createdAt = new DateTime(),
        public ?DateTime $updatedAt = null
    ) {}
    
    public function placeOrder(array $items, Address $shippingAddress): Order
    {
        $order = Order::create($this->id, $items, $shippingAddress);
        
        return $this->with([
            'orders' => [...$this->orders, $order],
            'updatedAt' => new DateTime()
        ]);
    }
    
    public function updateBillingAddress(Address $address): Customer
    {
        return $this->with([
            'billingAddress' => $address,
            'updatedAt' => new DateTime()
        ]);
    }
    
    public function getTotalSpent(): Money
    {
        $total = new Money(0.0, 'USD');
        
        foreach ($this->orders as $order) {
            if ($order->status === OrderStatus::COMPLETED) {
                $total = $total->add($order->getTotal());
            }
        }
        
        return $total;
    }
}

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
}

final readonly class OrderItem extends Granite
{
    public function __construct(
        #[Required]
        public int $productId,
        
        #[Required]
        #[StringType]
        public string $productName,
        
        #[Required]
        #[Min(1)]
        public int $quantity,
        
        #[Required]
        public Money $unitPrice
    ) {}
    
    public function getTotal(): Money
    {
        return $this->unitPrice->multiply($this->quantity);
    }
}

final readonly class Order extends Granite
{
    public function __construct(
        public ?int $id,
        
        #[Required]
        public int $customerId,
        
        #[Required]
        /** @var OrderItem[] */
        public array $items,
        
        #[Required]
        public Address $shippingAddress,
        
        #[Required]
        public OrderStatus $status,
        
        public DateTime $createdAt = new DateTime(),
        public ?DateTime $updatedAt = null
    ) {}
    
    public static function create(int $customerId, array $items, Address $shippingAddress): self
    {
        return new self(
            id: null,
            customerId: $customerId,
            items: $items,
            shippingAddress: $shippingAddress,
            status: OrderStatus::PENDING,
            createdAt: new DateTime()
        );
    }
    
    public function getTotal(): Money
    {
        $total = new Money(0.0, 'USD');
        
        foreach ($this->items as $item) {
            $total = $total->add($item->getTotal());
        }
        
        return $total;
    }
    
    public function confirm(): Order
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new DomainException('Only pending orders can be confirmed');
        }
        
        return $this->with([
            'status' => OrderStatus::CONFIRMED,
            'updatedAt' => new DateTime()
        ]);
    }
    
    public function ship(): Order
    {
        if ($this->status !== OrderStatus::CONFIRMED) {
            throw new DomainException('Only confirmed orders can be shipped');
        }
        
        return $this->with([
            'status' => OrderStatus::SHIPPED,
            'updatedAt' => new DateTime()
        ]);
    }
}
```

## API Design Patterns

### Request/Response DTOs

```php
<?php

// API Request DTOs
final readonly class CreateProductRequest extends Granite
{
    public function __construct(
        #[Required]
        #[StringType]
        #[Min(3)]
        #[Max(100)]
        public string $name,
        
        #[StringType]
        #[Max(1000)]
        public ?string $description = null,
        
        #[Required]
        #[NumberType]
        #[Min(0.01)]
        public float $price,
        
        #[Required]
        #[StringType]
        public string $currency,
        
        #[ArrayType]
        #[Each(new StringType())]
        public array $categories = [],
        
        #[ArrayType]
        #[Each(new Url())]
        public array $images = []
    ) {}
}

final readonly class UpdateProductRequest extends Granite
{
    public function __construct(
        #[StringType]
        #[Min(3)]
        #[Max(100)]
        public ?string $name = null,
        
        #[StringType]
        #[Max(1000)]
        public ?string $description = null,
        
        #[NumberType]
        #[Min(0.01)]
        public ?float $price = null,
        
        #[ArrayType]
        public ?array $categories = null
    ) {}
}

// API Response DTOs
final readonly class ProductResponse extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public float $price,
        public string $currency,
        public array $categories,
        public array $images,
        
        #[SerializedName('created_at')]
        public DateTime $createdAt,
        
        #[SerializedName('updated_at')]
        public ?DateTime $updatedAt
    ) {}
    
    public static function fromEntity(Product $product, AutoMapper $mapper): self
    {
        return $mapper->map($product, self::class);
    }
}

final readonly class ApiResponse extends Granite
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $message = null,
        public ?array $errors = null,
        public ?array $meta = null
    ) {}
    
    public static function success(mixed $data = null, ?string $message = null, ?array $meta = null): self
    {
        return new self(
            success: true,
            data: $data,
            message: $message,
            meta: $meta
        );
    }
    
    public static function error(string $message, ?array $errors = null, mixed $data = null): self
    {
        return new self(
            success: false,
            data: $data,
            message: $message,
            errors: $errors
        );
    }
}

final readonly class PaginatedResponse extends Granite
{
    public function __construct(
        public array $data,
        
        #[SerializedName('current_page')]
        public int $currentPage,
        
        #[SerializedName('per_page')]
        public int $perPage,
        
        #[SerializedName('total_items')]
        public int $totalItems,
        
        #[SerializedName('total_pages')]
        public int $totalPages,
        
        #[SerializedName('has_next_page')]
        public bool $hasNextPage,
        
        #[SerializedName('has_prev_page')]
        public bool $hasPrevPage
    ) {}
    
    public static function create(array $data, int $page, int $perPage, int $total): self
    {
        $totalPages = (int) ceil($total / $perPage);
        
        return new self(
            data: $data,
            currentPage: $page,
            perPage: $perPage,
            totalItems: $total,
            totalPages: $totalPages,
            hasNextPage: $page < $totalPages,
            hasPrevPage: $page > 1
        );
    }
}
```

### API Controllers

```php
<?php

final readonly class ProductController
{
    public function __construct(
        private ProductService $productService,
        private AutoMapper $mapper
    ) {}
    
    public function create(array $requestData): ApiResponse
    {
        try {
            $request = CreateProductRequest::from($requestData);
            $product = $this->productService->createProduct($request);
            $response = ProductResponse::fromEntity($product, $this->mapper);
            
            return ApiResponse::success($response, 'Product created successfully');
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->getErrors());
        } catch (DomainException $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
    
    public function update(int $id, array $requestData): ApiResponse
    {
        try {
            $request = UpdateProductRequest::from($requestData);
            $product = $this->productService->updateProduct($id, $request);
            $response = ProductResponse::fromEntity($product, $this->mapper);
            
            return ApiResponse::success($response, 'Product updated successfully');
        } catch (NotFoundException $e) {
            return ApiResponse::error('Product not found');
        } catch (ValidationException $e) {
            return ApiResponse::error('Validation failed', $e->getErrors());
        }
    }
    
    public function list(array $queryParams): ApiResponse
    {
        try {
            $request = ListProductsRequest::from($queryParams);
            $result = $this->productService->listProducts($request);
            
            $products = array_map(
                fn($product) => ProductResponse::fromEntity($product, $this->mapper),
                $result['products']
            );
            
            $response = PaginatedResponse::create(
                $products,
                $request->page,
                $request->perPage,
                $result['total']
            );
            
            return ApiResponse::success($response);
        } catch (ValidationException $e) {
            return ApiResponse::error('Invalid query parameters', $e->getErrors());
        }
    }
}
```

## Performance Considerations

### Lazy Loading with DTOs

```php
<?php

final readonly class LazyUserResponse extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        
        // Lazy-loaded properties
        private ?Closure $profileLoader = null,
        private ?Closure $ordersLoader = null,
        
        // Cached data
        private ?UserProfile $profile = null,
        private ?array $orders = null
    ) {}
    
    public function getProfile(): ?UserProfile
    {
        if ($this->profile === null && $this->profileLoader !== null) {
            $this->profile = ($this->profileLoader)();
        }
        
        return $this->profile;
    }
    
    public function getOrders(): array
    {
        if ($this->orders === null && $this->ordersLoader !== null) {
            $this->orders = ($this->ordersLoader)();
        }
        
        return $this->orders ?? [];
    }
    
    public static function create(
        array $userData,
        ?Closure $profileLoader = null,
        ?Closure $ordersLoader = null
    ): self {
        return new self(
            id: $userData['id'],
            name: $userData['name'],
            email: $userData['email'],
            profileLoader: $profileLoader,
            ordersLoader: $ordersLoader
        );
    }
}

// Usage
$user = LazyUserResponse::create(
    $userData,
    profileLoader: fn() => $this->profileService->getByUserId($userData['id']),
    ordersLoader: fn() => $this->orderService->getByUserId($userData['id'])
);
```

### Bulk Operations

```php
<?php

final readonly class BulkCreateUsersRequest extends Granite
{
    public function __construct(
        #[Required]
        #[ArrayType]
        #[Each([
            new Required(),
            new Callback(fn($item) => CreateUserRequest::from($item))
        ])]
        public array $users
    ) {}
}

final readonly class BulkOperationResult extends Granite
{
    public function __construct(
        #[SerializedName('total_requested')]
        public int $totalRequested,
        
        #[SerializedName('successful_count')]
        public int $successfulCount,
        
        #[SerializedName('failed_count')]
        public int $failedCount,
        
        public array $successful = [],
        public array $failed = []
    ) {}
}

final readonly class UserService
{
    public function bulkCreateUsers(BulkCreateUsersRequest $request): BulkOperationResult
    {
        $successful = [];
        $failed = [];
        
        foreach ($request->users as $index => $userData) {
            try {
                $userRequest = CreateUserRequest::from($userData);
                $user = $this->createUser($userRequest);
                $successful[] = [
                    'index' => $index,
                    'data' => UserResponse::fromEntity($user, $this->mapper)
                ];
            } catch (Exception $e) {
                $failed[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'data' => $userData
                ];
            }
        }
        
        return new BulkOperationResult(
            totalRequested: count($request->users),
            successfulCount: count($successful),
            failedCount: count($failed),
            successful: $successful,
            failed: $failed
        );
    }
}
```

### Caching Strategies

```php
<?php

final readonly class CachedRepository
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private CacheInterface $cache,
        private AutoMapper $mapper,
        private int $ttl = 3600
    ) {}
    
    public function findById(int $id): ?UserEntity
    {
        $cacheKey = "user:{$id}";
        
        // Try cache first
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return UserEntity::from($cached);
        }
        
        // Load from repository
        $user = $this->repository->findById($id);
        if ($user !== null) {
            // Cache the array representation
            $this->cache->set($cacheKey, $user->array(), $this->ttl);
        }
        
        return $user;
    }
    
    public function save(UserEntity $user): UserEntity
    {
        $savedUser = $this->repository->save($user);
        
        // Update cache
        if ($savedUser->id !== null) {
            $cacheKey = "user:{$savedUser->id}";
            $this->cache->set($cacheKey, $savedUser->array(), $this->ttl);
        }
        
        return $savedUser;
    }
}
```

## Testing Strategies

### Unit Testing with DTOs

```php
<?php

class UserTest extends PHPUnit\Framework\TestCase
{
    public function testUserCreation(): void
    {
        $userData = [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'createdAt' => '2023-01-15T10:30:00Z'
        ];
        
        $user = UserEntity::from($userData);
        
        $this->assertEquals(1, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertInstanceOf(DateTime::class, $user->createdAt);
    }
    
    public function testUserValidation(): void
    {
        $this->expectException(ValidationException::class);
        
        UserEntity::from([
            'name' => 'X', // Too short
            'email' => 'invalid-email', // Invalid format
        ]);
    }
    
    public function testUserImmutability(): void
    {
        $user = UserEntity::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'createdAt' => '2023-01-15T10:30:00Z'
        ]);
        
        $updatedUser = $user->with(['name' => 'Jane Doe']);
        
        // Original unchanged
        $this->assertEquals('John Doe', $user->name);
        // New instance with change
        $this->assertEquals('Jane Doe', $updatedUser->name);
        // Other properties unchanged
        $this->assertEquals($user->id, $updatedUser->id);
        $this->assertEquals($user->email, $updatedUser->email);
    }
}
```

### Integration Testing

```php
<?php

class UserServiceIntegrationTest extends PHPUnit\Framework\TestCase
{
    private UserService $userService;
    private AutoMapper $mapper;
    
    protected function setUp(): void
    {
        $this->mapper = new AutoMapper();
        $this->userService = new UserService(
            new InMemoryUserRepository(),
            new BCryptPasswordHasher(),
            new NullEventDispatcher(),
            $this->mapper
        );
    }
    
    public function testCreateUserWorkflow(): void
    {
        $request = CreateUserRequest::from([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123',
            'roles' => ['user']
        ]);
        
        $user = $this->userService->createUser($request);
        
        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertNotNull($user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertInstanceOf(DateTime::class, $user->createdAt);
    }
}
```

### Testing Mapping Configurations

```php
<?php

class MappingTest extends PHPUnit\Framework\TestCase
{
    private AutoMapper $mapper;
    
    protected function setUp(): void
    {
        $this->mapper = new AutoMapper([
            new UserMappingProfile()
        ]);
    }
    
    public function testUserEntityToResponseMapping(): void
    {
        $userEntity = UserEntity::from([
            'userId' => 1,
            'fullName' => 'John Doe',
            'emailAddress' => 'john@example.com',
            'createdAt' => '2023-01-15T10:30:00Z'
        ]);
        
        $response = $this->mapper->map($userEntity, UserResponse::class);
        
        $this->assertInstanceOf(UserResponse::class, $response);
        $this->assertEquals(1, $response->id);
        $this->assertEquals('John Doe', $response->name);
        $this->assertEquals('john@example.com', $response->email);
    }
    
    public function testMappingIgnoresHiddenProperties(): void
    {
        $userEntity = UserEntity::from([
            'userId' => 1,
            'fullName' => 'John Doe',
            'emailAddress' => 'john@example.com',
            'password' => 'secret123',
            'createdAt' => '2023-01-15T10:30:00Z'
        ]);
        
        $response = $this->mapper->map($userEntity, PublicUserResponse::class);
        $responseArray = $response->array();
        
        $this->assertArrayNotHasKey('password', $responseArray);
    }
}
```

### Testing Object Comparison ✨ NEW

```php
<?php

class ObjectComparisonTest extends PHPUnit\Framework\TestCase
{
    public function testEqualsDetectsIdenticalObjects(): void
    {
        $user1 = UserEntity::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'createdAt' => '2023-01-15T10:30:00Z'
        ]);

        $user2 = UserEntity::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'createdAt' => '2023-01-15T10:30:00Z'
        ]);

        $this->assertTrue($user1->equals($user2));
    }

    public function testDiffersShowsChangedProperties(): void
    {
        $original = UserEntity::from([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $modified = UserEntity::from([
            'id' => 1,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        $differences = $original->differs($modified);

        $this->assertArrayHasKey('name', $differences);
        $this->assertEquals('John Doe', $differences['name']['current']);
        $this->assertEquals('Jane Doe', $differences['name']['new']);

        $this->assertArrayHasKey('email', $differences);
        $this->assertEquals('john@example.com', $differences['email']['current']);
        $this->assertEquals('jane@example.com', $differences['email']['new']);
    }

    public function testDiffersWithNestedObjects(): void
    {
        $address1 = Address::from(['street' => '123 Main St', 'city' => 'New York']);
        $address2 = Address::from(['street' => '456 Oak Ave', 'city' => 'New York']);

        $company1 = Company::from(['name' => 'Acme', 'address' => $address1]);
        $company2 = Company::from(['name' => 'Acme', 'address' => $address2]);

        $differences = $company1->differs($company2);

        $this->assertArrayHasKey('address', $differences);
        $this->assertArrayHasKey('street', $differences['address']);
        $this->assertEquals('123 Main St', $differences['address']['street']['current']);
        $this->assertEquals('456 Oak Ave', $differences['address']['street']['new']);
    }
}
```

## Object Comparison Patterns ✨ NEW

### Change Tracking and Auditing

```php
<?php

final readonly class AuditLog extends Granite
{
    public function __construct(
        public int $id,
        public string $entityType,
        public int $entityId,
        public string $action,
        public array $changes,
        public int $userId,
        public DateTime $createdAt
    ) {}
}

final readonly class AuditService
{
    public function __construct(
        private AuditLogRepository $auditLogRepository
    ) {}

    public function trackChanges(
        string $entityType,
        int $entityId,
        Granite $original,
        Granite $modified,
        int $userId
    ): ?AuditLog {
        $differences = $original->differs($modified);

        if (empty($differences)) {
            return null; // No changes to track
        }

        $auditLog = new AuditLog(
            id: null,
            entityType: $entityType,
            entityId: $entityId,
            action: 'update',
            changes: $differences,
            userId: $userId,
            createdAt: new DateTime()
        );

        return $this->auditLogRepository->save($auditLog);
    }

    public function getChangeHistory(string $entityType, int $entityId): array
    {
        return $this->auditLogRepository->findByEntity($entityType, $entityId);
    }
}

// Usage in service layer
final readonly class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuditService $auditService
    ) {}

    public function updateUser(int $id, UpdateUserRequest $request, int $currentUserId): UserEntity
    {
        $original = $this->userRepository->findById($id);
        if (!$original) {
            throw NotFoundException::forResource('User', $id);
        }

        $modified = $original->with([
            'name' => $request->name ?? $original->name,
            'email' => $request->email ?? $original->email,
            'updatedAt' => new DateTime()
        ]);

        // Track changes before saving
        $this->auditService->trackChanges(
            'User',
            $id,
            $original,
            $modified,
            $currentUserId
        );

        return $this->userRepository->save($modified);
    }
}
```

### Optimistic Locking with Version Comparison

```php
<?php

final readonly class VersionedEntity extends Granite
{
    public function __construct(
        public int $id,
        public string $data,
        public int $version,
        public DateTime $updatedAt
    ) {}
}

final readonly class OptimisticLockException extends DomainException
{
    public function __construct(
        public readonly array $conflicts
    ) {
        parent::__construct(
            'Optimistic lock failed: data was modified by another process',
            'OPTIMISTIC_LOCK_FAILED',
            $conflicts
        );
    }
}

final readonly class VersionedRepository
{
    public function save(VersionedEntity $entity, VersionedEntity $originalEntity): VersionedEntity
    {
        // Load current version from database
        $currentEntity = $this->findById($entity->id);

        if (!$currentEntity->equals($originalEntity)) {
            // Detect what changed
            $conflicts = $originalEntity->differs($currentEntity);
            throw new OptimisticLockException($conflicts);
        }

        // Increment version and save
        $newEntity = $entity->with([
            'version' => $entity->version + 1,
            'updatedAt' => new DateTime()
        ]);

        return $this->persist($newEntity);
    }
}

// Usage
try {
    $original = $repository->findById($id);
    $modified = $original->with(['data' => 'new value']);

    $saved = $repository->save($modified, $original);
} catch (OptimisticLockException $e) {
    // Handle conflicts
    $conflicts = $e->conflicts;
    // Show user what changed and let them resolve
}
```

### State Machine with Transition Validation

```php
<?php

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
}

final readonly class Order extends Granite
{
    public function __construct(
        public int $id,
        public OrderStatus $status,
        public array $items,
        public Money $total,
        public DateTime $createdAt,
        public ?DateTime $updatedAt = null
    ) {}
}

final readonly class InvalidStateTransitionException extends DomainException
{
    public static function create(OrderStatus $from, OrderStatus $to, array $changes): self
    {
        return new self(
            message: "Invalid transition from {$from->value} to {$to->value}",
            domainCode: 'INVALID_STATE_TRANSITION',
            context: [
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changes' => $changes
            ]
        );
    }
}

final readonly class OrderStateMachine
{
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => []
    ];

    public function validateTransition(Order $from, Order $to): void
    {
        $differences = $from->differs($to);

        // Ensure only status changed
        if (count($differences) !== 1 || !isset($differences['status'])) {
            throw new InvalidArgumentException('Only status should change during state transition');
        }

        $fromStatus = $differences['status']['current'];
        $toStatus = $differences['status']['new'];

        $allowedStates = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        if (!in_array($toStatus, $allowedStates)) {
            throw InvalidStateTransitionException::create(
                OrderStatus::from($fromStatus),
                OrderStatus::from($toStatus),
                $differences
            );
        }
    }

    public function transition(Order $order, OrderStatus $newStatus): Order
    {
        $newOrder = $order->with([
            'status' => $newStatus,
            'updatedAt' => new DateTime()
        ]);

        $this->validateTransition($order, $newOrder);

        return $newOrder;
    }
}

// Usage
$stateMachine = new OrderStateMachine();

try {
    $order = Order::from(['id' => 1, 'status' => OrderStatus::PENDING, ...]);
    $confirmedOrder = $stateMachine->transition($order, OrderStatus::CONFIRMED);

    // This would throw exception
    $invalidOrder = $stateMachine->transition($order, OrderStatus::DELIVERED);
} catch (InvalidStateTransitionException $e) {
    echo $e->getMessage();
    // "Invalid transition from pending to delivered"
}
```

### Cache Invalidation Strategy

```php
<?php

final readonly class CachedUserData extends Granite
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public DateTime $cachedAt,
        public int $ttl = 3600
    ) {}

    public function isExpired(): bool
    {
        $expiresAt = (clone $this->cachedAt)->modify("+{$this->ttl} seconds");
        return new DateTime() > $expiresAt;
    }
}

final readonly class SmartCacheService
{
    public function __construct(
        private CacheInterface $cache,
        private UserRepositoryInterface $userRepository
    ) {}

    public function getUser(int $id): UserEntity
    {
        $cacheKey = "user:{$id}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $cachedData = CachedUserData::from($cached);

            if (!$cachedData->isExpired()) {
                // Load fresh data to compare
                $fresh = $this->userRepository->findById($id);

                if ($fresh === null) {
                    $this->cache->delete($cacheKey);
                    throw NotFoundException::forResource('User', $id);
                }

                // Check if data actually changed
                $cachedUser = UserEntity::from([
                    'id' => $cachedData->id,
                    'name' => $cachedData->name,
                    'email' => $cachedData->email
                ]);

                if ($cachedUser->equals($fresh)) {
                    // Data unchanged, update cache timestamp only
                    $refreshedCache = $cachedData->with([
                        'cachedAt' => new DateTime()
                    ]);
                    $this->cache->set($cacheKey, $refreshedCache->array(), $cachedData->ttl);
                    return $fresh;
                }

                // Data changed, update cache with new data
                $newCachedData = new CachedUserData(
                    id: $fresh->id,
                    name: $fresh->name,
                    email: $fresh->email,
                    cachedAt: new DateTime(),
                    ttl: 3600
                );
                $this->cache->set($cacheKey, $newCachedData->array(), $newCachedData->ttl);

                return $fresh;
            }
        }

        // Cache miss or expired, load from repository
        $user = $this->userRepository->findById($id);
        if ($user !== null) {
            $cacheData = new CachedUserData(
                id: $user->id,
                name: $user->name,
                email: $user->email,
                cachedAt: new DateTime(),
                ttl: 3600
            );
            $this->cache->set($cacheKey, $cacheData->array(), $cacheData->ttl);
        }

        return $user;
    }
}
```

### Conflict Resolution in Distributed Systems

```php
<?php

final readonly class ConflictResolution extends Granite
{
    public function __construct(
        public string $strategy, // 'client_wins', 'server_wins', 'merge', 'manual'
        public array $conflicts,
        public ?array $resolution = null
    ) {}
}

final readonly class DistributedDataService
{
    public function syncData(Granite $clientData, Granite $serverData): ConflictResolution
    {
        if ($clientData->equals($serverData)) {
            return new ConflictResolution(
                strategy: 'no_conflict',
                conflicts: []
            );
        }

        $differences = $clientData->differs($serverData);

        // Auto-resolve simple cases
        if ($this->canAutoResolve($differences)) {
            $resolved = $this->autoResolve($clientData, $serverData, $differences);
            return new ConflictResolution(
                strategy: 'merge',
                conflicts: $differences,
                resolution: $resolved->array()
            );
        }

        // Require manual resolution
        return new ConflictResolution(
            strategy: 'manual',
            conflicts: $differences
        );
    }

    private function canAutoResolve(array $differences): bool
    {
        // Only non-overlapping changes can be auto-merged
        foreach ($differences as $field => $change) {
            if (is_array($change) && isset($change['current']) && isset($change['new'])) {
                // Simple field change - can't auto-resolve
                return false;
            }
        }
        return true;
    }

    private function autoResolve(Granite $client, Granite $server, array $differences): Granite
    {
        // Merge non-conflicting changes
        $mergedData = $server->array();

        foreach ($differences as $field => $change) {
            // Apply client changes that don't conflict
            if (!isset($change['current'])) {
                $mergedData[$field] = $client->array()[$field];
            }
        }

        return $client::from($mergedData);
    }
}
```

## Error Handling Strategies

### Comprehensive Error Handling

```php
<?php

final readonly class ErrorResponse extends Granite
{
    public function __construct(
        public string $type,
        public string $message,
        public ?array $details = null,
        public ?string $code = null,
        
        #[SerializedName('request_id')]
        public ?string $requestId = null
    ) {}
}

final readonly class ValidationErrorDetail extends Granite
{
    public function __construct(
        public string $field,
        public array $messages,
        
        #[SerializedName('rejected_value')]
        public mixed $rejectedValue = null
    ) {}
}

final readonly class ApiExceptionHandler
{
    public function handle(Throwable $exception, string $requestId): ErrorResponse
    {
        return match (true) {
            $exception instanceof ValidationException => $this->handleValidationException($exception, $requestId),
            $exception instanceof MappingException => $this->handleMappingException($exception, $requestId),
            $exception instanceof SerializationException => $this->handleSerializationException($exception, $requestId),
            $exception instanceof NotFoundException => $this->handleNotFoundException($exception, $requestId),
            $exception instanceof DomainException => $this->handleDomainException($exception, $requestId),
            default => $this->handleGenericException($exception, $requestId)
        };
    }
    
    private function handleValidationException(ValidationException $e, string $requestId): ErrorResponse
    {
        $details = [];
        foreach ($e->getErrors() as $field => $messages) {
            $details[] = new ValidationErrorDetail($field, $messages);
        }
        
        return new ErrorResponse(
            type: 'validation_error',
            message: 'The request data is invalid',
            details: array_map(fn($detail) => $detail->array(), $details),
            code: 'VALIDATION_FAILED',
            requestId: $requestId
        );
    }
    
    private function handleMappingException(MappingException $e, string $requestId): ErrorResponse
    {
        return new ErrorResponse(
            type: 'mapping_error',
            message: 'Failed to transform data',
            details: [
                'source_type' => $e->getSourceType(),
                'destination_type' => $e->getDestinationType(),
                'property' => $e->getPropertyName()
            ],
            code: 'MAPPING_FAILED',
            requestId: $requestId
        );
    }
    
    private function handleNotFoundException(NotFoundException $e, string $requestId): ErrorResponse
    {
        return new ErrorResponse(
            type: 'not_found',
            message: $e->getMessage(),
            code: 'RESOURCE_NOT_FOUND',
            requestId: $requestId
        );
    }
    
    private function handleDomainException(DomainException $e, string $requestId): ErrorResponse
    {
        return new ErrorResponse(
            type: 'business_rule_violation',
            message: $e->getMessage(),
            code: 'BUSINESS_RULE_VIOLATION',
            requestId: $requestId
        );
    }
    
    private function handleGenericException(Throwable $e, string $requestId): ErrorResponse
    {
        // Log the actual exception for debugging
        error_log("Unhandled exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        return new ErrorResponse(
            type: 'internal_error',
            message: 'An internal error occurred',
            code: 'INTERNAL_ERROR',
            requestId: $requestId
        );
    }
}
```

### Custom Exceptions

```php
<?php

final class DomainException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $domainCode = null,
        private readonly array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
    
    public function getDomainCode(): ?string
    {
        return $this->domainCode;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
}

final class NotFoundException extends DomainException
{
    public static function forResource(string $resource, mixed $identifier): self
    {
        return new self(
            message: "{$resource} not found",
            domainCode: 'RESOURCE_NOT_FOUND',
            context: [
                'resource' => $resource,
                'identifier' => $identifier
            ]
        );
    }
}

final class BusinessRuleViolationException extends DomainException
{
    public static function forRule(string $rule, array $context = []): self
    {
        return new self(
            message: "Business rule violation: {$rule}",
            domainCode: 'BUSINESS_RULE_VIOLATION',
            context: $context
        );
    }
}
```

## Integration Patterns

### Event Sourcing with DTOs

```php
<?php

abstract readonly class DomainEvent extends Granite
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
        public DateTime $occurredAt,
        
        #[SerializedName('event_version')]
        public int $eventVersion = 1
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
        public string $email,
        
        public array $roles = ['user']
    ) {
        parent::__construct($eventId, $aggregateId, 'user_created', $occurredAt);
    }
}

final readonly class UserEmailChangedEvent extends DomainEvent
{
    public function __construct(
        string $eventId,
        string $aggregateId,
        DateTime $occurredAt,
        
        #[Required]
        #[SerializedName('old_email')]
        public string $oldEmail,
        
        #[Required]
        #[SerializedName('new_email')]
        public string $newEmail
    ) {
        parent::__construct($eventId, $aggregateId, 'user_email_changed', $occurredAt);
    }
}

final readonly class EventStore
{
    public function append(DomainEvent $event): void
    {
        $eventData = $event->array();
        // Store event in database/event store
    }
    
    public function getEventsForAggregate(string $aggregateId): array
    {
        // Load events from storage and convert back to objects
        $events = [];
        foreach ($this->loadEventsFromDatabase($aggregateId) as $eventData) {
            $events[] = $this->deserializeEvent($eventData);
        }
        return $events;
    }
    
    private function deserializeEvent(array $eventData): DomainEvent
    {
        return match ($eventData['event_type']) {
            'user_created' => UserCreatedEvent::from($eventData),
            'user_email_changed' => UserEmailChangedEvent::from($eventData),
            default => throw new InvalidArgumentException('Unknown event type')
        };
    }
}
```

This comprehensive guide shows how Granite can be used in complex, real-world applications with proper architectural patterns, domain modeling, and error handling strategies.