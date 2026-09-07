# Changelog

## 2.0.0 - 2026-09-07

### Added
- Added `Granite::with()` for immutable updates through the normal hydration and validation pipeline.
- Added first-class dev integration coverage for Ramsey UUID and Symfony UID.
- Added CI gates for risky tests, PHPUnit warnings, line coverage, and method coverage.

### Fixed
- Mapping caches are now isolated by deterministic configuration and code fingerprints and are invalidated after in-place mapping, profile, or convention mutations.
- Persistent mapping cache writes are locked, atomic, generation-aware, and reject untrusted paths or malformed payloads.
- Failed `mapTo()` operations restore raw destination state before rethrowing the original error.
- Pebble now takes defensive deep snapshots, preserves explicit `null`, and fingerprints dates, enums, objects, and cycles deterministically.
- Granite equality, validation, and immutable updates now use canonical PHP property state instead of serialized aliases or hidden projections.
- Carbon configuration is composed consistently across global, class, and property-level settings.
- Validation rule parsing now preserves pipes inside regular expressions and rejects invalid patterns without emitting PHP warnings.
- UUID conversion now supports both Ramsey's concrete factory and `UuidInterface` targets.

### Changed
- Serialization results are cached only for readonly, deeply immutable Granite graphs.
- `ObjectMapper::getCache()` returns a configuration-scoped facade; callers should depend on `MappingCache`, not a concrete backend class.
- Explicit `null` values passed through `fromNamedParameters()` are retained as overrides.
- `GraniteVO` is now deprecated alongside the already-deprecated `GraniteDTO`; both are scheduled for removal in v3.0.0.
- Removed unused development dependencies on Faker, Mockery, Rector, and PHP_CodeSniffer.

### Migration notes
- Pebble rejects resources and unsupported internal objects instead of retaining mutable references. Convert such values to arrays or scalars before snapshotting.
- Wrappers around `fromNamedParameters()` must include only intentionally supplied keys; nullable defaults collected with `get_defined_vars()` now overwrite source/default values with `null`.
- Persistent mapper cache schema v1 and unscoped entries are treated as misses and rebuilt in schema v2.

### Verification
- Local PHP 8.5.10 gate: 2,155 tests and 4,770 assertions, with no skips, warnings, or deprecations.
- Coverage gate: 85.14% lines and 72.24% methods.

## 1.7.0 - 2026-09-06

### Fixed
- Hardened object mapping, source normalization, collection transformers, and validation parsing.
- Replaced unsafe persistent mapping cache serialization with validated JSON.
- Method-based serialized names and hidden properties are now honored by serialization fast paths.
- Failed `mapTo()` population now rolls back earlier property writes.
- Cache directory configuration no longer depends on a PHP 8.4-only function.

### Changed
- CI now resolves dependencies consistently across PHP 8.3, 8.4, and 8.5.
- Non-coverage CI jobs now disable coverage reporting explicitly.
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
- Added capability-specific hydration, serialization, and comparison fast paths.
- Cached reflection, validation, Carbon, and serialization metadata across repeated operations.
- Reduced JSON/object hydration time by 82-87%, array-property hydration by 91%, validated hydration by 49%, and cached serialization by 95-97% in the recorded benchmark.
- Reduced cold nested JSON serialization time by 29.4% by avoiding duplicate graph analysis.
- Final PHP 8.5.10 verification reached 82.78% line coverage, up from the 81.94% baseline.
- Full before/after samples and methodology are recorded in `docs/plans/2026-09-05-optimize-object-performance.md`.

### Verification
- Local gate: 2,048 tests, 4,507 assertions, 3 optional skips, with no warnings or deprecations.
- CI remains configured for PHP 8.3, 8.4, and 8.5; the local gate was run on PHP 8.5.10.
