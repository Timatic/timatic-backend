<?php

namespace Timatic\Topdesk\Requests;

use Carbon\CarbonImmutable;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Timatic\Topdesk\DataTransferObjects\TopdeskAction;

class GetIncidentActionsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $number) {}

    public function resolveEndpoint(): string
    {
        return '/incidents/number/'.rawurlencode($this->number).'/actions';
    }

    protected function defaultQuery(): array
    {
        return [
            'page_size' => 99,
            'start' => 0,
        ];
    }

    /** @return array<int, TopdeskAction> */
    public function createDtoFromResponse(Response $response): array
    {
        return array_map(function (array $action): TopdeskAction {
            return new TopdeskAction(
                id: $action['id'],
                memoText: $this->html2text($action['memoText'] ?? ''),
                entryDate: CarbonImmutable::parse($action['entryDate']),
                operatorName: $action['operator']['name'] ?? null,
                personName: $action['person']['name'] ?? null,
            );
        }, $response->json());
    }

    private function html2text(string $text): string
    {
        return str_replace(['<br>', '<br/>'], "\n", strip_tags($text, '<br>'));
    }
}
