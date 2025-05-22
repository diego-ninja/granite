# Validation

Granite provides a comprehensive validation system that works seamlessly with DTOs and Value Objects. You can define validation rules using both PHP 8 attributes and method-based configurations.

## Table of Contents

- [Basic Validation](#basic-validation)
- [Validation Attributes](#validation-attributes)
- [Method-based Rules](#method-based-rules)
- [Available Validation Rules](#available-validation-rules)
- [Custom Validation Messages](#custom-validation-messages)
- [Conditional Validation](#conditional-validation)
- [Array Validation](#array-validation)
- [Custom Validation Rules](#custom-validation-rules)
- [Validation Error Handling](#validation-error-handling)

## Basic Validation

Validation is automatically applied when creating Value Objects:

```php
<?php

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;

final readonly class User extends GraniteVO
{
    public function __construct(
        #[Required]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email
    ) {}
}

// This will pass validation
$validUser = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// This will throw InvalidArgumentException
try {
    $invalidUser = User::from([
        'name' => '',  // Required field is empty
        'email' => 'not-an-email'  // Invalid email format
    ]);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(); // Validation error details
}
```

## Validation Attributes

Use PHP 8 attributes to define validation rules directly on properties:

### Type Validation

```php
<?php

use Ninja\Granite\Validation\Attributes\*;

final readonly class Product extends GraniteVO
{
    public function __construct(
        #[Required]
        #[StringType]
        public string $name,
        
        #[NumberType]
        public float $price,
        
        #[IntegerType]
        public int $quantity,
        
        #[BooleanType]
        public bool $isActive,
        
        #[ArrayType]
        public array $tags
    ) {}
}
```

### Length and Size Validation

```php
<?php

final readonly class UserProfile extends GraniteVO
{
    public function __construct(
        #[Required]
        #[StringType]
        #[Min(2)]
        #[Max(50)]
        public string $name,
        
        #[StringType]
        #[Max(500)]
        public ?string $bio = null,
        
        #[ArrayType]
        #[Min(1)]
        #[Max(10)]
        public array $interests = []
    ) {}
}
```

### Format Validation

```php
<?php

final readonly class ContactInfo extends GraniteVO
{
    public function __construct(
        #[Required]
        #[Email]
        public string $email,
        
        #[Url]
        public ?string $website = null,
        
        #[IpAddress]
        public ?string $serverIp = null,
        
        #[Regex('/^\+?[1-9]\d{1,14}$/')]
        public ?string $phone = null
    ) {}
}
```

## Method-based Rules

Define validation rules using the `rules()` method:

```php
<?php

final readonly class Product extends GraniteVO
{
    public function __construct(
        public string $name,
        public string $sku,
        public float $price,
        public int $quantity,
        public string $category
    ) {}
    
    protected static function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'sku' => 'required|string|regex:/^[A-Z0-9]{10}$/',
            'price' => 'required|number|min:0.01',
            'quantity' => 'required|integer|min:0',
            'category' => 'required|in:electronics,clothing,books,home'
        ];
    }
}
```

### Mixed Approach

You can combine attributes and method-based rules:

```php
<?php

final readonly class BlogPost extends GraniteVO
{
    public function __construct(
        #[Required]  // Attribute validation
        #[StringType]
        public string $title,
        
        public string $content,
        public array $tags,
        public string $status
    ) {}
    
    protected static function rules(): array
    {
        return [
            // Method-based rules (override attributes if both are defined)
            'content' => 'required|string|min:10',
            'tags' => 'array|max:5',
            'status' => 'required|in:draft,published,archived'
        ];
    }
}
```

## Available Validation Rules

### Basic Type Rules

- **`Required`** - Value must not be null
- **`StringType`** - Value must be a string
- **`IntegerType`** - Value must be an integer
- **`NumberType`** - Value must be a number (int or float)
- **`BooleanType`** - Value must be a boolean
- **`ArrayType`** - Value must be an array

### Size and Length Rules

- **`Min(value)`** - Minimum value (numbers) or length (strings/arrays)
- **`Max(value)`** - Maximum value (numbers) or length (strings/arrays)

### Format Rules

- **`Email`** - Valid email address format
- **`Url`** - Valid URL format
- **`IpAddress`** - Valid IP address (IPv4 or IPv6)
- **`Regex(pattern)`** - Must match regular expression pattern

### Value Rules

- **`In(values)`** - Value must be one of the specified options
- **`EnumType(enumClass)`** - Value must be a valid enum case

### Special Rules

- **`Each(rules)`** - Apply validation to each item in an array
- **`When(condition, rule)`** - Conditional validation
- **`Callback(callable)`** - Custom validation function

## Custom Validation Messages

Customize error messages for better user experience:

```php
<?php

final readonly class User extends GraniteVO
{
    public function __construct(
        #[Required(message: "Please provide your name")]
        #[Min(2, message: "Name must be at least 2 characters long")]
        public string $name,
        
        #[Required(message: "Email address is required")]
        #[Email(message: "Please provide a valid email address")]
        public string $email,
        
        #[Min(18, message: "You must be at least 18 years old")]
        #[Max(120, message: "Please enter a valid age")]
        public ?int $age = null
    ) {}
}
```

## Conditional Validation

Apply validation rules based on conditions:

```php
<?php

use Ninja\Granite\Validation\Attributes\When;
use Ninja\Granite\Validation\Rules\Min;

final readonly class Subscription extends GraniteVO
{
    public function __construct(
        #[Required]
        #[In(['free', 'premium', 'enterprise'])]
        public string $type,
        
        #[When(
            condition: fn($value, $data) => $data['type'] !== 'free',
            rule: new Min(1),
            message: "Paid subscriptions must have a price"
        )]
        public ?float $price = null,
        
        #[When(
            condition: fn($value, $data) => $data['type'] === 'enterprise',
            rule: new Min(10),
            message: "Enterprise subscriptions require at least 10 users"
        )]
        public ?int $minUsers = null
    ) {}
}
```

## Array Validation

Validate arrays and their contents:

### Basic Array Validation

```php
<?php

use Ninja\Granite\Validation\Attributes\Each;
use Ninja\Granite\Validation\Rules\StringType;
use Ninja\Granite\Validation\Rules\Email;

final readonly class Newsletter extends GraniteVO
{
    public function __construct(
        #[Required]
        public string $subject,
        
        #[Required]
        #[ArrayType]
        #[Min(1, message: "At least one recipient is required")]
        #[Each([new Email()])]
        public array $recipients,
        
        #[ArrayType]
        #[Each([new StringType()])]
        public array $tags = []
    ) {}
}
```

### Complex Array Validation

```php
<?php

final readonly class Order extends GraniteVO
{
    public function __construct(
        #[Required]
        public string $orderNumber,
        
        #[Required]
        #[ArrayType]
        #[Min(1)]
        #[Each([
            new \Ninja\Granite\Validation\Rules\Callback(
                fn($item) => is_array($item) && 
                           isset($item['product_id']) && 
                           isset($item['quantity']) &&
                           $item['quantity'] > 0
            )
        ])]
        public array $items
    ) {}
}
```

## Custom Validation Rules

Create custom validation rules for specific business logic:

```php
<?php

use Ninja\Granite\Validation\Rules\AbstractRule;

class UniqueUsernameRule extends AbstractRule
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function validate(mixed $value, ?array $allData = null): bool
    {
        if ($value === null) {
            return true;
        }
        
        return !$this->userRepository->existsByUsername($value);
    }

    protected function defaultMessage(string $property): string
    {
        return sprintf("The username '%s' is already taken", $property);
    }
}

// Use in Value Object
final readonly class UserRegistration extends GraniteVO
{
    public function __construct(
        #[Required]
        #[Callback([UniqueUsernameRule::class, 'validate'])]
        public string $username,
        
        #[Required]
        #[Email]
        public string $email
    ) {}
}
```

### Callback Validation

For simple custom validation, use callbacks:

```php
<?php

final readonly class Product extends GraniteVO
{
    public function __construct(
        #[Required]
        public string $name,
        
        #[Required]
        #[Callback(
            callback: fn($value) => $value > 0,
            message: "Price must be greater than zero"
        )]
        public float $price,
        
        #[Callback(
            callback: fn($value) => in_array($value, ['new', 'used', 'refurbished']),
            message: "Condition must be new, used, or refurbished"
        )]
        public string $condition = 'new'
    ) {}
}
```

## Validation Error Handling

Handle validation errors gracefully:

```php
<?php

try {
    $user = User::from($inputData);
} catch (InvalidArgumentException $e) {
    // Parse the validation errors
    $message = $e->getMessage();
    
    // The message contains JSON with field-specific errors
    if (str_contains($message, 'Validation failed')) {
        $errorsPart = substr($message, strpos($message, '{'));
        $errors = json_decode($errorsPart, true);
        
        foreach ($errors as $field => $fieldErrors) {
            echo "Field '$field' has errors:\n";
            foreach ($fieldErrors as $error) {
                echo "  - $error\n";
            }
        }
    }
}
```

### Custom Error Handling

Create a validation service for better error handling:

```php
<?php

class ValidationService
{
    public static function validateAndFormat(array $data, string $voClass): array
    {
        try {
            $vo = $voClass::from($data);
            return ['success' => true, 'data' => $vo];
        } catch (InvalidArgumentException $e) {
            $errors = self::parseValidationErrors($e->getMessage());
            return ['success' => false, 'errors' => $errors];
        }
    }
    
    private static function parseValidationErrors(string $message): array
    {
        if (!str_contains($message, 'Validation failed')) {
            return ['general' => [$message]];
        }
        
        $errorsPart = substr($message, strpos($message, '{'));
        return json_decode($errorsPart, true) ?: ['general' => [$message]];
    }
}

// Usage
$result = ValidationService::validateAndFormat($inputData, User::class);

if ($result['success']) {
    $user = $result['data'];
    // Process valid user
} else {
    $errors = $result['errors'];
    // Handle validation errors
}
```

## Best Practices

1. **Use attributes for simple validation** - They're more readable and declarative
2. **Use method-based rules for complex scenarios** - When you need dynamic or context-dependent validation
3. **Provide meaningful error messages** - Help users understand what went wrong
4. **Validate at the boundary** - Use Value Objects to ensure data integrity from the start
5. **Group related validations** - Create specific Value Objects for different contexts
6. **Test validation thoroughly** - Ensure both valid and invalid scenarios are covered