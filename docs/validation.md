# Validation

Granite provides a comprehensive validation system to ensure data integrity. The system supports both attribute-based validation (recommended) and method-based validation for complex scenarios.

## Table of Contents

- [Basic Validation](#basic-validation)
- [Available Validation Rules](#available-validation-rules)
- [Custom Error Messages](#custom-error-messages)
- [Method-based Validation](#method-based-validation)
- [Custom Validation Rules](#custom-validation-rules)
- [Conditional Validation](#conditional-validation)
- [Collection Validation](#collection-validation)
- [Error Handling](#error-handling)

## Basic Validation

Use validation attributes on your Granite objects to ensure data integrity:

```php
<?php

use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Max;
use Ninja\Granite\Validation\Attributes\StringType;

final readonly class User extends Granite
{
    public function __construct(
        #[Required]
        #[StringType]
        #[Min(2)]
        #[Max(50)]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Min(18)]
        #[Max(120)]
        public ?int $age = null,
        
        #[StringType]
        #[Min(8)]
        public ?string $password = null
    ) {}
}

// This will validate automatically
$user = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'password' => 'secret123'
]);

// This will throw ValidationException
try {
    $invalidUser = User::from([
        'name' => 'X',  // Too short
        'email' => 'invalid-email',  // Invalid format
        'age' => 15  // Too young
    ]);
} catch (InvalidArgumentException $e) {
    // Handle validation errors
    echo $e->getMessage();
}
```

## Available Validation Rules

### Type Validation

#### `#[Required]`
Ensures the field is not null.

```php
#[Required]
public string $name;
```

#### `#[StringType]`
Validates that the value is a string.

```php
#[StringType]
public ?string $description;
```

#### `#[IntegerType]`
Validates that the value is an integer.

```php
#[IntegerType]
public ?int $count;
```

#### `#[NumberType]`
Validates that the value is a number (int or float).

```php
#[NumberType]
public ?float $price;
```

#### `#[BooleanType]`
Validates that the value is a boolean.

```php
#[BooleanType]
public ?bool $active;
```

#### `#[ArrayType]`
Validates that the value is an array.

```php
#[ArrayType]
public ?array $tags;
```

### Length and Range Validation

#### `#[Min]`
Sets minimum value for numbers or minimum length for strings/arrays.

```php
#[Min(1)]
public int $quantity;

#[Min(3)]
public string $username;

#[Min(1)]
public array $items;
```

#### `#[Max]`
Sets maximum value for numbers or maximum length for strings/arrays.

```php
#[Max(100)]
public int $percentage;

#[Max(255)]
public string $title;

#[Max(10)]
public array $categories;
```

### Format Validation

#### `#[Email]`
Validates email address format.

```php
#[Email]
public string $email;
```

#### `#[Url]`
Validates URL format.

```php
#[Url]
public string $website;
```

#### `#[IpAddress]`
Validates IP address format.

```php
#[IpAddress]
public string $serverIp;
```

#### `#[Regex]`
Validates against a regular expression pattern.

```php
#[Regex('/^[A-Z]{2,3}-\d{4}$/')]
public string $productCode;
```

### Choice Validation

#### `#[In]`
Validates that the value is in a list of allowed values.

```php
#[In(['active', 'inactive', 'pending'])]
public string $status;

#[In([1, 2, 3, 4, 5])]
public int $rating;
```

#### `#[EnumType]`
Validates enum values.

```php
enum Status: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

#[EnumType(Status::class)]
public Status $status;
```

### Collection Validation

#### `#[Each]`
Validates each item in an array.

```php
use Ninja\Granite\Validation\Attributes\Each;
use Ninja\Granite\Validation\Rules\Email;

#[Each(new Email())]
public array $emails;

// Or with multiple rules
#[Each([new StringType(), new Min(3)])]
public array $names;
```

### Conditional Validation

#### `#[When]`
Applies validation only when a condition is met.

```php
#[When(
    condition: fn($value, $data) => $data['type'] === 'premium',
    rule: new Min(100)
)]
public ?float $premiumAmount;
```

### Custom Validation

#### `#[Callback]`
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

## Custom Error Messages

All validation attributes accept a custom error message:

```php
#[Required('The user name is mandatory')]
#[Min(2, 'Name must have at least 2 characters')]
#[Max(50, 'Name cannot exceed 50 characters')]
public string $name;

#[Email('Please provide a valid email address')]
public string $email;
```

## Method-based Validation

For complex validation scenarios, you can use method-based rules:

```php
final readonly class ComplexUser extends Granite
{
    public function __construct(
        public string $username,
        public string $email,
        public string $password,
        public string $confirmPassword
    ) {}

    protected static function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:20',
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'confirmPassword' => [
                'required',
                'string',
                new Rules\Callback(function($value, $allData) {
                    return $value === $allData['password'];
                }, 'Passwords must match')
            ]
        ];
    }
}
```

### String Format Rules

You can use pipe-separated string rules:

```php
protected static function rules(): array
{
    return [
        'name' => 'required|string|min:2|max:50',
        'age' => 'integer|min:18|max:120',
        'email' => 'required|email',
        'website' => 'url',
        'tags' => 'array',
        'status' => 'in:active,inactive,pending'
    ];
}
```

## Custom Validation Rules

Create custom validation rules by implementing `ValidationRule`:

```php
<?php

use Ninja\Granite\Validation\Rules\AbstractRule;

class UniqueUsername extends AbstractRule
{
    public function __construct(private UserRepository $userRepo) {}

    public function validate(mixed $value, ?array $allData = null): bool
    {
        if ($value === null) {
            return true;
        }

        return !$this->userRepo->existsByUsername($value);
    }

    protected function defaultMessage(string $property): string
    {
        return sprintf('The %s is already taken', $property);
    }
}

// Usage in VO
#[Required]
#[StringType]
public string $username;

protected static function rules(): array
{
    return [
        'username' => [
            'required',
            'string',
            new UniqueUsername(new UserRepository())
        ]
    ];
}
```

## Conditional Validation

### Using Callbacks

```php
#[When(
    condition: fn($value, $data) => isset($data['country']) && $data['country'] === 'US',
    rule: new Regex('/^\d{5}(-\d{4})?$/')  // US ZIP code format
)]
public ?string $postalCode;
```

### Using Method Conditions

```php
#[When(
    condition: [$this, 'requiresValidation'],
    rule: new Required()
)]
public ?string $conditionalField;

private function requiresValidation($value, $data): bool
{
    return $data['type'] === 'required_type';
}
```

## Collection Validation

### Validating Array Items

```php
// Validate that all emails in array are valid
#[Each(new Email())]
public array $emails;

// Multiple rules for each item
#[Each([
    new Required(),
    new StringType(),
    new Min(3)
])]
public array $names;

// Nested object validation
#[Each(new Rules\Callback(function($item) {
    return UserAddress::from($item); // This will validate each address
}))]
public array $addresses;
```

### Complex Collection Validation

```php
final readonly class OrderItem extends Granite
{
    public function __construct(
        #[Required]
        #[StringType]
        public string $name,
        
        #[Required]
        #[NumberType]
        #[Min(0.01)]
        public float $price,
        
        #[Required]
        #[IntegerType]
        #[Min(1)]
        public int $quantity
    ) {}
}

final readonly class Order extends Granite
{
    public function __construct(
        #[Required]
        #[StringType]
        public string $customerName,
        
        #[Required]
        #[ArrayType]
        #[Each(new Rules\Callback(function($item) {
            return OrderItem::from($item); // Validates each order item
        }))]
        public array $items
    ) {}
}
```

## Error Handling

### ValidationException

When validation fails, a `ValidationException` is thrown with detailed error information:

```php
try {
    $user = User::from($invalidData);
} catch (ValidationException $e) {
    // Get all errors
    $errors = $e->getErrors();
    
    // Get errors for a specific field
    $nameErrors = $e->getFieldErrors('name');
    
    // Check if a field has errors
    if ($e->hasFieldErrors('email')) {
        echo "Email has validation errors";
    }
    
    // Get formatted error message
    echo $e->getFormattedMessage();
    
    // Get all error messages as array
    $messages = $e->getAllMessages();
}
```

### Error Structure

Validation errors are structured by field:

```php
$errors = [
    'name' => [
        'name must be at least 2 characters',
        'name must be at most 50 characters'
    ],
    'email' => [
        'email must be a valid email address'
    ],
    'age' => [
        'age must be at least 18'
    ]
];
```

### Custom Error Handling

```php
final readonly class UserService
{
    public function createUser(array $data): User
    {
        try {
            return User::from($data);
        } catch (ValidationException $e) {
            // Log validation errors
            $this->logger->warning('User validation failed', [
                'errors' => $e->getErrors(),
                'data' => $data
            ]);
            
            // Transform to API response format
            throw new ApiValidationException(
                message: 'Validation failed',
                errors: $this->transformErrors($e->getErrors())
            );
        }
    }
    
    private function transformErrors(array $errors): array
    {
        $result = [];
        foreach ($errors as $field => $fieldErrors) {
            $result[] = [
                'field' => $field,
                'messages' => $fieldErrors
            ];
        }
        return $result;
    }
}
```

## Best Practices

### 1. Use Appropriate Validation Rules

```php
// Good: Specific and meaningful validation
#[Required('Customer name is required')]
#[StringType('Name must be text')]
#[Min(2, 'Name must be at least 2 characters')]
#[Max(100, 'Name cannot exceed 100 characters')]
public string $customerName;

// Avoid: Too generic or no validation
public string $customerName;
```

### 2. Combine Rules Appropriately

```php
// Good: Logical combination
#[Required]
#[Email]
public string $email;

#[IntegerType]
#[Min(0)]
#[Max(5)]
public ?int $rating;

// Avoid: Contradictory rules
#[Required]
// ... but then allowing null in constructor
public ?string $requiredField = null;
```

### 3. Use Custom Messages for User-Facing Validation

```php
final readonly class UserRegistration extends Granite
{
    public function __construct(
        #[Required('Please enter your full name')]
        #[Min(2, 'Name must be at least 2 characters long')]
        public string $name,
        
        #[Required('Email address is required')]
        #[Email('Please enter a valid email address')]
        public string $email,
        
        #[Required('Password is required')]
        #[Min(8, 'Password must be at least 8 characters long')]
        #[Regex('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', 'Password must contain at least one lowercase letter, one uppercase letter, and one number')]
        public string $password
    ) {}
}
```

### 4. Group Related Validations

```php
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
        #[Min(2)]
        #[Max(2)]
        public string $state,
        
        #[Required]
        #[Regex('/^\d{5}(-\d{4})?$/')]
        public string $zipCode
    ) {}
}
```

This validation system ensures that your Granite objects maintain data integrity while providing clear, actionable error messages to help users correct their input.