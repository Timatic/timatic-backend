<?php

namespace Timatic\Jira\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

// @arch-ignore response-dto: the TicketProvider maps this response until #38
class GetIssueRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $key) {}

    public function resolveEndpoint(): string
    {
        return '/rest/api/3/issue/'.$this->key;
    }

    protected function defaultQuery(): array
    {
        return [
            'fields' => 'summary,created,resolutiondate,issuetype,comment',
            'expand' => 'renderedFields,changelog',
        ];
    }
}
