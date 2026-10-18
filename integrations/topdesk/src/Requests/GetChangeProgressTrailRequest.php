<?php

namespace Timatic\Topdesk\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskAction;

class GetChangeProgressTrailRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $number) {}

    public function resolveEndpoint(): string
    {
        return '/operatorChanges/'.rawurlencode($this->number).'/progresstrail';
    }

    protected function defaultQuery(): array
    {
        return [
            'type' => 'memo',
        ];
    }

    /** @return array<int, TopdeskAction> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(function (array $entry): TopdeskAction {
            return new TopdeskAction(
                id: $entry['id'],
                memoText: trim($entry['plainText'] ?? ''),
                entryDate: CarbonImmutable::parse($entry['entryDate']),
                operatorName: $entry['operator']['name'] ?? null,
                personName: $entry['person']['name'] ?? null,
            );
        }, $response->json('results') ?? []);
    }
}
