<?php

namespace Ninja\Granite\Contracts;

use Ninja\Granite\Monads\Contracts\Either;

interface GraniteObject
{
    public static function from(string|array|GraniteObject $data): Either;
    public function array(): array;
    public function json(): string;

}