<?php

namespace Ninja\Granite\Monads\Factories;

use Ninja\Granite\Monads\Nil;
use Ninja\Granite\Monads\Contracts\LinkedList as LinkedListContract;

class LinkedList
{
    public static function empty(): LinkedListContract { return Nil::instance(); }

    public static function of(mixed ...$values): LinkedListContract
    {
        return self::fromArray($values);
    }

    public static function fromArray(array $values): LinkedListContract
    {
        $list = self::empty();
        foreach (array_reverse($values) as $value) {
            $list = $list->cons($value);
        }
        return $list;
    }
}