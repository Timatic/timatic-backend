<?php

declare(strict_types=1);

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Jira\DataTransferObjects\JiraCloudResource;

class GetTenantInfoRequest extends Request
{
    // Lives on the Jira site itself, the only place that tells its cloud id without a login
    public ?bool $allowBaseUrlOverride = true;

    protected Method $method = Method::GET;

    public function __construct(private readonly string $siteUrl) {}

    public function resolveEndpoint(): string
    {
        return $this->siteUrl.'/_edge/tenant_info';
    }

    public function createDtoFromResponse(Response $response): JiraCloudResource
    {
        return new JiraCloudResource(
            id: (string) $response->json('cloudId'),
            url: $this->siteUrl,
        );
    }
}
