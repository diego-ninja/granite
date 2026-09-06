<?php

// ABOUTME: Fixture whose name contains no ID suffix despite containing identity semantics.
// ABOUTME: Confirms UUID/ULID duck typing does not match arbitrary names.

declare(strict_types=1);

namespace Tests\Fixtures\VOs;

final readonly class Identity {}
