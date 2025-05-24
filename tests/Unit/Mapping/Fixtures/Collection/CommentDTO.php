<?php

namespace Tests\Unit\Mapping\Fixtures\Collection;

class CommentDTO
{
    public function __construct(
        public int $id,
        public string $text,
        public string $author
    ) {}
}
