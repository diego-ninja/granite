# 🪨 Granite

A lightweight zero-dependency PHP library for building immutable, serializable objects with validation capabilities.

## Overview

Granite is a powerful library for creating strongly typed, immutable Data Transfer Objects (DTOs) and Value Objects (VOs) in PHP. It provides a clean, attribute-based API for defining validation rules, serialization behavior, and type conversions.

## Features

- **Immutable objects**: Create read-only DTOs and Value Objects
- **Built-in validation**: Comprehensive validation system with many predefined rules
- **Attribute-based validation**: Use PHP 8 attributes to define validation rules directly on properties
- **Serialization control**: Customize property names during serialization/deserialization
- **Type conversion**: Automatic conversion of primitive types, DateTimes, and Enums
- **JSON support**: Easy conversion between objects and JSON
- **Performance optimized**: Uses reflection caching for improved performance

## Requirements

- PHP 8.3 or higher

## Installation

Install via Composer:

```bash
composer require diego-ninja/granite
```

## Basic Usage

### Creating a DTO

```php
<?php

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class UserDTO extends GraniteDTO
{
    public function __construct(
        public string $name,
        
        #[SerializedName('emailAddress')]
        public string $email,
        
        public ?int $age = null,
        
        #[Hidden]
        public string $password
    ) {}
}

// Create from an array
$user = UserDTO::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
    'password' => 'secret123'
]);

// Convert to array (password will be hidden, email will be serialized as emailAddress)
$array = $user->array();

// Convert to JSON
$json = $user->json();
```

### Creating a value object with validation

```php
<?php

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Validation\Attributes\Max;

final readonly class User extends GraniteVO
{
    public function __construct(
        #[Required]
        public string $name,
        
        #[Required]
        #[Email]
        public string $email,
        
        #[Min(18)]
        #[Max(120)]
        public ?int $age = null
    ) {}
}

// This will pass validation
$validUser = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// This will throw an InvalidArgumentException due to invalid email
try {
    $invalidUser = User::from([
        'name' => 'John Doe',
        'email' => 'not-an-email',
        'age' => 30
    ]);
} catch (InvalidArgumentException $e) {
    // Handle validation error
}
```

### Defining validation rules in methods (Laravel-style)

In addition to using attributes, you can define validation rules in the `rules()` method:

```php
<?php

use Ninja\Granite\GraniteVO;

final readonly class Product extends GraniteVO
{
    public function __construct(
        public string $name,
        public string $sku,
        public float $price,
        public int $quantity
    ) {}
    
    protected static function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'sku' => 'required|string|regex:/^[A-Z0-9]{10}$/',
            'price' => 'required|number|min:0.01',
            'quantity' => 'required|integer|min:0'
        ];
    }
}
```

### Creating value objects with modified properties

Value Objects are immutable, but you can create new instances with modified properties:

```php
<?php

$user = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Create a new user with a different email
$updatedUser = $user->with(['email' => 'newemail@example.com']);
```

### Comparing value objects

```php
<?php

$user1 = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

$user2 = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

$user3 = User::from([
    'name' => 'Jane Doe',
    'email' => 'jane@example.com'
]);

$user1->equals($user2); // true
$user1->equals($user3); // false
```

## Available validation rules

Granite provides a rich set of validation rules:

- `Required`: Ensures a property is not null
- `StringType`: Validates that a property is a string
- `IntegerType`: Validates that a property is an integer
- `NumberType`: Validates that a property is a number (integer or float)
- `BooleanType`: Validates that a property is a boolean
- `ArrayType`: Validates that a property is an array
- `EnumType`: Validates that a property is a valid enum case
- `Min`: Validates minimum value (for numbers) or length (for strings/arrays)
- `Max`: Validates maximum value (for numbers) or length (for strings/arrays)
- `Email`: Validates that a property is a valid email address
- `Url`: Validates that a property is a valid URL
- `IpAddress`: Validates that a property is a valid IP address
- `In`: Validates that a property is one of a set of allowed values
- `Regex`: Validates that a property matches a regular expression pattern
- `Each`: Validates each item in an array
- `When`: Applies conditional validation
- `Callback`: Custom validation using a callback function

## Advanced Usage

### Custom Serialization

You can customize how properties are named and hidden during serialization using both attributes and methods:

#### Using attributes

```php
<?php

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class User extends GraniteDTO
{
    public function __construct(
        #[SerializedName('first_name')]
        public string $firstName,
        
        #[SerializedName('last_name')]
        public string $lastName,
        
        #[SerializedName('email')]
        public string $emailAddress,
        
        #[Hidden]
        public string $password,
        
        #[Hidden]
        public string $apiToken
    ) {}
}
```

#### Using methods

```php
<?php

use Ninja\Granite\GraniteDTO;

final readonly class User extends GraniteDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $emailAddress,
        public string $password,
        public string $apiToken
    ) {}
    
    protected static function serializedNames(): array
    {
        return [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'emailAddress' => 'email'
        ];
    }
    
    protected static function hiddenProperties(): array
    {
        return ['password', 'apiToken'];
    }
}
```

### Custom validation messages

```php
<?php

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;

final readonly class User extends GraniteVO
{
    public function __construct(
        #[Required(message: "Please provide a name")]
        public string $name,
        
        #[Required(message: "Email is required")]
        #[Email(message: "Please provide a valid email address")]
        public string $email
    ) {}
}
```

### Conditional validation

```php
<?php

use Ninja\Granite\GraniteVO;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\When;
use Ninja\Granite\Validation\Rules\Min;

final readonly class Subscription extends GraniteVO
{
    public function __construct(
        public string $type,
        
        #[When(
            condition: fn($value, $data) => $data['type'] === 'paid',
            rule: new Min(10),
            message: "Paid subscriptions must cost at least $10"
        )]
        public ?float $price = null
    ) {}
}
```

## License

This package is open-sourced software licensed under the MIT license.