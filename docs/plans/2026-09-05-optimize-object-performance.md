# Object Pipeline Performance Optimization Plan

> **Execution:** Apply each cut independently on the current branch, run its focused tests and project checks, and create one conventional commit per cut.

**Goal:** Reduce serialization, hydration, deserialization, validation, and mapping overhead while preserving Granite's public API and observable behavior.

**Approach:** Establish a reproducible benchmark first, then remove repeated reflection, graph traversal, type inspection, parser work, and temporary allocations from hot paths. Correctness-sensitive cache invalidation and regression tests land before cache lookup order changes.

**Constraints:** PHP 8.3+, zero runtime dependencies, no breaking changes, no timing assertions in the test suite.

---

## Cut 1: Reproducible benchmark and baseline

- Track the existing benchmark suite and add focused cases for array properties, JSON/object input, validation, cached serialization, and object mapping.
- Support machine-readable JSON output and configurable iterations/repetitions.
- Add a Composer benchmark command.
- Record the baseline before any production-code optimization.
- Run the benchmark smoke check and coding-style checks.
- Commit as `test: add object pipeline performance benchmark`.

## Cut 2: Serialization cache correctness and cache-first reads

- Add regression tests proving serialization changes after runtime configuration changes.
- Invalidate serialization/transformer caches from serialization-affecting configuration mutations and reset.
- Read `SerializationCache` before recomputing deep cacheability.
- Run focused serialization/config tests, the benchmark, and full project checks.
- Commit as `perf: avoid repeated serialization cache analysis`.

## Cut 3: Type conversion and hydration fast paths

- Add tests for builtin conversion, array-property DTOs, normalized JSON/object/Granite sources, nested DTOs, and constructor-default fallback semantics.
- Return builtin named types before class/Carbon checks.
- Split `ClassProfile` capabilities for hydration, serialization, and comparison.
- Retry constructor hydration after structured input normalization.
- Run focused deserialization/profile tests, the benchmark, and full project checks.
- Commit as `perf: expand safe hydration fast paths`.

## Cut 4: Validation and serialization metadata reuse

- Add tests for cached attribute rules and unchanged dynamic method rules.
- Cache immutable attribute-derived rules and skip validation normalization when no rules exist.
- Resolve class-level date metadata once per serialization and avoid transformer lookup for scalar/null values.
- Cache property Carbon transformer metadata with configuration-aware invalidation.
- Run focused validation/serialization tests, the benchmark, and full project checks.
- Commit as `perf: cache validation and serialization metadata`.

## Cut 5: Hydrator chain and JSON parsing

- Add regression tests proving the selected primary object hydrator is not overwritten and null public values keep precedence over getters.
- Select one primary object hydrator, then enrich only with getters.
- Replace JSON validate-plus-decode with one throwing decode while preserving exception semantics.
- Run focused hydration tests, the benchmark, and full project checks.
- Commit as `perf: streamline input hydrators`.

## Cut 6: ObjectMapper hot path

- Add tests for malformed mapping configuration and destination validation behavior.
- Validate mapping configuration without copying it.
- Remove the duplicate destination `class_exists` check.
- Run focused mapper tests, the benchmark, and full project checks.
- Commit as `perf: remove object mapper hot-path allocations`.

## Cut 7: Final evidence and verification

- Run the same benchmark command and environment used for the baseline.
- Compare median before/after timings and document the results.
- Run PHPUnit, PHPStan at maximum level, Pint check, ABOUTME audit, and project audit checks.
- Commit as `docs: record object performance results`.

## Results

Measured on PHP 8.5.10 (Darwin), with OPcache CLI enabled and `XDEBUG_MODE=off`. Each result is the median of 7 repetitions with 100,000 operations per repetition. The baseline commit (`969db61`) and optimized branch were executed consecutively with the same benchmark and dependency set.

| Scenario | Before (µs) | After (µs) | Reduction | Speed-up |
|---|---:|---:|---:|---:|
| `plain.constructor` | 0.157 | 0.147 | 6.3% | 1.07x |
| `hydrate.scalar.array` | 0.287 | 0.284 | 1.2% | 1.01x |
| `hydrate.scalar.named` | 0.358 | 0.356 | 0.6% | 1.01x |
| `hydrate.scalar.json` | 6.227 | 1.019 | 83.6% | 6.11x |
| `hydrate.scalar.object` | 5.589 | 1.000 | 82.1% | 5.59x |
| `hydrate.scalar.granite` | 6.738 | 0.898 | 86.7% | 7.50x |
| `hydrate.array_property` | 2.349 | 0.201 | 91.4% | 11.69x |
| `hydrate.validated` | 9.213 | 4.691 | 49.1% | 1.96x |
| `serialize.scalar.array_cached` | 0.894 | 0.040 | 95.6% | 22.58x |
| `serialize.scalar.json_cached` | 0.882 | 0.040 | 95.5% | 22.04x |
| `serialize.nested.array_cached` | 1.257 | 0.040 | 96.8% | 31.56x |
| `serialize.array_property` | 1.636 | 1.175 | 28.2% | 1.39x |
| `mapper.plain` | 2.079 | 1.869 | 10.1% | 1.11x |
| `mapper.granite` | 1.770 | 1.550 | 12.5% | 1.14x |

The plain constructor control moved by 0.010 µs between adjacent runs; sub-2% changes in already-fast scalar hydration are treated as noise rather than claimed improvements.

### Post-review cold JSON result

A final review found that a cold `json()` call analyzed graph cacheability before calling `array()`, which repeated the same analysis. A dedicated cold nested JSON scenario was added and measured immediately before and after removing that duplicate traversal, using the same PHP, OPcache, Xdebug, iteration, and repetition settings.

| Scenario | Before (µs) | After (µs) | Reduction | Speed-up |
|---|---:|---:|---:|---:|
| `serialize.nested.json_cold` | 3.520 | 2.484 | 29.4% | 1.42x |
