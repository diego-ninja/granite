# 🪨 Granite

[![Latest Version on Packagist](https://img.shields.io/packagist/v/diego-ninja/granite.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://packagist.org/packages/diego-ninja/granite)
[![Total Downloads](https://img.shields.io/packagist/dt/diego-ninja/granite.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://packagist.org/packages/diego-ninja/granite)
![PHP Version](https://img.shields.io/packagist/php-v/diego-ninja/granite.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://opensource.org/licenses/MIT)
![GitHub last commit](https://img.shields.io/github/last-commit/diego-ninja/granite?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)
[![wakatime](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/3cc2ec60-a8b4-4ddc-aeac-ea78e37a094b.svg?style=flat-square&color=blue&logoColor=%23949ca4&labelColor=%233f4750)](https://wakatime.com/badge/user/bd65f055-c9f3-4f73-92aa-3c9810f70cc3/project/3cc2ec60-a8b4-4ddc-aeac-ea78e37a094b)

[![Tests](https://img.shields.io/github/actions/workflow/status/diego-ninja/granite/tests.yml?branch=main&style=flat-square&logo=github&label=tests&logoColor=%23949ca4&labelColor=%233f4750)]()
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/diego-ninja/granite/static-analysis.yml?branch=main&style=flat-square&logo=github&label=phpstan%2010&logoColor=%23949ca4&labelColor=%233f4750)]()
[![Code Style](https://img.shields.io/github/actions/workflow/status/diego-ninja/granite/code-style.yml?branch=main&style=flat-square&logo=github&label=pint%3A%20PER&logoColor=%23949ca4&labelColor=%233f4750)]()
[![Coveralls](https://img.shields.io/coverallsCoverage/github/diego-ninja/granite?branch=main&style=flat-square&logo=coveralls&logoColor=%23949ca4&labelColor=%233f4750&link=https%3A%2F%2Fcoveralls.io%2Fgithub%2Fdiego-ninja%2Fgranite)]()

A powerful, zero-dependency PHP library for building **immutable**, **serializable** objects with **validation** and **mapping** capabilities. Perfect for DTOs, Value Objects, API responses, and domain modeling.

## 🪶 Pebble - Lightweight Immutable Snapshots

**Pebble** is a lightweight alternative to Granite for when you need immutable snapshots without validation overhead — ideal for caching, Eloquent snapshots, and fast comparisons.

```php
$snapshot = Pebble::from($eloquentModel);

$snapshot->name;           // Magic __get
$snapshot['email'];        // ArrayAccess
$snapshot->equals($other); // O(1) fingerprint comparison
```

📖 **[Read Pebble Documentation](docs/pebble.md)**

---

## ✨ Features

- **Immutable Objects** — Read-only DTOs and Value Objects, thread-safe by design
- **Flexible `from()` Method** — Create from arrays, JSON, named parameters, other Granite objects, or mix them
- **Comprehensive Validation** — 30+ built-in rules including Carbon date validation (Age, Future, Past, BusinessDay...)
- **ObjectMapper** — Convention-based property mapping between objects with custom transformations
- **Smart Serialization** — Custom property names, naming conventions, hidden fields, Carbon date formats
- **Object Comparison** — Deep equality with `equals()`, detailed diffs with `differs()`
- **Performance Optimized** — Conservative fast path for simple DTOs, deep immutable WeakMap caching, direct property access

## 🚀 Quick Start

### Installation

```bash
composer require diego-ninja/granite
```

### Basic Usage

```php
use Ninja\Granite\Granite;
use Ninja\Granite\Validation\Attributes\Required;
use Ninja\Granite\Validation\Attributes\Email;
use Ninja\Granite\Validation\Attributes\Min;
use Ninja\Granite\Serialization\Attributes\Hidden;

final readonly class User extends Granite
{
    public function __construct(
        #[Required]
        #[Min(2)]
        public string $name,

        #[Required]
        #[Email]
        public string $email,

        #[Hidden]
        public ?string $password = null,
    ) {}
}

// Create from array
$user = User::from(['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'secret']);

// Create from named parameters
$user = User::from(name: 'John Doe', email: 'john@example.com');

// Immutable updates
$updated = $user->with(['name' => 'Jane Doe']);

// Serialization (password hidden automatically)
$json = $user->json();   // {"name":"John Doe","email":"john@example.com"}
$array = $user->array();
```

## 📖 Documentation

### Core Concepts

- **[Enhanced from() Method](docs/hydration.md)** — Multiple invocation patterns for flexible object creation
- **[Validation](docs/validation.md)** — Comprehensive validation system with 30+ built-in rules including Carbon
- **[Serialization](docs/serialization.md)** — Control how objects are converted to/from arrays and JSON with Carbon support
- **[Object Comparison](docs/comparison.md)** — Deep equality checks and difference detection
- **[ObjectMapper](docs/automapper.md)** — Powerful object-to-object mapping with conventions
- **[Pebble](docs/pebble.md)** — Lightweight immutable snapshots with fingerprinting
- **[Advanced Usage](docs/advanced_usage.md)** — Patterns for complex applications
- **[API Reference](docs/api_reference.md)** — Complete API documentation

### Guides

- **[Migration Guide](docs/migration_guide.md)** — Migrate from arrays, stdClass, Doctrine, Laravel
- **[Troubleshooting](docs/troubleshooting.md)** — Common issues and solutions

## 📈 Performance & Benchmarks

Granite uses a **multi-layer fast path** system that detects simple DTOs at class-load time and bypasses the full hydration pipeline (reflection, metadata, type conversion) entirely. For objects that qualify, the overhead vs plain PHP constructors is minimal.

### Benchmark Results

Benchmarked on PHP 8.4, comparing Granite against plain PHP constructors and `Pebble` (Granite's lightweight companion). The test DTO has 6 fields (`int`, `string`, `string`, `int`, `string`, `bool`).

#### Object Creation

| Benchmark | µs/op | vs Plain PHP |
|-----------|------:|:------------:|
| Plain PHP constructor | 0.33 | — |
| `Granite::from(array)` | 1.14 | 3.5x |
| `Granite::from(named args)` | 1.13 | 3.4x |
| `Pebble::from(array)` | 3.62 | 11.1x |

#### Nested Object Creation (3 objects)

| Benchmark | µs/op | vs Plain PHP |
|-----------|------:|:------------:|
| Plain PHP constructors | 0.78 | — |
| `Granite::from(array)` | 3.63 | 4.6x |
| `Pebble::from(array)` | 6.80 | 8.7x |

Granite recursively applies the fast path to nested Granite-typed properties, so the overhead scales linearly with object depth rather than exploding through the full hydration pipeline.

#### Serialization

| Benchmark | µs/op | vs Plain PHP |
|-----------|------:|:------------:|
| Plain PHP `toArray()` | 0.19 | — |
| `Granite array()` | 0.25 | 1.3x |
| Plain PHP `json_encode(array)` | 0.25 | — |
| `Granite json()` | 0.26 | 1.0x |

Both `array()` and `json()` may be cached in a `WeakMap` when the complete value graph is demonstrably immutable. Arrays, mutable dates and unknown objects bypass the cache so later mutations cannot return stale data.

#### Equality Check

| Benchmark | µs/op | vs Plain PHP |
|-----------|------:|:------------:|
| Plain array `===` | 0.12 | — |
| `Granite equals()` | 0.48 | 4.0x |
| `Pebble equals()` (fingerprint) | 0.31 | 2.6x |

For simple DTOs, `equals()` compares properties directly without building intermediate arrays, with early exit on the first difference.

#### Collection (100 items, create from array)

| Benchmark | µs/op | vs Plain PHP |
|-----------|------:|:------------:|
| Plain PHP `array_map` + constructors | 31 | — |
| Granite `array_map` + `from()` | 106 | 3.4x |
| Pebble `array_map` + `from()` | 343 | 11.1x |

#### Property Access

Granite uses native PHP readonly promoted properties — property access is **identical** to plain PHP objects, with zero overhead:

| Benchmark | µs/op |
|-----------|------:|
| Plain PHP readonly | 0.15 |
| Granite readonly | 0.14 |
| Pebble `__get()` | 1.03 |

### How it works

Granite's performance comes from three layers of optimization:

1. **Fast path detection** (`ClassProfile`) — At class-load time, Granite analyzes each class and determines if it can skip the full hydration pipeline. A class qualifies when all constructor parameters are scalar/null-compatible primitives or other Granite subclasses, and the class has no special attributes (`#[Hidden]`, `#[SerializedName]`, validation rules, etc.). Arrays use the general path until their element semantics can be proven equivalent.

2. **WeakMap caching** — `array()` and `json()` results are cached only for deeply immutable graphs. A readonly outer object does not make mutable arrays or dates immutable. When the object is garbage collected, the cache entry is automatically cleaned up.

3. **Direct property access** — For serialization and comparison, Granite reads properties directly by name (`$instance->$name`) instead of going through reflection, metadata lookups, and type conversion.

Classes that don't qualify for the fast path (those with validation attributes, naming conventions, Carbon dates, etc.) use the standard hydration pipeline, which is still optimized with reflection caching and O(1) hidden property lookups.

### Running the Benchmarks

```bash
php benchmarks/GraniteBench.php
```

## ⚠️ Deprecation Notice

`GraniteDTO` and `GraniteVO` are deprecated since v2.0.0 in favor of the unified `Granite` base class. Both still extend `Granite` for backward compatibility but will be removed in v3.0.0.

```php
// ❌ Deprecated — use Granite instead
final readonly class User extends GraniteVO { }
// ✅
final readonly class User extends Granite { }
```

## 🔧 Requirements

- **PHP 8.3+** — Takes advantage of modern PHP features
- **No dependencies** — Zero external dependencies for maximum compatibility

## 📦 Installation

```bash
composer require diego-ninja/granite
```

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Credits

This project is developed and maintained by 🥷 [Diego Rin](https://diego.ninja) in his free time.

If you find this project useful, please consider:
- ⭐ Starring the repository
- 🐛 Reporting bugs and issues
- 💡 Suggesting new features
- 🔧 Contributing code improvements

---

**Made with ❤️ for the PHP community**
