<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class ArticleDTO
{
    public function __construct(
        public int $id,
        public string $title,
        public array $comments = []
    ) {}
}
