<?php

namespace Timatic\Bitbucket\DataTransferObjects;

readonly class BitbucketWorkspace
{
    public function __construct(
        public string $slug,
        public bool $isAdministrator,
    ) {}
}
