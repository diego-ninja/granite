# 🪨 Granite
[![Latest Version on Packagist](https://img.shields.io/packagist/v/diego-ninja/granite.svg?style=flat&color=blue)](https://packagist.org/packages/diego-ninja/granite)
[![Total Downloads](https://img.shields.io/packagist/dt/diego-ninja/granite.svg?style=flat&color=blue)](https://packagist.org/packages/diego-ninja/granite)
![PHP Version](https://img.shields.io/packagist/php-v/diego-ninja/granite.svg?style=flat&color=blue)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
![GitHub last commit](https://img.shields.io/github/last-commit/diego-ninja/granite?color=blue)

A lightweight zero-dependency PHP library for building immutable, serializable objects with validation capabilities.

## Overview

Granite is a powerful library for creating strongly-typed, immutable Data Transfer Objects (DTOs) and Value Objects (VOs) in PHP. It provides a clean, attribute-based API for defining validation rules, serialization behavior, and type conversions.

## Features

- **Immutable objects**: Create read-only DTOs and Value Objects
- **Built-in validation**: Comprehensive validation system with many predefined rules
- **Attribute-based validation**: Use PHP 8 attributes to define validation rules directly on properties
- **Serialization control**: Customize property names during serialization/deserialization
- **Type conversion**: Automatic conversion of primitive types, DateTimes, and Enums
- **JSON support**: Easy conversion between objects and JSON
- **AutoMapper**: Powerful mapping between different object structures
- **Performance optimized**: Uses reflection caching for improved performance

## Requirements

- PHP 8.3 or higher

## Installation

Install via Composer:

```bash
composer require diego-ninja/granite
```

## Quick Start

### Creating a Data Transfer Object

```php
<?php

use Ninja\Granite\GraniteDTO;
use Ninja\Granite\Serialization\Attributes\SerializedName;
use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class UserDTO extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $name,
        
        #[SerializedName('email_address')]
        public string $email,
        
        #[Hidden]
        public string $password
    ) {}
}

// Create from array
$user = UserDTO::from([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);

// Convert to array (password hidden, email as email_address)
$array = $user->array();

// Convert to JSON
$json = $user->json();
```

### Creating a Value Object with Validation

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
        #[Min(2)]
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
$user = User::from([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// This will throw InvalidArgumentException
try {
    $invalidUser = User::from([
        'name' => 'X',  // Too short
        'email' => 'invalid-email',
        'age' => 15  // Too young
    ]);
} catch (InvalidArgumentException $e) {
    // Handle validation errors
}
```

### Using AutoMapper

```php
<?php

use Ninja\Granite\Mapping\AutoMapper;
use Ninja\Granite\Mapping\Attributes\MapFrom;

// Source DTO
final readonly class UserEntity extends GraniteDTO
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $email
    ) {}
}

// Destination DTO with mapping
final readonly class UserResponse extends GraniteDTO
{
    public function __construct(
        public int $id,
        
        #[MapFrom('firstName')]
        public string $name,
        
        #[MapFrom('lastName')]
        public string $surname,
        
        public string $email
    ) {}
}

$mapper = new AutoMapper();
$userResponse = $mapper->map($userEntity, UserResponse::class);
```

### Immutable Updates

```php
<?php

// Create a new instance with modified properties
$updatedUser = $user->with([
    'email' => 'newemail@example.com',
    'age' => 31
]);

// Compare objects
$user->equals($updatedUser); // false
```

## Documentation

For detailed documentation and advanced usage examples, see:

- **[Validation](docs/validation.md)** - Comprehensive validation system with attributes and custom rules
- **[Serialization](docs/serialization.md)** - Control how objects are converted to/from arrays and JSON
- **[AutoMapper](docs/automapper.md)** - Map data between different object structures automatically

## Key Concepts

### DTOs vs Value Objects

- **DTOs (GraniteDTO)**: Simple data containers for transferring data between layers
- **Value Objects (GraniteVO)**: DTOs with built-in validation for ensuring data integrity

### Validation Approaches

You can define validation rules in two ways:

```php
// Using attributes (recommended for simple rules)
#[Required]
#[Email]
public string $email;

// Using methods (for complex rules)
protected static function rules(): array
{
    return [
        'email' => 'required|email',
        'age' => 'integer|min:18|max:120'
    ];
}
```

### Serialization Control

Customize how your objects are serialized:

```php
// Using attributes
#[SerializedName('api_key')]
#[Hidden]
public string $apiKey;

// Using methods
protected static function serializedNames(): array
{
    return ['apiKey' => 'api_key'];
}

protected static function hiddenProperties(): array
{
    return ['apiKey', 'password'];
}
```

## Examples

### Building an API Response

```php
<?php

final readonly class ApiResponse extends GraniteDTO
{
    public function __construct(
        public bool $success,
        public ?array $data = null,
        public ?string $message = null,
        
        #[Hidden]
        public ?array $debug = null
    ) {}
}

$response = ApiResponse::from([
    'success' => true,
    'data' => ['users' => [...]],
    'debug' => ['query_time' => '50ms']
]);

// Only success, data, and message are included in output
echo $response->json();
```

### Product Catalog Example

```php
<?php

enum ProductStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case OUT_OF_STOCK = 'out_of_stock';
}

final readonly class Product extends GraniteVO
{
    public function __construct(
        #[Required]
        #[Min(1)]
        public string $name,
        
        #[Required]
        #[Min(0.01)]
        public float $price,
        
        #[Required]
        public ProductStatus $status,
        
        #[ArrayType]
        public array $tags = []
    ) {}
}
```

## 🙏 Credits

This project is developed and maintained by 🥷 [Diego Rin](https://diego.ninja) in his free time.
If you find this project useful, please consider giving it a ⭐ on GitHub!

## License

This package is open-sourced software licensed under the MIT license.