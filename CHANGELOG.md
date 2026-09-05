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

### Performance
- Fast paths remain available for scalar/Granite DTOs; arrays use the general path until recursive semantics can be proven equivalent.
