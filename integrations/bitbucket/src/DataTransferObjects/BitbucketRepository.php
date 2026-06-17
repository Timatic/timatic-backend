<?php

namespace Timatic\Bitbucket\DataTransferObjects;

readonly class BitbucketRepository
{
    public function __construct(
        public string $slug,
        public string $fullName,
        public string $name,
    ) {}
}
