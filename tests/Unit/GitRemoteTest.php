<?php

declare(strict_types=1);

use App\DataTransferObjects\GitRemote;
use App\Exceptions\InvalidGitRemoteException;

it('reads the host and path of a git remote', function (string $url, string $host, string $path) {
    $remote = GitRemote::fromUrl($url);

    expect($remote->host)->toBe($host)
        ->and($remote->path)->toBe($path);
})->with([
    'scp-like ssh' => ['git@bitbucket.org:acme/shop.git', 'bitbucket.org', 'acme/shop'],
    'ssh with port' => ['ssh://git@bitbucket.org:22/acme/shop.git', 'bitbucket.org', 'acme/shop'],
    'https with user' => ['https://user@Bitbucket.org/acme/shop.git', 'bitbucket.org', 'acme/shop'],
    'https without .git' => ['https://github.com/acme/shop/', 'github.com', 'acme/shop'],
]);

it('refuses what is not a git remote', function () {
    GitRemote::fromUrl('not a remote');
})->throws(InvalidGitRemoteException::class);
