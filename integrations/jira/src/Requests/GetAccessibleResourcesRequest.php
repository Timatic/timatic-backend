<?php

declare(strict_types=1);

namespace Timatic\Jira\Requests;

use Illuminate\Support\Collection;
use Saloon\Enums\Method;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraCloudResource;

class GetAccessibleResourcesRequest extends Request
{
    // Lives on api.atlassian.com while the token endpoints live on auth.atlassian.com
    public ?bool $allowBaseUrlOverride = true;

    protected Method $method = Method::GET;

    public function __construct(private readonly string $accessToken) {}

    public function resolveEndpoint(): string
    {
        return 'https://api.atlassian.com/oauth/token/accessible-resources';
    }

    /**
     * @return Collection<int, JiraCloudResource>
     */
    public function createDtoFromResponse(Response $response): Collection
    {
        /** @var list<array{id: string, url: string}> $resources */
        $resources = $response->json();

        return collect($resources)->map(fn (array $resource): JiraCloudResource => new JiraCloudResource(
            id: $resource['id'],
            url: $resource['url'],
        ));
    }

    protected function defaultAuth(): TokenAuthenticator
    {
        return new TokenAuthenticator($this->accessToken);
    }
}
