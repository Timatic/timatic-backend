<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Exceptions\InvalidGitRemoteException;

/**
 * A repository as its git remote names it, whether cloned over ssh or https.
 */
readonly class GitRemote
{
    public function __construct(
        public string $host,
        public string $path,
    ) {}

    /**
     * @throws InvalidGitRemoteException
     */
    public static function fromUrl(string $url): self
    {
        // The scp-like form git@host:owner/repo has no scheme, so parse_url cannot read it.
        if (preg_match('#^[\w.-]+@([^:/]+):(?!//)(.+)$#', $url, $matches) === 1) {
            [, $host, $path] = $matches;
        } else {
            $host = parse_url($url, PHP_URL_HOST);
            $path = parse_url($url, PHP_URL_PATH);
        }

        $path = preg_replace('#\.git$#', '', trim((string) $path, '/'));

        if (! is_string($host) || $host === '' || ! str_contains((string) $path, '/')) {
            throw InvalidGitRemoteException::forUrl($url);
        }

        return new self(strtolower($host), (string) $path);
    }

    public function name(): string
    {
        return "{$this->host}/{$this->path}";
    }
}
