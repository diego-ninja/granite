<?php

namespace Tests\Data;

enum StatusTestEnum: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Inactive = 'inactive';
    case Unknown = 'unknown';

}
