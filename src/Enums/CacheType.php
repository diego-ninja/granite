<?php
// ABOUTME: Defines CacheType as part of the library enum definitions.
// ABOUTME: Owns the CacheType boundary within the library enum definitions.

namespace Ninja\Granite\Enums;

enum CacheType: string
{
    case Memory = 'memory';
    case Shared = 'shared';
    case Persistent = 'persistent';
}
