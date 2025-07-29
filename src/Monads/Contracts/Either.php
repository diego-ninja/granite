<?php

namespace Ninja\Granite\Monads\Contracts;

interface Either
{
    public function isLeft(): bool;
    public function isRight(): bool;
    public function getLeft(): mixed;
    public function getRight(): mixed;
    public function map(callable $mapper): self;
    public function flatMap(callable $mapper): self;
    public function mapLeft(callable $mapper): self;
    public function fold(callable $leftMapper, callable $rightMapper): mixed;
}