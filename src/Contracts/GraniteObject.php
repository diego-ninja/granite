<?php

namespace Ninja\Granite\Contracts;

interface GraniteObject
{
    public static function from(string|array|GraniteObject $data): GraniteObject;
    public function array(): array;
    public function json(): string;

}