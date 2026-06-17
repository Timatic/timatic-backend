<?php

namespace Timatic\Bitbucket\DataTransferObjects;

readonly class BitbucketWebhook
{
    public function __construct(
        public string $uuid,
    ) {}
}
