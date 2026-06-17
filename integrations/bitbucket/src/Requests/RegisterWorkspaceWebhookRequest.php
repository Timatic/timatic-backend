<?php

namespace Timatic\Bitbucket\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;
use Timatic\Bitbucket\DataTransferObjects\BitbucketWebhook;

class RegisterWorkspaceWebhookRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $workspace,
        private readonly string $webhookUrl,
        private readonly string $secret,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/workspaces/'.$this->workspace.'/hooks';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'description' => 'Timatic',
            'url' => $this->webhookUrl,
            'active' => true,
            'events' => [
                'repo:push',
                'pullrequest:comment_created',
                'pullrequest:approved',
                'pullrequest:changes_request_created',
                'pullrequest:fulfilled',
                'pullrequest:rejected',
            ],
            'secret' => $this->secret,
        ];
    }

    public function createDtoFromResponse(Response $response): BitbucketWebhook
    {
        return new BitbucketWebhook(
            uuid: $response->json('uuid') ?? '',
        );
    }
}
