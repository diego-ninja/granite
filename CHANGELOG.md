# Changelog

## Unreleased

### Fixed
- Hardened object mapping, source normalization, collection transformers, and validation parsing.
- Replaced unsafe persistent mapping cache serialization with validated JSON.

### Changed
- CI now resolves dependencies consistently across PHP 8.3, 8.4, and 8.5.
- Serialization caching is now conservative: only deeply immutable graphs are cached.
- `ObjectMapper::configure()` now requires the callback to return the immutable `MapperConfig`.

### Behavior changes
- Invalid typed conversions, malformed validation rules and mapping hydration failures now throw contextual exceptions instead of degrading silently.
- Persistent mapping cache files use validated versioned JSON; closures, resources and transformer objects are kept in memory and skipped on disk.
- Arrays are serialized recursively, but array element classes are not inferred from PHPDoc during hydration.

### Security
- Updated PHP_CodeSniffer to a non-vulnerable release line.
- Final `composer audit` verification reports no security vulnerability advisories.

### Performance
- Fast paths remain available for scalar/Granite DTOs; arrays use the general path until recursive semantics can be proven equivalent.
- Final PHP 8.5.10 verification reached 82.78% line coverage, up from the 81.94% baseline.
- The existing benchmark smoke run completed on PHP 8.5.10; scalar creation and property access remain fast-path eligible. Array and nested serialization intentionally use the general path, so historical benchmark values are not directly comparable.

### Verification
- Local gate: 2,038 tests, 4,488 assertions, 3 optional skips, with no warnings or deprecations.
- CI remains configured for PHP 8.3, 8.4, and 8.5; the local gate was run on PHP 8.5.10.
