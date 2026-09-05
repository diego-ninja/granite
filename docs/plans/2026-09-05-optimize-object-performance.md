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

- Add tests for builtin conversion, array-property DTOs, normalized JSON/object/Granite sources, nested DTOs, and constructor defaults.
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
