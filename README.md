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

Measured on PHP 8.5.10 with OPcache CLI enabled, using the median of 7 repetitions with 100,000 operations each:

| Scenario | µs/op | Improvement |
|---|---:|---:|
| Plain PHP constructor | 0.147 | control |
| `Granite::from(array)` | 0.284 | — |
| `Granite::from(JSON)` | 1.019 | 6.11x faster |
| `Granite::from(object)` | 1.000 | 5.59x faster |
| `Granite::from(Granite)` | 0.898 | 7.50x faster |
| Array-property DTO hydration | 0.201 | 11.69x faster |
| Validated DTO hydration | 4.691 | 1.96x faster |
| Cached `array()` | 0.040 | 22.58x faster |
| Cached nested `array()` | 0.040 | 31.56x faster |
| General array serialization | 1.175 | 1.39x faster |
| ObjectMapper to plain object | 1.869 | 1.11x faster |
| ObjectMapper to Granite | 1.550 | 1.14x faster |

Improvements compare the same benchmark against the pre-optimization baseline. Full samples and methodology are recorded in [the performance optimization plan](docs/plans/2026-09-05-optimize-object-performance.md#results).

### How it works

Granite's performance comes from three layers of optimization:

1. **Capability-specific fast paths** (`ClassProfile`) — Hydration, serialization, and comparison are evaluated independently. Exact array properties can use constructor hydration while still using recursive general serialization.

2. **WeakMap caching** — `array()` and `json()` results are read before repeating graph analysis and are cached only for deeply immutable graphs. Runtime serialization configuration invalidates cached values.

3. **Compiled metadata** — Reflection objects, validation attributes, validators, class date providers, and property transformers are reused across operations.

Classes that need conversion or custom naming use the general pipeline, with cached metadata and early exits for scalar values.

### Running the Benchmarks

```bash
php benchmarks/GraniteBench.php
composer bench:objects
composer bench:objects -- --json --iterations=100000 --repetitions=7
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
