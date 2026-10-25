<?php

namespace Timatic\GitHub\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasJsonBody;

class CreateRepositoryWebhookRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param array<int, string> $events */
    public function __construct(
        private readonly string $repositoryFullName,
        private readonly string $url,
        private readonly string $secret,
        private readonly array $events,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/repos/'.$this->repositoryFullName.'/hooks';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'name' => 'web',
            'active' => true,
            'events' => $this->events,
            'config' => [
                'url' => $this->url,
                'secret' => $this->secret,
                'content_type' => 'json',
            ],
        ];
    }

    public function createDtoFromResponse(Response $response): int
    {
        return (int) $response->json('id');
    }
}
