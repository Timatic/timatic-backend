<?php

namespace Timatic\Nmbrs\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateVariableHourRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $employeeId,
        private readonly int $hourCode,
        private readonly float $hours,
        private readonly string $comment = 'Overuren',
    ) {}

    public function resolveEndpoint(): string
    {
        return '/api/employees/'.$this->employeeId.'/variablehours';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'hourCode' => $this->hourCode,
            'hours' => $this->hours,
            'comment' => $this->comment,
        ];
    }
}
