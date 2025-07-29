<?php

namespace Ninja\Granite\Enums;

enum CacheType: string
{
    case Memory = 'memory';
    case Shared = 'shared';
    case Persistent = 'persistent';
}
